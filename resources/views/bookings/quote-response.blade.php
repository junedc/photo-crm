<!DOCTYPE html>
@php use App\Support\DateFormatter; @endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $tenant->name }} Quote Response</title>
        @if ($tenant->logo_path)
            <link rel="icon" type="image/png" href="{{ url(Storage::disk('public')->url($tenant->logo_path)) }}">
        @endif
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-stone-950 text-stone-50" data-theme="dark">
        <main class="mx-auto flex min-h-screen max-w-4xl items-center px-4 py-10 sm:px-6 lg:px-8">
            <section class="w-full rounded-[2rem] border border-white/10 bg-gradient-to-br from-cyan-300/15 via-stone-900 to-emerald-300/10 p-6 shadow-2xl shadow-black/20 sm:p-8">
                <div class="mb-5 flex items-center gap-4">
                    @if ($tenant->logo_path)
                        <img src="{{ Storage::disk('public')->url($tenant->logo_path) }}" alt="{{ $tenant->name }} logo" class="h-14 w-14 rounded-2xl object-cover shadow-lg shadow-black/20">
                    @else
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-lg font-semibold text-stone-300">
                            {{ \Illuminate\Support\Str::of($tenant->name)->substr(0, 1) }}
                        </div>
                    @endif
                    <div>
                        <p class="text-sm font-semibold text-white">{{ $tenant->name }}</p>
                        <p class="text-xs uppercase tracking-[0.3em] text-stone-400">Quote Response</p>
                    </div>
                </div>
                <p class="text-sm uppercase tracking-[0.35em] {{ $response === 'accepted' ? 'text-emerald-200' : 'text-rose-200' }}">
                    Quote {{ ucfirst($response) }}
                </p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                    {{ $booking->customer_name }}, your response has been recorded
                </h1>
                <p class="mt-4 max-w-3xl text-sm leading-7 text-stone-300">
                    You have {{ $response }} the quote for
                    <strong>{{ $booking->package?->name ?? 'your selected package' }}</strong>
                    on {{ DateFormatter::date($booking->event_date, 'your event date') }}.
                </p>

                <div class="mt-6 rounded-3xl border border-white/10 bg-stone-950/50 p-5">
                    <p class="text-sm uppercase tracking-[0.3em] text-stone-400">Quote Summary</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-white/10 bg-stone-950/60 p-4">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Package</p>
                            <p class="mt-2 text-base font-semibold">{{ $booking->package?->name ?? 'No package selected' }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-stone-950/60 p-4">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Event Location</p>
                            <p class="mt-2 text-base font-semibold">{{ $booking->event_location }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('bookings.create') }}" class="rounded-2xl border border-white/10 px-4 py-3 text-center text-sm font-semibold text-white transition hover:bg-white/5">
                        Request Another Quote
                    </a>
                    @if ($response === 'accepted')
                        <p class="rounded-2xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100">
                            Thank you. The business can now follow up with your accepted quote details.
                        </p>
                    @else
                        <p class="rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-100">
                            Thanks for letting us know. You can request another quote at any time.
                        </p>
                    @endif
                </div>
            </section>
        </main>
    </body>
</html>
