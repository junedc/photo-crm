<?php

namespace App\Http\Controllers;

use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignResult;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\SubscriberGroup;
use App\Models\Template;
use App\Models\Tenant;
use App\Support\DateFormatter;
use App\Support\TenantStatuses;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Support\TrackedEmailSender;

class CampaignController extends Controller
{
    public function index(CurrentTenant $currentTenant): View
    {
        $tenant = $currentTenant->get();
        $campaigns = Campaign::query()
            ->with('template')
            ->withCount('results')
            ->latest()
            ->get();

        return view('admin.app', [
            'page' => 'campaigns',
            'props' => [
                'tenant' => $this->serializeTenant($tenant),
                'routes' => [
                    ...$this->baseRoutes(),
                    ...$this->campaignRoutes(),
                ],
                'campaigns' => $campaigns->map(fn (Campaign $campaign) => $this->serializeCampaign($campaign))->values()->all(),
                'campaignStatuses' => ['all', ...TenantStatuses::names($tenant, TenantStatuses::SCOPE_CAMPAIGN)],
                'templates' => $this->templateOptions(),
                'groups' => $this->groupOptions(),
                'recipientOptions' => $this->recipientOptions(),
            ],
        ]);
    }

    public function create(CurrentTenant $currentTenant): View
    {
        $tenant = $currentTenant->get();

        return view('admin.app', [
            'page' => 'campaigns-create',
            'props' => [
                'tenant' => $this->serializeTenant($tenant),
                'routes' => [
                    ...$this->baseRoutes(),
                    ...$this->campaignRoutes(),
                ],
                'templates' => $this->templateOptions(),
                'groups' => $this->groupOptions(),
                'recipientOptions' => $this->recipientOptions(),
            ],
        ]);
    }

    public function show(CurrentTenant $currentTenant, Campaign $campaign): View
    {
        $tenant = $currentTenant->get();
        $campaign->load(['template', 'results'])->loadCount('results');

        return view('admin.app', [
            'page' => 'campaigns-detail',
            'props' => [
                'tenant' => $this->serializeTenant($tenant),
                'routes' => [
                    ...$this->baseRoutes(),
                    ...$this->campaignRoutes(),
                ],
                'templates' => $this->templateOptions(),
                'groups' => $this->groupOptions(),
                'recipientOptions' => $this->recipientOptions(),
                'campaign' => $this->serializeCampaign($campaign),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validateCampaign($request);
        $groupIds = $data['group_ids'] ?? [];
        unset($data['group_ids']);

        $tenant = app(CurrentTenant::class)->get();
        $draftStatus = $tenant ? TenantStatuses::firstOrCreateWorkspaceStatus($tenant, TenantStatuses::SCOPE_CAMPAIGN, 'draft') : null;

        $campaign = Campaign::query()->create([
            ...$data,
            'campaign_status_id' => $draftStatus?->id,
            'status' => $draftStatus?->name ?? 'draft',
        ]);
        $campaign->load(['template', 'results'])->loadCount('results');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Campaign draft created.',
                'record' => $this->serializeCampaign($campaign, $groupIds),
            ]);
        }

        return redirect()->route('campaigns.show', $campaign)->with('status', 'Campaign draft created.');
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse|JsonResponse
    {
        $data = $this->validateCampaign($request);
        $groupIds = $data['group_ids'] ?? [];
        unset($data['group_ids']);

        $tenant = app(CurrentTenant::class)->get();
        $statusName = $campaign->sent_at ? 'sent' : 'draft';
        $status = $tenant ? TenantStatuses::firstOrCreateWorkspaceStatus($tenant, TenantStatuses::SCOPE_CAMPAIGN, $statusName) : null;

        $campaign->update([
            ...$data,
            'campaign_status_id' => $status?->id,
            'status' => $status?->name ?? $statusName,
        ]);
        $campaign->refresh();
        $campaign->load(['template', 'results'])->loadCount('results');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Campaign updated.',
                'record' => $this->serializeCampaign($campaign, $groupIds),
            ]);
        }

        return redirect()->route('campaigns.show', $campaign)->with('status', 'Campaign updated.');
    }

    public function send(Request $request, Campaign $campaign, TrackedEmailSender $trackedEmailSender): RedirectResponse|JsonResponse
    {
        $tenantId = app(CurrentTenant::class)->id();
        $data = $request->validate([
            'group_ids' => ['required', 'array', 'min:1'],
            'group_ids.*' => ['integer', Rule::exists('subscriber_groups', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
        ]);

        $recipients = $this->recipientsForGroups($data['group_ids']);
        abort_if($recipients->isEmpty(), 422, 'Select at least one group with subscribed customers or leads.');

        $campaign->load(['tenant', 'template']);

        foreach ($recipients as $recipient) {
            $email = $this->recipientEmail($recipient);

            $result = CampaignResult::query()->updateOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'email' => $email,
                ],
                [
                    'campaign_recipient_id' => $recipient->id,
                    'name' => $this->recipientName($recipient),
                    'token' => Str::uuid()->toString(),
                    'status' => 'sent',
                    'sent_at' => now(),
                    'opened_at' => null,
                    'bounced_at' => null,
                    'unsubscribed_at' => null,
                ]
            );

            $trackedEmailSender->send(
                new CampaignMail($campaign, $result),
                [[
                    'email' => $email,
                    'name' => $this->recipientName($recipient),
                ]],
                [],
                ['tenant' => $campaign->tenant, 'context' => $campaign],
            );
        }

        $sentStatus = $campaign->tenant ? TenantStatuses::firstOrCreateWorkspaceStatus($campaign->tenant, TenantStatuses::SCOPE_CAMPAIGN, 'sent') : null;

        $campaign->update([
            'campaign_status_id' => $sentStatus?->id,
            'status' => $sentStatus?->name ?? 'sent',
            'sent_count' => $recipients->count(),
            'sent_at' => now(),
        ]);
        $campaign->refresh();
        $campaign->load(['template', 'results'])->loadCount('results');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Campaign sent to '.$recipients->count().' recipient'.($recipients->count() === 1 ? '.' : 's.'),
                'record' => $this->serializeCampaign($campaign, $data['group_ids']),
            ]);
        }

        return redirect()->route('campaigns.show', $campaign)->with('status', 'Campaign sent.');
    }

    public function destroy(Request $request, Campaign $campaign): RedirectResponse|JsonResponse
    {
        $campaign->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Campaign deleted.']);
        }

        return redirect()->route('campaigns.index')->with('status', 'Campaign deleted.');
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $template = Template::query()->create($this->validateTemplate($request));

        return response()->json([
            'message' => 'Template saved.',
            'record' => $this->serializeTemplate($template),
        ]);
    }

    public function updateTemplate(Request $request, Template $template): JsonResponse
    {
        $template->update($this->validateTemplate($request));

        return response()->json([
            'message' => 'Template updated.',
            'record' => $this->serializeTemplate($template->refresh()),
        ]);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $group = SubscriberGroup::query()->create($this->validateGroup($request));

        return response()->json([
            'message' => 'Subscriber group saved.',
            'record' => $this->serializeGroup($group->loadCount('recipients')),
        ]);
    }

    public function updateGroup(Request $request, SubscriberGroup $group): JsonResponse
    {
        $group->update($this->validateGroup($request));

        return response()->json([
            'message' => 'Subscriber group updated.',
            'record' => $this->serializeGroup($group->refresh()->loadCount('recipients')),
        ]);
    }

    public function importGroup(Request $request, SubscriberGroup $group): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $handle = fopen($data['file']->getRealPath(), 'r');
        abort_if($handle === false, 422, 'Unable to read import file.');

        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(fn ($header) => Str::of((string) $header)->lower()->replace([' ', '-'], '_')->toString(), $headers);
        $created = 0;
        $updated = 0;
        $attached = 0;

        DB::transaction(function () use ($handle, $headers, $group, &$created, &$updated, &$attached): void {
            while (($row = fgetcsv($handle)) !== false) {
                $record = array_combine($headers, array_pad($row, count($headers), null));
                if (! is_array($record)) {
                    continue;
                }

                $email = Str::lower(trim((string) ($record['email'] ?? '')));
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $firstName = trim((string) ($record['first_name'] ?? $record['firstname'] ?? ''));
                $lastName = trim((string) ($record['last_name'] ?? $record['lastname'] ?? ''));
                $fullName = trim($firstName.' '.$lastName) ?: trim((string) ($record['full_name'] ?? $record['name'] ?? $email));
                $phone = trim((string) ($record['phone'] ?? $record['mobile'] ?? ''));
                $source = Str::lower(trim((string) ($record['source'] ?? 'customer')));

                if ($source === 'lead') {
                    $lead = Lead::query()->where('customer_email', $email)->first();
                    if ($lead === null) {
                        $lead = Lead::query()->create([
                            'customer_name' => $fullName,
                            'customer_email' => $email,
                            'customer_phone' => $phone ?: null,
                            'status' => 'campaign',
                            'last_activity_at' => now(),
                        ]);
                        $created++;
                    } else {
                        $lead->update([
                            'customer_name' => $fullName ?: $lead->customer_name,
                            'customer_phone' => $phone ?: $lead->customer_phone,
                            'last_activity_at' => now(),
                        ]);
                        $updated++;
                    }

                    $attached += $this->upsertGroupRecipient($group, $lead);

                    continue;
                }

                $customer = Customer::query()->where('email', $email)->first();
                if ($customer === null) {
                    $customer = Customer::query()->create([
                        'full_name' => $fullName,
                        'email' => $email,
                        'phone' => $phone ?: 'Not provided',
                    ]);
                    $created++;
                } else {
                    $customer->update([
                        'full_name' => $fullName ?: $customer->full_name,
                        'phone' => $phone ?: $customer->phone,
                    ]);
                    $updated++;
                }

                $attached += $this->upsertGroupRecipient($group, $customer);
            }
        });

        fclose($handle);

        return response()->json([
            'message' => "Import complete: {$created} created, {$updated} updated, {$attached} added to group.",
            'record' => $this->serializeGroup($group->refresh()->loadCount('recipients')),
        ]);
    }

    public function storeGroupRecipients(Request $request, SubscriberGroup $group): JsonResponse
    {
        $tenantId = app(CurrentTenant::class)->id();
        $data = $request->validate([
            'customer_ids' => ['nullable', 'array'],
            'customer_ids.*' => ['integer', Rule::exists('customers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'lead_ids' => ['nullable', 'array'],
            'lead_ids.*' => ['integer', Rule::exists('leads', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
        ]);

        $customerIds = array_values(array_unique($data['customer_ids'] ?? []));
        $leadIds = array_values(array_unique($data['lead_ids'] ?? []));
        abort_if($customerIds === [] && $leadIds === [], 422, 'Select at least one customer or lead.');

        $attached = 0;

        DB::transaction(function () use ($group, $customerIds, $leadIds, &$attached): void {
            foreach (Customer::query()->whereIn('id', $customerIds)->get() as $customer) {
                $attached += $this->upsertGroupRecipient($group, $customer);
            }

            foreach (Lead::query()->whereIn('id', $leadIds)->get() as $lead) {
                $attached += $this->upsertGroupRecipient($group, $lead);
            }
        });

        return response()->json([
            'message' => $attached === 0
                ? 'Those recipients are already in this group.'
                : $attached.' recipient'.($attached === 1 ? '' : 's').' added to group.',
            'record' => $this->serializeGroup($group->refresh()->loadCount('recipients')),
        ]);
    }

    public function markBounce(CampaignResult $result): JsonResponse
    {
        $result->load('campaign');
        $this->authorizeResultTenant($result);

        $result->update([
            'status' => 'bounced',
            'bounced_at' => $result->bounced_at ?? now(),
        ]);

        return response()->json([
            'message' => 'Recipient marked as bounced.',
            'record' => $this->serializeCampaign($result->campaign->refresh()->load(['template', 'results'])),
        ]);
    }

    public function trackOpen(string $token)
    {
        $result = CampaignResult::query()
            ->with('campaign')
            ->where('token', $token)
            ->firstOrFail();

        $this->authorizeResultTenant($result);

        if ($result->opened_at === null && $result->unsubscribed_at === null) {
            $result->update([
                'status' => 'opened',
                'opened_at' => now(),
            ]);
        }

        return response(base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw=='), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function unsubscribe(string $token): View
    {
        $result = CampaignResult::query()
            ->with('campaign')
            ->where('token', $token)
            ->firstOrFail();

        $this->authorizeResultTenant($result);

        $result->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => $result->unsubscribed_at ?? now(),
        ]);

        $this->deleteRecipientsByEmail($result->email);

        return view('campaigns.unsubscribe', [
            'recipient' => $result,
            'tenant' => app(CurrentTenant::class)->get(),
        ]);
    }

    private function validateCampaign(Request $request): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        return $request->validate([
            'template_id' => ['required', 'integer', Rule::exists('templates', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'subject' => ['required', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'button_text' => ['nullable', 'string', 'max:80'],
            'button_url' => ['nullable', 'url', 'max:2048', 'required_with:button_text'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['integer', Rule::exists('subscriber_groups', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
        ]);
    }

    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'html_body' => ['required', 'string', 'max:20000'],
            'button_text' => ['nullable', 'string', 'max:80'],
            'button_url' => ['nullable', 'url', 'max:2048', 'required_with:button_text'],
        ]);
    }

    private function validateGroup(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function upsertGroupRecipient(SubscriberGroup $group, Model $recipientModel): int
    {
        $attributes = [
            'subscriber_group_id' => $group->id,
            'recipient_type' => $recipientModel->getMorphClass(),
            'recipient_id' => $recipientModel->getKey(),
        ];

        $recipient = CampaignRecipient::query()->firstOrNew($attributes);
        $isNew = ! $recipient->exists;
        $recipient->fill($attributes);
        $recipient->save();

        return $isNew ? 1 : 0;
    }

    private function recipientsForGroups(array $groupIds)
    {
        if ($groupIds === []) {
            return collect();
        }

        $unsubscribedEmails = CampaignResult::query()
            ->whereNotNull('unsubscribed_at')
            ->pluck('email')
            ->map(fn (string $email) => Str::lower($email))
            ->all();

        return CampaignRecipient::query()
            ->with('recipient')
            ->whereIn('subscriber_group_id', $groupIds)
            ->get()
            ->filter(fn (CampaignRecipient $recipient) => filled($this->recipientEmail($recipient)))
            ->reject(fn (CampaignRecipient $recipient) => in_array(Str::lower($this->recipientEmail($recipient)), $unsubscribedEmails, true))
            ->unique(fn (CampaignRecipient $recipient) => Str::lower($this->recipientEmail($recipient)))
            ->values();
    }

    private function templateOptions(): array
    {
        return Template::query()
            ->latest()
            ->get()
            ->map(fn (Template $template) => $this->serializeTemplate($template))
            ->values()
            ->all();
    }

    private function groupOptions(): array
    {
        return SubscriberGroup::query()
            ->with(['recipients.recipient'])
            ->withCount('recipients')
            ->latest()
            ->get()
            ->map(fn (SubscriberGroup $group) => $this->serializeGroup($group))
            ->values()
            ->all();
    }

    private function recipientOptions(): array
    {
        return [
            'customers' => Customer::query()
                ->orderBy('full_name')
                ->get()
                ->map(fn (Customer $customer) => [
                    'id' => $customer->id,
                    'label' => $customer->full_name ?: $customer->email,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'source' => 'customer',
                ])
                ->values()
                ->all(),
            'leads' => Lead::query()
                ->orderBy('customer_name')
                ->get()
                ->map(fn (Lead $lead) => [
                    'id' => $lead->id,
                    'label' => $lead->customer_name ?: $lead->customer_email,
                    'email' => $lead->customer_email,
                    'phone' => $lead->customer_phone,
                    'source' => 'lead',
                ])
                ->values()
                ->all(),
        ];
    }

    private function serializeCampaign(Campaign $campaign, array $groupIds = []): array
    {
        $campaign->loadMissing(['template', 'results']);

        return [
            'id' => $campaign->id,
            'template_id' => $campaign->template_id,
            'template_name' => $campaign->template?->name,
            'subject' => $campaign->subject,
            'preheader' => $campaign->preheader,
            'headline' => $campaign->headline,
            'body' => $campaign->body,
            'button_text' => $campaign->button_text,
            'button_url' => $campaign->button_url,
            'status_id' => $campaign->campaign_status_id,
            'status' => $campaign->status,
            'status_label' => $campaign->campaignStatus?->label() ?? str($campaign->status)->replace('_', ' ')->title()->toString(),
            'sent_count' => $campaign->sent_count,
            'sent_at' => $campaign->sent_at?->format('Y-m-d H:i:s'),
            'sent_at_label' => DateFormatter::dateTime($campaign->sent_at),
            'customers_count' => $this->recipientsForGroups($groupIds)->count(),
            'recipients_count' => $campaign->results_count ?? $campaign->results->count(),
            'opened_count' => $campaign->results->whereNotNull('opened_at')->count(),
            'bounced_count' => $campaign->results->whereNotNull('bounced_at')->count(),
            'unsubscribed_count' => $campaign->results->whereNotNull('unsubscribed_at')->count(),
            'group_ids' => $groupIds,
            'recipients' => $campaign->results->map(fn (CampaignResult $result) => [
                'id' => $result->id,
                'name' => $result->name ?: $result->email,
                'email' => $result->email,
                'status' => $result->status,
                'sent_at_label' => DateFormatter::dateTime($result->sent_at),
                'opened_at_label' => DateFormatter::dateTime($result->opened_at),
                'bounced_at_label' => DateFormatter::dateTime($result->bounced_at),
                'unsubscribed_at_label' => DateFormatter::dateTime($result->unsubscribed_at),
                'bounce_url' => route('campaigns.results.bounce', $result),
            ])->values()->all(),
            'created_at_label' => DateFormatter::date($campaign->created_at),
            'show_url' => route('campaigns.show', $campaign),
            'update_url' => route('campaigns.update', $campaign),
            'send_url' => route('campaigns.send', $campaign),
            'delete_url' => route('campaigns.destroy', $campaign),
        ];
    }

    private function serializeTemplate(Template $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'subject' => $template->subject,
            'preheader' => $template->preheader,
            'headline' => $template->headline,
            'html_body' => $template->html_body,
            'button_text' => $template->button_text,
            'button_url' => $template->button_url,
            'update_url' => route('campaigns.templates.update', $template),
        ];
    }

    private function serializeGroup(SubscriberGroup $group): array
    {
        $group->loadMissing('recipients.recipient');

        return [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'customers_count' => $group->recipients_count ?? $group->recipients->count(),
            'recipients_count' => $group->recipients_count ?? $group->recipients->count(),
            'customers' => $group->recipients->map(fn (CampaignRecipient $recipient) => [
                'id' => $recipient->id,
                'full_name' => $this->recipientName($recipient),
                'email' => $this->recipientEmail($recipient),
                'phone' => $this->recipientPhone($recipient),
                'source' => $this->recipientSource($recipient),
            ])->values()->all(),
            'recipients' => $group->recipients->map(fn (CampaignRecipient $recipient) => [
                'id' => $recipient->id,
                'full_name' => $this->recipientName($recipient),
                'email' => $this->recipientEmail($recipient),
                'phone' => $this->recipientPhone($recipient),
                'source' => $this->recipientSource($recipient),
            ])->values()->all(),
            'update_url' => route('campaigns.groups.update', $group),
            'import_url' => route('campaigns.groups.import', $group),
            'recipient_store_url' => route('campaigns.groups.recipients.store', $group),
        ];
    }

    private function recipientEmail(CampaignRecipient $recipient): ?string
    {
        return match (true) {
            $recipient->recipient instanceof Customer => $recipient->recipient->email,
            $recipient->recipient instanceof Lead => $recipient->recipient->customer_email,
            default => null,
        };
    }

    private function recipientName(CampaignRecipient $recipient): ?string
    {
        return match (true) {
            $recipient->recipient instanceof Customer => $recipient->recipient->full_name,
            $recipient->recipient instanceof Lead => $recipient->recipient->customer_name,
            default => null,
        };
    }

    private function recipientPhone(CampaignRecipient $recipient): ?string
    {
        return match (true) {
            $recipient->recipient instanceof Customer => $recipient->recipient->phone,
            $recipient->recipient instanceof Lead => $recipient->recipient->customer_phone,
            default => null,
        };
    }

    private function recipientSource(CampaignRecipient $recipient): string
    {
        return match (true) {
            $recipient->recipient instanceof Lead => 'lead',
            default => 'customer',
        };
    }

    private function deleteRecipientsByEmail(string $email): void
    {
        CampaignRecipient::query()
            ->with('recipient')
            ->get()
            ->filter(fn (CampaignRecipient $recipient) => Str::lower((string) $this->recipientEmail($recipient)) === Str::lower($email))
            ->each(fn (CampaignRecipient $recipient) => $recipient->delete());
    }

    private function serializeTenant(?Tenant $tenant): ?array
    {
        if ($tenant === null) {
            return null;
        }

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'logo_url' => $tenant->logo_path ? '/storage/'.ltrim($tenant->logo_path, '/') : null,
            'theme' => $tenant->theme ?: 'dark',
            'contact_email' => $tenant->contact_email,
            'contact_phone' => $tenant->contact_phone,
            'address' => $tenant->address,
            'invoice_deposit_percentage' => number_format((float) ($tenant->invoice_deposit_percentage ?? config('invoicing.deposit_percentage', 30)), 2, '.', ''),
            'travel_free_kilometers' => number_format((float) ($tenant->travel_free_kilometers ?? config('pricing.travel_free_kilometers', 0)), 2, '.', ''),
            'travel_fee_per_kilometer' => number_format((float) ($tenant->travel_fee_per_kilometer ?? config('pricing.travel_fee_per_kilometer', 0)), 2, '.', ''),
            'google_maps_api_key' => env('VITE_GOOGLE_MAPS_API_KEY', ''),
            'quote_prefix' => $tenant->quote_prefix ?? 'QT',
            'invoice_prefix' => $tenant->invoice_prefix ?? 'INV',
            'customer_package_discount_percentage' => number_format((float) ($tenant->customer_package_discount_percentage ?? 0), 2, '.', ''),
        ];
    }

    private function campaignRoutes(): array
    {
        return [
            'create' => route('campaigns.create'),
            'campaigns' => route('campaigns.index'),
            'store' => route('campaigns.store'),
            'templateStore' => route('campaigns.templates.store'),
            'groupStore' => route('campaigns.groups.store'),
        ];
    }

    private function baseRoutes(): array
    {
        return [
            'dashboard' => route('dashboard'),
            'calendar' => route('admin.calendar.index'),
            'packages' => route('packages.index'),
            'equipment' => route('equipment.index'),
            'addons' => route('addons.index'),
            'discounts' => route('discounts.index'),
            'bookings' => route('admin.bookings.index'),
            'quotes' => route('admin.quotes.index'),
            'invoices' => route('admin.invoices.index'),
            'leads' => route('leads.index'),
            'customers' => route('customers.index'),
            'campaigns' => route('campaigns.index'),
            'tasks' => route('tasks.index'),
            'users' => route('users.index'),
            'roles' => route('roles.index'),
            'access' => route('access.index'),
            'settings' => route('settings.index'),
            'logout' => route('logout'),
        ];
    }

    private function authorizeResultTenant(CampaignResult $result): void
    {
        abort_unless($result->campaign?->tenant_id === app(CurrentTenant::class)->id(), 404);
    }
}
