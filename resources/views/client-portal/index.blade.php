<!DOCTYPE html>
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
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-white/10 bg-white/[0.04] p-6">
            <div class="space-y-2">
                <p class="text-[11px] uppercase tracking-[0.35em] text-cyan-200">Client Portal</p>
                <h1 class="text-3xl font-semibold tracking-tight text-white">{{ $customerName ?: $customerEmail }}</h1>
                <p class="text-sm text-stone-300">Review your current and previous bookings with {{ $tenant->name }}.</p>
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
                                <dd class="mt-1 text-stone-100">{{ $booking->event_date?->format('d M Y') ?? 'To be confirmed' }}</dd>
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
                                {{ $booking->event_date?->format('d M Y') ?? 'Past booking' }}
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
