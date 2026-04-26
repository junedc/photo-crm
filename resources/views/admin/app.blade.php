<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $props['tenant']['name'] ?? 'MemoShot' }} Admin</title>
        @if (!empty($props['tenant']['logo_url']))
            <link rel="icon" type="image/png" href="{{ url($props['tenant']['logo_url']) }}">
        @endif
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-stone-950 text-stone-50" data-theme="{{ $props['tenant']['theme'] ?? 'dark' }}">
        @php
            $allowedScreens = null;
            $tenantId = $props['tenant']['id'] ?? null;
            $user = auth()->user();
            $notifications = collect();

            if ($user !== null && $tenantId !== null) {
                $membership = $user->tenants()->whereKey($tenantId)->first()?->pivot;

                if ($membership !== null && $membership->role !== 'owner') {
                    if ($membership->role === 'guest') {
                        $allowedScreens = $membership->role_id
                            ? (\App\Models\Role::query()->find($membership->role_id)?->screen_access ?? [])
                            : [];
                    } elseif ($membership->role_id !== null) {
                        $allowedScreens = \App\Models\Role::query()->find($membership->role_id)?->screen_access ?? [];
                    }
                }

                $notifications = \App\Models\Task::query()
                    ->with(['booking', 'status'])
                    ->where('tenant_id', $tenantId)
                    ->where('assignee_type', \App\Models\Task::ASSIGNEE_USER)
                    ->where('assignee_id', $user->id)
                    ->whereNull('notification_dismissed_at')
                    ->orderByRaw('case when due_date is null then 1 else 0 end')
                    ->orderBy('due_date')
                    ->latest('created_at')
                    ->get()
                    ->map(function (\App\Models\Task $task) {
                        $booking = $task->booking;
                        $bookingLabel = $booking?->quote_number
                            ? sprintf('%s - %s', $booking->quote_number, $booking->entry_name ?: $booking->customer_name)
                            : ($booking?->entry_name ?: $booking?->customer_name);

                        return [
                            'id' => $task->id,
                            'title' => $task->task_name,
                            'status' => $task->status?->name ?: 'Open',
                            'due_date_label' => \App\Support\DateFormatter::date($task->due_date, 'No due date'),
                            'booking_label' => $bookingLabel,
                            'task_url' => route('tasks.index'),
                            'booking_url' => $booking ? route('admin.bookings.show', $booking) : null,
                            'dismiss_url' => route('tasks.notifications.dismiss', $task),
                        ];
                    })
                    ->values();
            }
        @endphp
        <script>
            window.googleMapsApiKey = @js($props['tenant']['google_maps_api_key'] ?? env('VITE_GOOGLE_MAPS_API_KEY', ''));
            window.adminPage = @js($page);
            window.adminProps = @js(array_merge($props, [
                'allowedScreens' => $allowedScreens,
                'csrfToken' => csrf_token(),
                'currentUser' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'notifications' => $notifications,
                'notificationRoutes' => $user && $tenantId ? [
                    'index' => route('tasks.notifications.index'),
                ] : null,
                'flash' => [
                    'status' => session('status'),
                    'errors' => $errors->all(),
                    'old' => session()->getOldInput(),
                ],
            ]));
        </script>
        <div id="app" data-page="{{ $page }}"></div>
    </body>
</html>
