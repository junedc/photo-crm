<!DOCTYPE html>
@php use App\Support\DateFormatter; @endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Client Portal | {{ $tenant->name }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-950 text-stone-50">
    <div class="mx-auto max-w-6xl px-6 py-10">
        @if (session('status'))
            <div class="mb-4 rounded-2xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-100">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="mb-8 flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-white/10 bg-white/[0.04] p-6">
            <div class="space-y-2">
                <p class="text-[11px] uppercase tracking-[0.35em] text-cyan-200">Client Portal</p>
                <h1 class="text-3xl font-semibold tracking-tight text-white">{{ $customerName ?: $customerEmail }}</h1>
                <p class="text-sm text-stone-300">Review your bookings, design drafts, and any client tasks from {{ $tenant->name }}.</p>
            </div>
            <form method="POST" action="{{ route('client.portal.logout') }}">
                @csrf
                <button type="submit" class="rounded-2xl border border-white/10 px-4 py-2 text-sm font-medium text-stone-200 transition hover:bg-white/5">Sign out</button>
            </form>
        </div>

        <div class="grid gap-8 xl:grid-cols-2">
            <section class="space-y-4">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.3em] text-cyan-200">Current And Upcoming</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">{{ $upcomingBookings->count() }} booking{{ $upcomingBookings->count() === 1 ? '' : 's' }}</h2>
                </div>

                @forelse ($upcomingBookings as $booking)
                    <article class="rounded-3xl border border-white/10 bg-white/[0.04] p-5">
                        @php
                            $clientTasks = $booking->tasks;
                            $openTaskCount = $clientTasks->filter(fn ($task) => str_replace([' ', '-'], '_', strtolower($task->status?->name ?? 'pending')) !== 'completed')->count();
                        @endphp
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm text-cyan-200">{{ $booking->quote_number ?: 'Booking #'.$booking->id }}</p>
                                <h3 class="mt-1 text-lg font-semibold text-white">{{ $booking->package?->name ?? 'Custom booking' }}</h3>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-medium {{ $booking->status === 'confirmed' ? 'bg-emerald-400/15 text-emerald-200' : ($booking->status === 'completed' ? 'bg-cyan-300/15 text-cyan-200' : 'bg-amber-300/15 text-amber-200') }}">
                                {{ str($booking->status)->replace('_', ' ')->title() }}
                            </span>
                        </div>
                        <dl class="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
                            <div>
                                <dt class="text-stone-500">Event date</dt>
                                <dd class="mt-1 text-stone-100">{{ DateFormatter::date($booking->event_date, 'To be confirmed') }}</dd>
                            </div>
                            <div>
                                <dt class="text-stone-500">Time</dt>
                                <dd class="mt-1 text-stone-100">{{ $booking->start_time }} - {{ $booking->end_time }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-stone-500">Location</dt>
                                <dd class="mt-1 text-stone-100">{{ $booking->event_location ?: 'To be confirmed' }}</dd>
                            </div>
                            <div>
                                <dt class="text-stone-500">Add-ons</dt>
                                <dd class="mt-1 text-stone-100">{{ $booking->addOns->pluck('name')->join(', ') ?: 'None selected' }}</dd>
                            </div>
                            <div>
                                <dt class="text-stone-500">Invoice</dt>
                                <dd class="mt-1 text-stone-100">{{ $booking->invoice?->invoice_number ?: 'Not issued yet' }}</dd>
                            </div>
                        </dl>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ route('client.portal.design', $booking) }}" class="rounded-2xl border border-cyan-300/30 px-4 py-2 text-sm font-medium text-cyan-100 transition hover:bg-cyan-300/10">
                                {{ $booking->clientPortalDesign ? 'Edit design draft' : 'Create design draft' }}
                            </a>
                            @if ($booking->clientPortalDesign?->last_saved_at)
                                <span class="inline-flex items-center rounded-2xl border border-white/10 px-4 py-2 text-xs text-stone-400">
                                    Saved {{ DateFormatter::dateTime($booking->clientPortalDesign->last_saved_at) }}
                                </span>
                            @endif
                        </div>

                        @if ($clientTasks->isNotEmpty())
                            <div class="mt-5 rounded-3xl border border-white/10 bg-slate-950/40 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="text-[11px] uppercase tracking-[0.3em] text-cyan-200">Task List</p>
                                        <h4 class="mt-1 text-base font-semibold text-white">{{ $clientTasks->count() }} task{{ $clientTasks->count() === 1 ? '' : 's' }} for this booking</h4>
                                    </div>
                                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-stone-300">{{ $openTaskCount }} open</span>
                                </div>

                                <div class="mt-4 space-y-4">
                                    @foreach ($clientTasks as $task)
                                        @php
                                            $statusName = $task->status?->name ?: 'pending';
                                            $normalizedStatus = str_replace([' ', '-'], '_', strtolower($statusName));
                                            $statusClasses = match ($normalizedStatus) {
                                                'completed' => 'bg-emerald-400/15 text-emerald-200',
                                                'in_progress' => 'bg-amber-300/15 text-amber-200',
                                                default => 'bg-cyan-300/15 text-cyan-200',
                                            };
                                            $isActiveTaskForm = (string) old('form_task_id') === (string) $task->id;
                                        @endphp
                                        <div id="task-{{ $task->id }}" class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Customer task</p>
                                                    <h5 class="mt-1 text-base font-semibold text-white">{{ $task->task_name }}</h5>
                                                </div>
                                                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $statusClasses }}">
                                                    {{ str($statusName)->replace('_', ' ')->title() }}
                                                </span>
                                            </div>

                                            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                                                <div>
                                                    <dt class="text-stone-500">Due date</dt>
                                                    <dd class="mt-1 text-stone-100">{{ DateFormatter::date($task->due_date, 'No due date yet') }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-stone-500">Hours</dt>
                                                    <dd class="mt-1 text-stone-100">{{ $task->task_duration_hours ?: '0.00' }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-stone-500">Last update</dt>
                                                    <dd class="mt-1 text-stone-100">{{ DateFormatter::dateTime($task->clientPortalUpdates->first()?->created_at, 'No reply yet') }}</dd>
                                                </div>
                                            </dl>

                                            @if (filled($task->remarks))
                                                <div class="mt-3 rounded-2xl border border-white/10 bg-slate-950/50 px-4 py-3">
                                                    <p class="text-[11px] uppercase tracking-[0.28em] text-stone-400">Instructions</p>
                                                    <p class="mt-2 text-sm leading-6 text-stone-200">{{ $task->remarks }}</p>
                                                </div>
                                            @endif

                                            @if ($task->clientPortalUpdates->isNotEmpty())
                                                <div class="mt-3 space-y-2">
                                                    <p class="text-[11px] uppercase tracking-[0.28em] text-stone-400">Your recent updates</p>
                                                    @foreach ($task->clientPortalUpdates->take(3) as $update)
                                                        <div class="rounded-2xl border border-white/10 bg-slate-950/40 px-4 py-3">
                                                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-stone-400">
                                                                <span>{{ str($update->action)->replace('_', ' ')->title() }}</span>
                                                                <span>{{ DateFormatter::dateTime($update->created_at) }}</span>
                                                            </div>
                                                            @if (filled($update->note))
                                                                <p class="mt-2 text-sm leading-6 text-stone-200">{{ $update->note }}</p>
                                                            @endif
                                                            @if (! empty($update->attachments))
                                                                <div class="mt-3 flex flex-wrap gap-2">
                                                                    @foreach ($update->attachments as $attachment)
                                                                        <a href="{{ $attachment['url'] ?? '#' }}" target="_blank" rel="noreferrer" class="inline-flex items-center rounded-full border border-cyan-300/20 bg-cyan-300/10 px-3 py-1 text-xs text-cyan-100 transition hover:bg-cyan-300/15">
                                                                            {{ $attachment['name'] ?? 'Attachment' }}
                                                                        </a>
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif

                                            <form method="POST" action="{{ route('client.portal.tasks.respond', [$booking, $task]) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                                                @csrf
                                                <input type="hidden" name="form_task_id" value="{{ $task->id }}">
                                                <div>
                                                    <label for="task-note-{{ $task->id }}" class="text-[11px] uppercase tracking-[0.28em] text-stone-400">Reply Or Notes</label>
                                                    <textarea id="task-note-{{ $task->id }}" name="note" rows="3" class="mt-2 w-full rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm text-white outline-none transition placeholder:text-stone-500 focus:border-cyan-300/40">{{ $isActiveTaskForm ? old('note') : '' }}</textarea>
                                                    @if ($isActiveTaskForm)
                                                        @error('note')
                                                            <p class="mt-2 text-xs text-rose-200">{{ $message }}</p>
                                                        @enderror
                                                        @error('attachments')
                                                            <p class="mt-2 text-xs text-rose-200">{{ $message }}</p>
                                                        @enderror
                                                        @error('attachments.*')
                                                            <p class="mt-2 text-xs text-rose-200">{{ $message }}</p>
                                                        @enderror
                                                    @endif
                                                </div>
                                                <div>
                                                    <label for="task-attachments-{{ $task->id }}" class="text-[11px] uppercase tracking-[0.28em] text-stone-400">Upload Images, Videos, Or PDF</label>
                                                    <input id="task-attachments-{{ $task->id }}" type="file" name="attachments[]" multiple accept="image/*,video/*,.pdf" class="mt-2 block w-full rounded-2xl border border-dashed border-white/10 bg-slate-950/50 px-4 py-3 text-sm text-stone-300 file:mr-3 file:rounded-full file:border-0 file:bg-cyan-300/15 file:px-3 file:py-2 file:text-xs file:font-medium file:text-cyan-100 hover:file:bg-cyan-300/20">
                                                </div>
                                                <div class="flex flex-wrap gap-2">
                                                    <button type="submit" name="action" value="save_note" class="rounded-2xl border border-white/10 px-4 py-2 text-sm font-medium text-stone-200 transition hover:bg-white/5">Save note</button>
                                                    <button type="submit" name="action" value="mark_in_progress" class="rounded-2xl border border-amber-300/25 bg-amber-300/10 px-4 py-2 text-sm font-medium text-amber-100 transition hover:bg-amber-300/15">Mark in progress</button>
                                                    <button type="submit" name="action" value="mark_completed" class="rounded-2xl border border-emerald-300/25 bg-emerald-300/10 px-4 py-2 text-sm font-medium text-emerald-100 transition hover:bg-emerald-300/15">Mark completed</button>
                                                </div>
                                            </form>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="rounded-3xl border border-dashed border-white/10 bg-white/[0.03] p-6 text-sm text-stone-400">
                        No current or upcoming bookings are linked to this email yet.
                    </div>
                @endforelse
            </section>

            <section class="space-y-4">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Previous Bookings</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">{{ $pastBookings->count() }} booking{{ $pastBookings->count() === 1 ? '' : 's' }}</h2>
                </div>

                @forelse ($pastBookings as $booking)
                    <article class="rounded-3xl border border-white/10 bg-white/[0.04] p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm text-stone-400">{{ $booking->quote_number ?: 'Booking #'.$booking->id }}</p>
                                <h3 class="mt-1 text-lg font-semibold text-white">{{ $booking->package?->name ?? 'Custom booking' }}</h3>
                            </div>
                            <span class="rounded-full bg-white/5 px-3 py-1 text-xs font-medium text-stone-300">
                                {{ DateFormatter::date($booking->event_date, 'Past booking') }}
                            </span>
                        </div>
                        <p class="mt-4 text-sm leading-6 text-stone-300">
                            {{ $booking->event_location ?: 'Location not recorded.' }}
                        </p>
                    </article>
                @empty
                    <div class="rounded-3xl border border-dashed border-white/10 bg-white/[0.03] p-6 text-sm text-stone-400">
                        No previous bookings were found for this email.
                    </div>
                @endforelse
            </section>
        </div>
    </div>
</body>
</html>
