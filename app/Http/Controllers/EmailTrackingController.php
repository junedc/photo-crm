<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\Tenant;
use App\Support\DateFormatter;
use App\Support\TrackedEmailSender;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmailTrackingController extends Controller
{
    public function index(CurrentTenant $currentTenant): View
    {
        $tenant = $this->requireTenant($currentTenant);
        $logs = EmailLog::query()
            ->with('emailTrackingStatus')
            ->where('tenant_id', $tenant->id)
            ->latest('created_at')
            ->get();

        return view('admin.app', [
            'page' => 'email-tracking',
            'props' => [
                'tenant' => $this->serializeTenant($tenant),
                'routes' => [
                    ...$this->baseRoutes(),
                    'emailTracking' => route('email-tracking.index', [], false),
                    'bulkDelete' => route('email-tracking.bulk-destroy', [], false),
                ],
                'emailLogs' => $logs->map(fn (EmailLog $log) => $this->serializeLog($log))->values()->all(),
            ],
        ]);
    }

    public function resend(CurrentTenant $currentTenant, EmailLog $emailLog, TrackedEmailSender $trackedEmailSender): JsonResponse|RedirectResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        abort_unless($emailLog->tenant_id === $tenant->id, 404);

        $resentLog = $trackedEmailSender->resendLog($emailLog);

        if ($resentLog->status !== 'sent') {
            return response()->json([
                'message' => 'Email resend failed.',
                'record' => $this->serializeLog($resentLog),
            ], 422);
        }

        return $this->savedResponse(request(), 'Email resent.', $this->serializeLog($resentLog), route('email-tracking.index', [], false));
    }

    public function bulkDestroy(CurrentTenant $currentTenant, Request $request): JsonResponse|RedirectResponse
    {
        $tenant = $this->requireTenant($currentTenant);

        $data = $request->validate([
            'email_log_ids' => ['required', 'array', 'min:1'],
            'email_log_ids.*' => ['integer', Rule::exists('email_logs', 'id')->where(fn ($query) => $query->where('tenant_id', $tenant->id))],
        ]);

        EmailLog::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $data['email_log_ids'])
            ->delete();

        return $this->deletedResponse($request, 'Selected emails deleted.', route('email-tracking.index', [], false));
    }

    private function serializeLog(EmailLog $log): array
    {
        $trackingType = TrackedEmailSender::trackingTitle($log->mailable_class, $log->subject);
        $trackingCode = TrackedEmailSender::trackingCode($log->mailable_class, $trackingType);

        return [
            'id' => $log->id,
            'title' => $trackingType,
            'type' => $trackingCode,
            'type_label' => $trackingType,
            'recipient_email' => $log->recipient_email,
            'recipient_name' => $log->recipient_name,
            'recipient_label' => $log->recipient_name
                ? sprintf('%s <%s>', $log->recipient_name, $log->recipient_email)
                : $log->recipient_email,
            'recipient_type' => $log->recipient_type,
            'subject' => $log->subject,
            'html_content' => $log->html_content,
            'attachments' => collect($log->attachments ?? [])
                ->map(fn (array $attachment) => [
                    'name' => $attachment['name'] ?? 'Attachment',
                    'mime' => $attachment['mime'] ?? 'application/octet-stream',
                ])
                ->values()
                ->all(),
            'status_id' => $log->email_tracking_status_id,
            'status' => $log->status,
            'status_label' => $log->emailTrackingStatus?->label() ?? str($log->status)->replace('_', ' ')->title()->toString(),
            'error_message' => $log->error_message,
            'sent_at_sort' => $log->sent_at?->timestamp ?? 0,
            'sent_at_label' => DateFormatter::dateTime($log->sent_at, 'Not sent'),
            'mailable_class' => $log->mailable_class,
            'resend_url' => route('email-tracking.resend', $log, false),
        ];
    }

    private function requireTenant(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }

    private function serializeTenant(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'logo_url' => $tenant->logo_path ? '/storage/'.ltrim($tenant->logo_path, '/') : null,
            'theme' => $tenant->theme ?: 'dark',
            'google_maps_api_key' => env('VITE_GOOGLE_MAPS_API_KEY', ''),
        ];
    }

    private function baseRoutes(): array
    {
        return [
            'login' => route('login', [], false),
            'dashboard' => route('dashboard', [], false),
            'calendar' => route('admin.calendar.index', [], false),
            'packages' => route('packages.index', [], false),
            'equipment' => route('equipment.index', [], false),
            'addons' => route('addons.index', [], false),
            'discounts' => route('discounts.index', [], false),
            'bookings' => route('admin.bookings.index', [], false),
            'quotes' => route('admin.quotes.index', [], false),
            'invoices' => route('admin.invoices.index', [], false),
            'leads' => route('leads.index', [], false),
            'customers' => route('customers.index', [], false),
            'campaigns' => route('campaigns.index', [], false),
            'tasks' => route('tasks.index', [], false),
            'users' => route('users.index', [], false),
            'roles' => route('roles.index', [], false),
            'access' => route('access.index', [], false),
            'settings' => route('settings.index', [], false),
            'logout' => route('logout', [], false),
        ];
    }

    private function savedResponse(Request $request, string $message, array $record, string $redirectTo): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'record' => $record]);
        }

        return redirect($redirectTo)->with('status', $message);
    }

    private function deletedResponse(Request $request, string $message, string $redirectTo): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect($redirectTo)->with('status', $message);
    }
}
