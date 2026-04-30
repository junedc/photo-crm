<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\Tenant;
use App\Models\TenantReferral;
use App\Support\DateFormatter;
use App\Support\TenantStatuses;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function index(CurrentTenant $currentTenant, Request $request): View
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        $tickets = SupportTicket::query()
            ->with('user')
            ->withCount('replies')
            ->latest()
            ->get();

        return view('admin.app', [
            'page' => 'support',
            'props' => [
                'tenant' => $this->serializeTenant($tenant),
                'routes' => $this->baseRoutes(),
                'tickets' => $tickets->map(fn (SupportTicket $ticket): array => $this->serializeTicket($ticket))->values()->all(),
                'ticketTypes' => SupportTicket::types(),
                'ticketPriorities' => SupportTicket::priorities(),
                'ticketStatuses' => TenantStatuses::names($tenant, TenantStatuses::SCOPE_SUPPORT),
                'tenantTicketStatuses' => $this->supportStatusOptions($tenant, onlyTenantActions: true),
            ],
        ]);
    }

    public function show(CurrentTenant $currentTenant, SupportTicket $ticket): View
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        $ticket->load(['user', 'replies.user']);

        return view('admin.app', [
            'page' => 'support-detail',
            'props' => [
                'tenant' => $this->serializeTenant($tenant),
                'routes' => $this->baseRoutes(),
                'ticket' => $this->serializeTicket($ticket, includeReplies: true),
                'tenantTicketStatuses' => $this->supportStatusOptions($tenant, onlyTenantActions: true),
            ],
        ]);
    }

    public function referrals(CurrentTenant $currentTenant): View
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        $this->ensureReferralCode($tenant);

        $referrals = TenantReferral::query()
            ->with('referredTenant')
            ->where('referrer_tenant_id', $tenant->id)
            ->latest()
            ->get();

        return view('admin.app', [
            'page' => 'referrals',
            'props' => [
                'tenant' => $this->serializeTenant($tenant->fresh()),
                'routes' => $this->baseRoutes(),
                'referral' => [
                    'code' => $tenant->referral_code,
                    'url' => rtrim((string) config('app.url'), '/').'/?ref='.$tenant->referral_code,
                    'count' => $referrals->count(),
                    'qualified_count' => $referrals->where('status', TenantReferral::STATUS_QUALIFIED)->count(),
                    'rewarded_count' => $referrals->where('status', TenantReferral::STATUS_REWARDED)->count(),
                    'referred' => $referrals->map(fn (TenantReferral $referral): array => $this->serializeReferral($referral))->values()->all(),
                ],
            ],
        ]);
    }

    public function storeTicket(CurrentTenant $currentTenant, Request $request): RedirectResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(SupportTicket::types()))],
            'priority' => ['required', Rule::in(array_keys(SupportTicket::priorities()))],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        SupportTicket::query()->create([
            ...$validated,
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()?->id,
            'ticket_number' => $this->newTicketNumber(),
            'support_status_id' => TenantStatuses::firstOrCreateWorkspaceStatus($tenant, TenantStatuses::SCOPE_SUPPORT, SupportTicket::STATUS_OPEN)?->id,
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        return redirect()->route('support.index')->with('status', 'Support ticket submitted.');
    }

    public function updateStatus(CurrentTenant $currentTenant, SupportTicket $ticket, Request $request): RedirectResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant && $ticket->tenant_id === $tenant->id, 404);

        $validated = $request->validate([
            'support_status_id' => ['required', Rule::exists('workspace_statuses', 'id')->where(fn ($query) => $query
                ->where('tenant_id', $tenant->id)
                ->where('scope', TenantStatuses::SCOPE_SUPPORT))],
        ]);

        $status = TenantStatuses::findWorkspaceStatusById($tenant, TenantStatuses::SCOPE_SUPPORT, $validated['support_status_id']);

        $ticket->forceFill([
            'support_status_id' => $status?->id,
            'status' => $status?->name ?? $ticket->status,
            'resolved_at' => ($status?->name ?? '') === SupportTicket::STATUS_RESOLVED ? now() : null,
        ])->save();

        return back()->with('status', 'Support ticket status updated.');
    }

    public function storeReply(CurrentTenant $currentTenant, SupportTicket $ticket, Request $request): RedirectResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        SupportTicketReply::query()->create([
            'tenant_id' => $tenant->id,
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()?->id,
            'message' => $validated['message'],
        ]);

        if ($ticket->status === SupportTicket::STATUS_RESOLVED) {
            $inProgress = TenantStatuses::firstOrCreateWorkspaceStatus($tenant, TenantStatuses::SCOPE_SUPPORT, SupportTicket::STATUS_IN_PROGRESS);
            $ticket->forceFill([
                'support_status_id' => $inProgress?->id,
                'status' => $inProgress?->name ?? SupportTicket::STATUS_IN_PROGRESS,
                'resolved_at' => null,
            ])->save();
        }

        return redirect()->route('support.show', $ticket)->with('status', 'Reply added.');
    }

    private function ensureReferralCode(Tenant $tenant): void
    {
        if (filled($tenant->referral_code)) {
            return;
        }

        do {
            $code = Str::upper(Str::random(8));
        } while (Tenant::query()->where('referral_code', $code)->exists());

        $tenant->forceFill(['referral_code' => $code])->save();
    }

    private function newTicketNumber(): string
    {
        do {
            $number = 'TCK-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (SupportTicket::query()->withoutGlobalScopes()->where('ticket_number', $number)->exists());

        return $number;
    }

    private function serializeTicket(SupportTicket $ticket, bool $includeReplies = false): array
    {
        $data = [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'type' => $ticket->type,
            'type_label' => SupportTicket::types()[$ticket->type] ?? Str::headline($ticket->type),
            'priority' => $ticket->priority,
            'priority_label' => SupportTicket::priorities()[$ticket->priority] ?? Str::headline($ticket->priority),
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'status_id' => $ticket->support_status_id,
            'status' => $ticket->status,
            'status_label' => $ticket->supportStatus?->label() ?? SupportTicket::statuses()[$ticket->status] ?? Str::headline($ticket->status),
            'created_by' => $ticket->user?->name,
            'created_at' => DateFormatter::dateTime($ticket->created_at),
            'updated_at' => DateFormatter::dateTime($ticket->updated_at),
            'resolved_at' => DateFormatter::dateTime($ticket->resolved_at),
            'replies_count' => $ticket->replies_count ?? $ticket->replies->count(),
            'show_url' => route('support.show', $ticket),
            'status_update_url' => route('support.status.update', $ticket),
            'reply_store_url' => route('support.replies.store', $ticket),
        ];

        if ($includeReplies) {
            $data['replies'] = $ticket->replies
                ->sortBy('created_at')
                ->map(fn (SupportTicketReply $reply): array => $this->serializeReply($reply))
                ->values()
                ->all();
        }

        return $data;
    }

    private function serializeReply(SupportTicketReply $reply): array
    {
        return [
            'id' => $reply->id,
            'message' => $reply->message,
            'created_by' => $reply->user?->name ?? 'Workspace user',
            'created_at' => DateFormatter::dateTime($reply->created_at),
        ];
    }

    private function serializeReferral(TenantReferral $referral): array
    {
        return [
            'id' => $referral->id,
            'workspace_name' => $referral->referredTenant?->name ?? $referral->referred_workspace_name,
            'owner_email' => $referral->referred_owner_email,
            'status_id' => $referral->referral_status_id,
            'status' => $referral->status,
            'status_label' => $referral->referralStatus?->label() ?? TenantReferral::statuses()[$referral->status] ?? Str::headline($referral->status),
            'created_at' => DateFormatter::date($referral->created_at),
        ];
    }

    private function supportStatusOptions(Tenant $tenant, bool $onlyTenantActions = false): array
    {
        $records = TenantStatuses::ensureWorkspaceRecords($tenant, TenantStatuses::SCOPE_SUPPORT);

        if ($onlyTenantActions) {
            $records = $records->filter(fn ($status) => in_array($status->name, [
                SupportTicket::STATUS_IN_PROGRESS,
                SupportTicket::STATUS_RESOLVED,
            ], true));
        }

        return $records
            ->mapWithKeys(fn ($status) => [$status->id => $status->label()])
            ->all();
    }

    private function serializeTenant(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'logo_url' => $tenant->logo_path ? '/storage/'.ltrim($tenant->logo_path, '/') : null,
            'google_maps_api_key' => env('VITE_GOOGLE_MAPS_API_KEY', ''),
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
            'support' => route('support.index'),
            'referrals' => route('referrals.index'),
            'supportTicketsStore' => route('support.tickets.store'),
            'settings' => route('settings.index'),
            'logout' => route('logout'),
        ];
    }
}
