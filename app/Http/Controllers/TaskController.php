<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantStatuses;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
                ->with(['assignedUser', 'booking', 'status'])
                ->latest('due_date')
                ->latest('created_at')
                ->get()
                ->map(fn (Task $task) => $this->serializeTask($task))
                ->values(),
            'users' => $tenant->users()
                ->wherePivot('role', '!=', 'guest')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->values(),
            'taskStatuses' => TenantStatuses::records($tenant, TenantStatuses::SCOPE_TASK)
                ->map(fn (array $status) => [
                    'id' => $status['id'],
                    'name' => $status['name'],
                ])
                ->values(),
            'bookings' => Booking::query()
                ->latest('event_date')
                ->latest('created_at')
                ->get()
                ->map(fn (Booking $booking) => [
                    'id' => $booking->id,
                    'display_name' => $booking->entry_name ?: $booking->customer_name,
                    'quote_number' => $booking->quote_number,
                    'event_date_label' => $booking->event_date?->format('d M Y'),
                ])
                ->values(),
        ]);
    }

    public function store(Request $request, CurrentTenant $currentTenant): RedirectResponse|JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        $task = Task::query()->create($this->validateTask($request, $tenant));

        return $this->savedResponse($request, 'Task added.', $this->serializeTask($task->fresh(['assignedUser', 'booking', 'status'])), route('tasks.index'));
    }

    public function update(Request $request, CurrentTenant $currentTenant, Task $task): RedirectResponse|JsonResponse
    {
        $tenant = $this->requireTenant($currentTenant);
        $this->assertTenantTask($tenant, $task);
        $task->update($this->validateTask($request, $tenant));

        return $this->savedResponse($request, 'Task updated.', $this->serializeTask($task->fresh(['assignedUser', 'booking', 'status'])), route('tasks.index'));
    }

    public function destroy(Request $request, CurrentTenant $currentTenant, Task $task): RedirectResponse|JsonResponse
    {
        $this->assertTenantTask($this->requireTenant($currentTenant), $task);
        $task->delete();

        return $this->deletedResponse($request, 'Task deleted.', route('tasks.index'));
    }

    private function validateTask(Request $request, Tenant $tenant): array
    {
        $bookingIds = Booking::query()->pluck('id');
        $statusIds = $tenant->taskStatuses()->pluck('id');
        $validated = $request->validate([
            'task_name' => ['required', 'string', 'max:255'],
            'task_duration_hours' => ['nullable', 'numeric', 'min:0'],
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('id', $tenant->users()->pluck('users.id'))),
            ],
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
            'status_name' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'date_started' => ['nullable', 'date'],
            'date_completed' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        $statusName = trim((string) ($validated['status_name'] ?? ''));

        if (! empty($validated['task_status_id'])) {
            $validated['task_status_id'] = (int) $validated['task_status_id'];
        } elseif ($statusName !== '') {
            $validated['task_status_id'] = $tenant->taskStatuses()->firstOrCreate([
                'name' => $statusName,
            ])->id;
        }

        unset($validated['status_name']);

        return $validated;
    }

    private function serializeTask(Task $task): array
    {
        return [
            'id' => $task->id,
            'task_name' => $task->task_name,
            'task_duration_hours' => $task->task_duration_hours,
            'assigned_to' => $task->assigned_to,
            'assigned_to_name' => $task->assignedUser?->name ?? 'Unassigned',
            'booking_id' => $task->booking_id,
            'booking_label' => $task->booking?->quote_number
                ? sprintf('%s - %s', $task->booking->quote_number, $task->booking->entry_name ?: $task->booking->customer_name)
                : ($task->booking?->entry_name ?: $task->booking?->customer_name),
            'task_status_id' => $task->task_status_id,
            'status_name' => $task->status?->name ?? '',
            'due_date' => $task->due_date?->format('Y-m-d') ?? '',
            'due_date_label' => $task->due_date?->format('d M Y') ?? 'Not set',
            'date_started' => $task->date_started?->format('Y-m-d') ?? '',
            'date_started_label' => $task->date_started?->format('d M Y') ?? 'Not set',
            'date_completed' => $task->date_completed?->format('Y-m-d') ?? '',
            'date_completed_label' => $task->date_completed?->format('d M Y') ?? 'Not set',
            'remarks' => $task->remarks ?? '',
            'created_at' => $task->created_at?->format('d M Y'),
            'update_url' => route('tasks.update', $task),
            'delete_url' => route('tasks.destroy', $task),
        ];
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
