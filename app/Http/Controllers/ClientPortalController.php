<?php

namespace App\Http\Controllers;

use App\Mail\TaskResponseReceivedMail;
use App\Models\Booking;
use App\Models\ClientPortalCode;
use App\Models\ClientPortalDesign;
use App\Models\ClientPortalTaskUpdate;
use App\Models\Tenant;
use App\Models\TenantNotification;
use App\Models\TenantFont;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Services\ClientPortalService;
use App\Support\DateFormatter;
use App\Support\TenantStatuses;
use App\Support\TrackedEmailSender;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientPortalController extends Controller
{
    public function login(Request $request, CurrentTenant $currentTenant, ClientPortalService $clientPortalService): View|RedirectResponse
    {
        $tenant = $currentTenant->get();

        if (! $tenant) {
            abort(404);
        }

        if ($request->session()->has(ClientPortalService::AUTH_SESSION_KEY)) {
            return redirect()->route('client.portal.index');
        }

        $access = $clientPortalService->resolveGrantedAccess(
            $tenant,
            token: $request->query('access'),
        );

        return view('client-portal.login', [
            'tenant' => $tenant,
            'prefillEmail' => $access?->customer_email ?? old('email'),
            'accessToken' => $access?->invite_token ?? (string) $request->query('access', ''),
        ]);
    }

    public function sendCode(Request $request, CurrentTenant $currentTenant, ClientPortalService $clientPortalService): RedirectResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant, 404);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'access' => ['nullable', 'uuid'],
        ]);

        $access = $clientPortalService->resolveGrantedAccess(
            $tenant,
            email: strtolower($data['email']),
            token: $data['access'] ?? null,
        );

        if (! $access) {
            return back()
                ->withInput($request->only('email', 'access'))
                ->withErrors(['email' => 'This email does not have client portal access yet.']);
        }

        $verification = $clientPortalService->issueCode($access);

        $request->session()->put(ClientPortalService::ACCESS_SESSION_KEY, [
            'access_id' => $access->id,
            'verification_id' => $verification->id,
            'email' => $access->customer_email,
        ]);

        return redirect()
            ->route('client.portal.verify')
            ->with('status', 'We emailed a six-digit portal code to you.');
    }

    public function verify(Request $request, CurrentTenant $currentTenant): View|RedirectResponse
    {
        abort_unless($currentTenant->get(), 404);

        if (! $request->session()->has(ClientPortalService::ACCESS_SESSION_KEY)) {
            return redirect()->route('client.portal.login');
        }

        return view('client-portal.verify', [
            'tenant' => $currentTenant->get(),
        ]);
    }

    public function confirm(Request $request, CurrentTenant $currentTenant, ClientPortalService $clientPortalService): RedirectResponse
    {
        abort_unless($currentTenant->get(), 404);

        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $pendingAccess = $request->session()->get(ClientPortalService::ACCESS_SESSION_KEY);

        if (! $pendingAccess) {
            return redirect()->route('client.portal.login');
        }

        $verification = ClientPortalCode::query()
            ->with('access')
            ->whereKey($pendingAccess['verification_id'])
            ->where('client_portal_access_id', $pendingAccess['access_id'])
            ->where('email', $pendingAccess['email'])
            ->first();

        if (! $verification || $verification->consumed_at || $verification->expires_at->isPast()) {
            $request->session()->forget(ClientPortalService::ACCESS_SESSION_KEY);

            return redirect()->route('client.portal.login')
                ->withErrors(['email' => 'Your portal code expired. Request a new one to continue.']);
        }

        if ($verification->attempts >= 5) {
            return back()->withErrors(['code' => 'Too many attempts. Request a new portal code.']);
        }

        if (! $clientPortalService->codeMatches($verification, $data['code'])) {
            $verification->increment('attempts');

            return back()->withErrors(['code' => 'That code is not valid.']);
        }

        $verification->forceFill([
            'consumed_at' => now(),
        ])->save();

        $verification->access->forceFill([
            'last_verified_at' => now(),
        ])->save();

        $request->session()->forget(ClientPortalService::ACCESS_SESSION_KEY);
        $request->session()->put(ClientPortalService::AUTH_SESSION_KEY, [
            'access_id' => $verification->access->id,
            'email' => $verification->access->customer_email,
        ]);
        $request->session()->regenerate();

        return redirect()->route('client.portal.index');
    }

    public function resend(Request $request, CurrentTenant $currentTenant, ClientPortalService $clientPortalService): RedirectResponse
    {
        abort_unless($currentTenant->get(), 404);

        $pendingAccess = $request->session()->get(ClientPortalService::ACCESS_SESSION_KEY);

        if (! $pendingAccess) {
            return redirect()->route('client.portal.login');
        }

        $access = $clientPortalService->resolveGrantedAccess($currentTenant->get(), email: $pendingAccess['email']);
        abort_unless($access, 404);

        $verification = $clientPortalService->issueCode($access);

        $request->session()->put(ClientPortalService::ACCESS_SESSION_KEY, [
            'access_id' => $access->id,
            'verification_id' => $verification->id,
            'email' => $access->customer_email,
        ]);

        return back()->with('status', 'A fresh portal code is on its way.');
    }

    public function index(Request $request, CurrentTenant $currentTenant): View|RedirectResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant, 404);

        $auth = $request->session()->get(ClientPortalService::AUTH_SESSION_KEY);

        if (! $auth) {
            return redirect()->route('client.portal.login');
        }

        $email = strtolower((string) ($auth['email'] ?? ''));

        $bookings = Booking::query()
            ->with([
                'package',
                'invoice.installments',
                'addOns',
                'clientPortalDesign',
                'tasks' => fn ($query) => $query
                    ->where('assignee_type', Task::ASSIGNEE_CUSTOMER)
                    ->with([
                        'status',
                        'clientPortalUpdates',
                        'clientPortalUpdates.status',
                    ])
                    ->orderByRaw('case when due_date is null then 1 else 0 end')
                    ->orderBy('due_date')
                    ->orderBy('id'),
            ])
            ->where('customer_email', $email)
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get();

        $upcomingBookings = $bookings
            ->filter(fn (Booking $booking) => $booking->event_date === null || $booking->event_date->isToday() || $booking->event_date->isFuture())
            ->values();

        $pastBookings = $bookings
            ->filter(fn (Booking $booking) => $booking->event_date !== null && $booking->event_date->isPast() && ! $booking->event_date->isToday())
            ->values();

        return view('client-portal.index', [
            'tenant' => $tenant,
            'customerEmail' => $email,
            'customerName' => $bookings->first()?->customer_name,
            'upcomingBookings' => $upcomingBookings,
            'pastBookings' => $pastBookings,
        ]);
    }

    public function respondToTask(Request $request, CurrentTenant $currentTenant, Booking $booking, Task $task, TrackedEmailSender $trackedEmailSender): RedirectResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant && $booking->tenant_id === $tenant->id, 404);

        $auth = $this->clientAuth($request);
        abort_unless($auth && strtolower((string) $booking->customer_email) === strtolower((string) $auth['email']), 403);
        abort_unless(
            $task->tenant_id === $tenant->id
            && $task->booking_id === $booking->id
            && $task->assignee_type === Task::ASSIGNEE_CUSTOMER,
            404,
        );

        $data = $request->validate([
            'form_task_id' => ['required', 'integer'],
            'action' => ['required', Rule::in(['save_note', 'mark_in_progress', 'mark_completed'])],
            'note' => ['nullable', 'string', 'max:3000'],
            'attachments' => ['nullable', 'array', 'max:6'],
            'attachments.*' => [
                'file',
                'max:20480',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/heic,image/heif,video/mp4,video/quicktime,video/x-msvideo,video/webm,application/pdf',
            ],
        ], [
            'attachments.*.uploaded' => 'One of the selected files could not be uploaded. Please keep each file under 20 MB and try again.',
            'attachments.*.max' => 'Each attachment must be 20 MB or smaller.',
            'attachments.max' => 'You can upload up to 6 files at a time.',
        ]);

        abort_unless((int) $data['form_task_id'] === $task->id, 404);

        $note = trim((string) ($data['note'] ?? ''));
        $files = $request->file('attachments', []);

        if ($data['action'] === 'save_note' && $note === '' && $files === []) {
            return back()
                ->withInput()
                ->withErrors(['note' => 'Add a note or upload at least one file before saving an update.']);
        }

        $status = $task->status;

        if ($data['action'] === 'mark_in_progress') {
            $status = $this->resolveTaskStatus($tenant, 'in_progress') ?? $status;
            $task->forceFill([
                'task_status_id' => $status?->id,
                'date_started' => $task->date_started ?: now()->toDateString(),
                'date_completed' => null,
            ])->save();
        }

        if ($data['action'] === 'mark_completed') {
            $status = $this->resolveTaskStatus($tenant, 'completed') ?? $status;
            $task->forceFill([
                'task_status_id' => $status?->id,
                'date_started' => $task->date_started ?: now()->toDateString(),
                'date_completed' => now()->toDateString(),
            ])->save();
        }

        $attachments = collect($files)
            ->filter()
            ->map(function ($file) use ($tenant, $booking, $task): array {
                $path = $file->store("client-portal-task-updates/{$tenant->id}/{$booking->id}/{$task->id}", 'public');

                return [
                    'path' => $path,
                    'url' => $this->publicStorageUrl($path),
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize(),
                ];
            })
            ->values()
            ->all();

        ClientPortalTaskUpdate::query()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'task_id' => $task->id,
            'task_status_id' => $task->fresh('status')->task_status_id,
            'customer_email' => strtolower((string) $booking->customer_email),
            'action' => $data['action'],
            'note' => $note !== '' ? $note : null,
            'attachments' => $attachments === [] ? null : $attachments,
        ]);

        $this->createAdminNotificationsForTaskResponse($tenant, $booking, $task->fresh(['status']), $note, $attachments);
        $this->notifyTenantAdminsOfTaskResponse($trackedEmailSender, $tenant, $booking, $task->fresh(['status']), $note, $attachments);

        return redirect()
            ->route('client.portal.index')
            ->withFragment('task-'.$task->id)
            ->with('status', 'Task update saved.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget([
            ClientPortalService::ACCESS_SESSION_KEY,
            ClientPortalService::AUTH_SESSION_KEY,
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('client.portal.login');
    }

    public function design(Request $request, CurrentTenant $currentTenant, Booking $booking): View|RedirectResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant && $booking->tenant_id === $tenant->id, 404);

        $auth = $this->clientAuth($request);

        if (! $auth || strtolower((string) $booking->customer_email) !== strtolower((string) $auth['email'])) {
            return redirect()->route('client.portal.login');
        }

        $design = ClientPortalDesign::query()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'booking_id' => $booking->id,
            ],
            [
                'customer_email' => strtolower((string) $booking->customer_email),
                'title' => 'Client design draft',
                'design_data' => [
                    'width' => 400,
                    'height' => 1200,
                    'backgroundColor' => '#f8fafc',
                    'remarks' => '',
                    'nodes' => [],
                ],
                'status' => 'draft',
                'last_saved_at' => now(),
            ],
        );

        return view('client-portal.design', [
            'tenant' => $tenant,
            'booking' => $booking->loadMissing('package'),
            'design' => [
                'id' => $design->id,
                'title' => $design->title,
                'last_saved_at_label' => DateFormatter::dateTime($design->last_saved_at),
                'design_data' => $this->normalizeDesignData($design->design_data),
            ],
            'fonts' => $tenant->fonts()
                ->get()
                ->map(fn (TenantFont $font): array => $this->serializeTenantFont($font))
                ->values()
                ->all(),
        ]);
    }

    public function saveDesign(Request $request, CurrentTenant $currentTenant, Booking $booking): JsonResponse|RedirectResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant && $booking->tenant_id === $tenant->id, 404);

        $auth = $this->clientAuth($request);
        abort_unless($auth && strtolower((string) $booking->customer_email) === strtolower((string) $auth['email']), 403);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'design_data' => ['required', 'array'],
            'design_data.width' => ['required', 'numeric', 'min:320', 'max:4000'],
            'design_data.height' => ['required', 'numeric', 'min:320', 'max:4000'],
            'design_data.backgroundColor' => ['nullable', 'string', 'max:30'],
            'design_data.remarks' => ['nullable', 'string', 'max:2000'],
            'design_data.nodes' => ['required', 'array'],
        ]);

        $design = ClientPortalDesign::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'booking_id' => $booking->id,
            ],
            [
                'customer_email' => strtolower((string) $booking->customer_email),
                'title' => $data['title'] ?: 'Client design draft',
                'design_data' => $data['design_data'],
                'status' => 'draft',
                'last_saved_at' => now(),
            ],
        );

        return response()->json([
            'message' => 'Draft saved.',
            'record' => [
                'id' => $design->id,
                'title' => $design->title,
                'last_saved_at_label' => DateFormatter::dateTime($design->last_saved_at),
                'design_data' => $this->normalizeDesignData($design->design_data),
            ],
        ]);
    }

    public function uploadDesignAsset(Request $request, CurrentTenant $currentTenant, Booking $booking): JsonResponse
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant && $booking->tenant_id === $tenant->id, 404);

        $auth = $this->clientAuth($request);
        abort_unless($auth && strtolower((string) $booking->customer_email) === strtolower((string) $auth['email']), 403);

        $data = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        $path = $data['image']->store("client-portal-designs/{$tenant->id}/{$booking->id}", 'public');

        return response()->json([
            'message' => 'Image uploaded.',
            'record' => [
                'path' => $path,
                'url' => $this->publicStorageUrl($path),
                'name' => basename($path),
            ],
        ]);
    }

    private function clientAuth(Request $request): ?array
    {
        return $request->session()->get(ClientPortalService::AUTH_SESSION_KEY);
    }

    private function notifyTenantAdminsOfTaskResponse(
        TrackedEmailSender $trackedEmailSender,
        Tenant $tenant,
        Booking $booking,
        Task $task,
        string $note,
        array $attachments,
    ): void {
        $recipients = $tenant->users()
            ->wherePivot('role', 'owner')
            ->get()
            ->map(fn ($user) => [
                'email' => $user->email,
                'name' => $user->name,
            ])
            ->filter(fn (array $recipient) => filled($recipient['email']))
            ->values()
            ->all();

        if ($recipients === []) {
            return;
        }

        $trackedEmailSender->send(
            new TaskResponseReceivedMail(
                $tenant,
                $booking,
                $task,
                $note !== '' ? $note : null,
                $attachments,
                route('admin.bookings.show', $booking),
            ),
            $recipients,
            [],
            ['tenant' => $tenant, 'context' => $task]
        );
    }

    private function createAdminNotificationsForTaskResponse(
        Tenant $tenant,
        Booking $booking,
        Task $task,
        string $note,
        array $attachments,
    ): void {
        $attachmentCount = count($attachments);
        $hasNote = $note !== '';
        $displayName = $booking->customer_name ?: $booking->entry_name ?: 'Client';
        $messageParts = [];

        if ($hasNote) {
            $messageParts[] = $displayName.' replied to the task.';
        }

        if ($attachmentCount > 0) {
            $messageParts[] = $attachmentCount === 1
                ? '1 file was attached.'
                : $attachmentCount.' files were attached.';
        }

        if ($messageParts === []) {
            $messageParts[] = $displayName.' updated the task in the client portal.';
        }

        $title = $attachmentCount > 0 && $hasNote
            ? 'Client replied and uploaded files'
            : ($attachmentCount > 0 ? 'Client uploaded files' : 'Client replied to task');

        $recipients = $tenant->users()
            ->wherePivot('role', '!=', 'guest')
            ->get(['users.id']);

        foreach ($recipients as $recipient) {
            TenantNotification::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $recipient->id,
                'booking_id' => $booking->id,
                'task_id' => $task->id,
                'type' => 'client_portal_task_update',
                'title' => $title,
                'message' => implode(' ', $messageParts),
                'payload' => [
                    'task_name' => $task->task_name,
                    'customer_email' => $booking->customer_email,
                    'attachment_count' => $attachmentCount,
                    'has_note' => $hasNote,
                ],
            ]);
        }
    }

    private function normalizeDesignData(?array $designData): ?array
    {
        if (! is_array($designData)) {
            return $designData;
        }

        $designData['nodes'] = collect($designData['nodes'] ?? [])
            ->map(function ($node) {
                if (! is_array($node)) {
                    return $node;
                }

                if (isset($node['src']) && is_string($node['src'])) {
                    $node['src'] = $this->normalizeAssetUrl($node['src']);
                }

                return $node;
            })
            ->values()
            ->all();

        $designData['remarks'] = is_string($designData['remarks'] ?? null)
            ? $designData['remarks']
            : '';

        return $designData;
    }

    private function normalizeAssetUrl(string $url): string
    {
        if (str_starts_with($url, '/storage/')) {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && str_starts_with($path, '/storage/')
            ? $path
            : $url;
    }

    private function publicStorageUrl(string $path): string
    {
        return '/storage/'.ltrim($path, '/');
    }

    private function serializeTenantFont(TenantFont $font): array
    {
        return [
            'id' => $font->id,
            'family' => $font->family,
            'weight' => $font->weight,
            'style' => $font->style,
            'url' => $this->publicStorageUrl($font->file_path),
            'css_format' => $this->fontCssFormat($font->extension),
            'label' => trim($font->family.' '.$this->fontVariantLabel($font->weight, $font->style)),
        ];
    }

    private function fontVariantLabel(int $weight, string $style): string
    {
        return match (true) {
            $weight >= 700 && $style === 'italic' => 'Bold Italic',
            $weight >= 700 => 'Bold',
            $style === 'italic' => 'Italic',
            default => 'Regular',
        };
    }

    private function fontCssFormat(?string $extension): string
    {
        return match (strtolower((string) $extension)) {
            'woff2' => 'woff2',
            'woff' => 'woff',
            'ttf' => 'truetype',
            'otf' => 'opentype',
            default => 'woff2',
        };
    }

    private function resolveTaskStatus($tenant, string $name): ?TaskStatus
    {
        return $this->taskStatuses($tenant)->first(function (TaskStatus $status) use ($name): bool {
            $normalizedStatus = str_replace([' ', '-'], '_', strtolower(trim($status->name)));
            $normalizedName = str_replace([' ', '-'], '_', strtolower(trim($name)));

            return $normalizedStatus === $normalizedName;
        });
    }

    private function taskStatuses($tenant): Collection
    {
        return TenantStatuses::ensureTaskRecords($tenant);
    }
}
