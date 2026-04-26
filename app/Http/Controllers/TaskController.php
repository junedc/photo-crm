<?php

namespace App\Http\Controllers;

use App\Mail\TaskAssignedMail;
use App\Models\Booking;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Tenant;
use App\Support\DateFormatter;
use App\Support\TaskAssignees;
use App\Support\TenantStatuses;
use App\Support\TrackedEmailSender;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(CurrentTenant $currentTenant): View
    {
        $tenant = $this->requireTenant($currentTenant);

        return $this->renderAdminPage('tasks', [
            'tenant' => $this->serializeTenant($tenant),
            'routes' => [
                ...$this->baseRoutes(),
                'store' => route('tasks.store'),
                'tasks' => route('tasks.index'),
            ],
            'tasks' => Task::query()
                ->with(['assignedUser', 'assigneeVendor', 'assigneeCustomer', 'booking.customer', 'status', 'clientPortalUpdates'])
                ->latest('due_date')
                ->latest('created_at')
                ->get()
                ->map(fn (Task $task) => $this->serializeTask($task))
                ->values(),
            'assigneeOptions' => TaskAssignees::optionsForTenant($tenant)
                ->values(),
            'taskStatuses' => $this->taskStatuses($tenant)
                ->values(),
            'bookings' => Booking::query()
                ->with('customer')
                ->latest('event_date')
                ->latest('created_at')
                ->get()
                ->map(fn (Booking $booking) => [
                    'id' => $booking->id,
                    'display_name' => $booking->entry_name ?: $booking->customer_name,
                    'quote_number' => $booking->quote_number,
                    'event_date_label' => DateFormatter::date($booking->event_date),
                    'customer_assignee' => TaskAssignees::customerOption($tenant, $booking),
                ])
                ->values(),
        ]);
    }

    public function store(Request $request, CurrentTenant $currentTenant, TrackedEmailSender $trackedEmailSender): RedirectResponse|JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        $task = Task::query()->create($this->validateTask($request, $tenant));
        $task->load(['assignedUser', 'assigneeVendor', 'assigneeCustomer', 'booking.customer', 'status', 'clientPortalUpdates']);
        $this->notifyAssignee($trackedEmailSender, $tenant, $task);

        return $this->savedResponse($request, 'Task added.', $this->serializeTask($task), route('tasks.index'));
    }

    public function update(Request $request, CurrentTenant $currentTenant, Task $task, TrackedEmailSender $trackedEmailSender): RedirectResponse|JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        $this->assertTenantTask($tenant, $task);
        $validated = $this->validateTask($request, $tenant);
        $assigneeChanged = $task->assignee_type !== ($validated['assignee_type'] ?? null)
            || (string) $task->assignee_id !== (string) ($validated['assignee_id'] ?? '');

        if ($assigneeChanged) {
            $validated['notification_dismissed_at'] = null;
        }

        $task->update($validated);
        $task->load(['assignedUser', 'assigneeVendor', 'assigneeCustomer', 'booking.customer', 'status', 'clientPortalUpdates']);
        $this->notifyAssignee($trackedEmailSender, $tenant, $task);

        return $this->savedResponse($request, 'Task updated.', $this->serializeTask($task), route('tasks.index'));
    }

    public function destroy(Request $request, CurrentTenant $currentTenant, Task $task): RedirectResponse|JsonResponse
    {
        $this->assertTenantTask($this->requireTenant($currentTenant), $task);
        $task->delete();

        return $this->deletedResponse($request, 'Task deleted.', route('tasks.index'));
    }

    public function dismissNotification(Request $request, CurrentTenant $currentTenant, Task $task): JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        $this->assertTenantTask($tenant, $task);

        abort_unless(
            $task->assignee_type === Task::ASSIGNEE_USER && $task->assignee_id === $request->user()?->id,
            403
        );

        $task->forceFill([
            'notification_dismissed_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Task removed from notifications.',
        ]);
    }

    public function notifications(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        $user = $request->user();

        abort_unless($user !== null, 401);

        $notifications = Task::query()
            ->with(['booking', 'status'])
            ->where('tenant_id', $tenant->id)
            ->where('assignee_type', Task::ASSIGNEE_USER)
            ->where('assignee_id', $user->id)
            ->whereNull('notification_dismissed_at')
            ->orderByRaw('case when due_date is null then 1 else 0 end')
            ->orderBy('due_date')
            ->latest('created_at')
            ->get()
            ->map(fn (Task $task) => $this->serializeNotification($task))
            ->values();

        return response()->json([
            'notifications' => $notifications,
        ]);
    }

    private function validateTask(Request $request, Tenant $tenant): array
    {
        $bookingIds = Booking::query()->pluck('id');
        $taskStatuses = $this->taskStatuses($tenant);
        $statusIds = $taskStatuses->pluck('id');
        $validated = $request->validate([
            'task_name' => ['required', 'string', 'max:255'],
            'task_duration_hours' => ['nullable', 'numeric', 'min:0'],
            'assigned_to' => ['nullable', 'string', 'max:40'],
            'booking_id' => [
                'nullable',
                'integer',
                Rule::exists('bookings', 'id')->where(fn ($query) => $query->whereIn('id', $bookingIds)),
            ],
            'task_status_id' => [
                'nullable',
                'integer',
                Rule::exists('task_statuses', 'id')->where(fn ($query) => $query->whereIn('id', $statusIds)),
            ],
            'due_date' => ['nullable', 'date'],
            'date_started' => ['nullable', 'date'],
            'date_completed' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        $booking = ! empty($validated['booking_id'])
            ? Booking::query()->with('customer')->find($validated['booking_id'])
            : null;

        if (! empty($validated['assigned_to'])) {
            $parsedAssignee = TaskAssignees::parse($validated['assigned_to']);

            if (! $parsedAssignee || ! TaskAssignees::matchesTenant($tenant, $booking, $parsedAssignee['type'], $parsedAssignee['id'])) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'The selected task assignee is not valid for this tenant.',
                ]);
            }

            $validated['assignee_type'] = $parsedAssignee['type'];
            $validated['assignee_id'] = $parsedAssignee['id'];
        } else {
            $validated['assignee_type'] = null;
            $validated['assignee_id'] = null;
        }

        unset($validated['assigned_to']);

        if (blank($validated['task_status_id'] ?? null)) {
            $validated['task_status_id'] = $taskStatuses->firstWhere('name', 'new')['id'] ?? null;
        }

        return $validated;
    }

    private function serializeTask(Task $task): array
    {
        $latestPortalUpdate = $task->clientPortalUpdates->first();

        return [
            'id' => $task->id,
            'task_name' => $task->task_name,
            'task_duration_hours' => $task->task_duration_hours,
            'assigned_to' => $task->assignee_type && $task->assignee_id ? TaskAssignees::value($task->assignee_type, $task->assignee_id) : '',
            'assigned_to_name' => TaskAssignees::labelForTask($task),
            'assignee_type' => $task->assignee_type,
            'booking_id' => $task->booking_id,
            'booking_label' => $task->booking?->quote_number
                ? sprintf('%s - %s', $task->booking->quote_number, $task->booking->entry_name ?: $task->booking->customer_name)
                : ($task->booking?->entry_name ?: $task->booking?->customer_name),
            'task_status_id' => $task->task_status_id,
            'status_name' => $task->status?->name ?? '',
            'status_label' => $task->status?->label() ?? '',
            'due_date' => DateFormatter::inputDate($task->due_date) ?? '',
            'due_date_label' => DateFormatter::date($task->due_date, 'Not set'),
            'date_started' => DateFormatter::inputDate($task->date_started) ?? '',
            'date_started_label' => DateFormatter::date($task->date_started, 'Not set'),
            'date_completed' => DateFormatter::inputDate($task->date_completed) ?? '',
            'date_completed_label' => DateFormatter::date($task->date_completed, 'Not set'),
            'remarks' => $task->remarks ?? '',
            'customer_response_note' => $latestPortalUpdate?->note ?? '',
            'customer_response_at_label' => DateFormatter::dateTime($latestPortalUpdate?->created_at, 'No reply yet'),
            'customer_response_attachments' => collect($latestPortalUpdate?->attachments ?? [])
                ->map(fn ($attachment) => [
                    'name' => $attachment['name'] ?? 'Attachment',
                    'url' => $attachment['url'] ?? null,
                ])
                ->filter(fn (array $attachment) => filled($attachment['url']))
                ->values()
                ->all(),
            'customer_response_count' => $task->clientPortalUpdates->count(),
            'created_at' => DateFormatter::date($task->created_at),
            'update_url' => route('tasks.update', $task),
            'delete_url' => route('tasks.destroy', $task),
            'dismiss_notification_url' => route('tasks.notifications.dismiss', $task),
        ];
    }

    private function serializeNotification(Task $task): array
    {
        $booking = $task->booking;
        $bookingLabel = $booking?->quote_number
            ? sprintf('%s - %s', $booking->quote_number, $booking->entry_name ?: $booking->customer_name)
            : ($booking?->entry_name ?: $booking?->customer_name);

        return [
            'id' => $task->id,
            'title' => $task->task_name,
            'status' => $task->status?->name ?: 'Open',
            'due_date_label' => DateFormatter::date($task->due_date, 'No due date'),
            'booking_label' => $bookingLabel,
            'task_url' => route('tasks.index'),
            'booking_url' => $booking ? route('admin.bookings.show', $booking) : null,
            'dismiss_url' => route('tasks.notifications.dismiss', $task),
        ];
    }

    private function taskStatuses(Tenant $tenant)
    {
        return TenantStatuses::ensureTaskRecords($tenant)
            ->map(fn (TaskStatus $status) => [
                'id' => $status->id,
                'name' => $status->name,
                'label' => $status->label(),
                'sort_order' => (int) ($status->sort_order ?? 0),
            ]);
    }

    private function notifyAssignee(TrackedEmailSender $trackedEmailSender, Tenant $tenant, Task $task): void
    {
        $recipient = match ($task->assignee_type) {
            Task::ASSIGNEE_USER => $task->assignedUser ? [
                'email' => $task->assignedUser->email,
                'name' => $task->assignedUser->name,
            ] : null,
            Task::ASSIGNEE_VENDOR => $task->assigneeVendor ? [
                'email' => $task->assigneeVendor->email,
                'name' => $task->assigneeVendor->name,
            ] : null,
            Task::ASSIGNEE_CUSTOMER => $task->assigneeCustomer ? [
                'email' => $task->assigneeCustomer->email,
                'name' => $task->assigneeCustomer->full_name,
            ] : null,
            default => null,
        };

        if (! filled($recipient['email'] ?? null)) {
            return;
        }

        $trackedEmailSender->send(
            new TaskAssignedMail(
                $tenant,
                $task,
                (string) ($recipient['name'] ?? 'there'),
                $task->booking_id
                    ? route('admin.bookings.show', $task->booking_id)
                    : route('tasks.index', ['task' => $task->id]),
                $task->booking_id ? 'View booking' : 'View task',
            ),
            $recipient,
            [],
            [
                'tenant' => $tenant,
                'context' => $task,
            ],
        );
    }

    private function assertTenantTask(Tenant $tenant, Task $task): void
    {
        abort_unless($task->tenant_id === $tenant->id, 404);
    }

    private function requireTenant(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }

    private function serializeTenant(?Tenant $tenant): ?array
    {
        return $tenant ? [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'logo_url' => $tenant->logo_path ? '/storage/'.ltrim($tenant->logo_path, '/') : null,
            'theme' => $tenant->theme ?: 'dark',
            'google_maps_api_key' => env('VITE_GOOGLE_MAPS_API_KEY', ''),
        ] : null;
    }

    private function baseRoutes(): array
    {
        return [
            'login' => route('login'),
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
            'settings' => route('settings.index'),
            'logout' => route('logout'),
        ];
    }

    private function renderAdminPage(string $page, array $props): View
    {
        return view('admin.app', compact('page', 'props'));
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
