<?php

namespace App\Support;

use App\Models\Task;
use App\Models\Tenant;
use App\Models\TenantNotification;
use App\Models\User;
use Illuminate\Support\Collection;

class AdminNotificationFeed
{
    public function forUser(Tenant $tenant, User $user): Collection
    {
        $taskNotifications = Task::query()
            ->with(['booking', 'status'])
            ->where('tenant_id', $tenant->id)
            ->where('assignee_type', Task::ASSIGNEE_USER)
            ->where('assignee_id', $user->id)
            ->whereNull('notification_dismissed_at')
            ->orderByRaw('case when due_date is null then 1 else 0 end')
            ->orderBy('due_date')
            ->latest('created_at')
            ->get()
            ->map(fn (Task $task) => $this->serializeTaskNotification($task));

        $activityNotifications = TenantNotification::query()
            ->with(['booking', 'task'])
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->latest('created_at')
            ->get()
            ->map(fn (TenantNotification $notification) => $this->serializeActivityNotification($notification));

        return $taskNotifications
            ->concat($activityNotifications)
            ->sortByDesc(fn (array $notification) => $notification['sort_at'] ?? '')
            ->values()
            ->map(function (array $notification): array {
                unset($notification['sort_at']);

                return $notification;
            });
    }

    private function serializeTaskNotification(Task $task): array
    {
        $booking = $task->booking;
        $bookingLabel = $booking?->quote_number
            ? sprintf('%s - %s', $booking->quote_number, $booking->entry_name ?: $booking->customer_name)
            : ($booking?->entry_name ?: $booking?->customer_name);

        return [
            'id' => 'task-'.$task->id,
            'kind' => 'task_assignment',
            'title' => $task->task_name,
            'message' => 'Assigned to you.',
            'status' => $task->status?->name ?: 'Open',
            'created_at_label' => DateFormatter::dateTime($task->created_at, 'Just now'),
            'due_date_label' => DateFormatter::date($task->due_date, 'No due date'),
            'booking_label' => $bookingLabel,
            'task_url' => route('tasks.index'),
            'booking_url' => $booking ? route('admin.bookings.show', $booking) : null,
            'dismiss_url' => route('tasks.notifications.dismiss', $task),
            'sort_at' => optional($task->created_at)->toIso8601String(),
        ];
    }

    private function serializeActivityNotification(TenantNotification $notification): array
    {
        $booking = $notification->booking;
        $bookingLabel = $booking?->quote_number
            ? sprintf('%s - %s', $booking->quote_number, $booking->entry_name ?: $booking->customer_name)
            : ($booking?->entry_name ?: $booking?->customer_name);

        return [
            'id' => 'activity-'.$notification->id,
            'kind' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'status' => 'Client Portal',
            'created_at_label' => DateFormatter::dateTime($notification->created_at, 'Just now'),
            'due_date_label' => null,
            'booking_label' => $bookingLabel,
            'task_url' => $notification->task_id ? route('tasks.index', ['task' => $notification->task_id]) : route('tasks.index'),
            'booking_url' => $booking ? route('admin.bookings.show', $booking) : null,
            'dismiss_url' => route('notifications.dismiss', $notification),
            'sort_at' => optional($notification->created_at)->toIso8601String(),
        ];
    }
}
