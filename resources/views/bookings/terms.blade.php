<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $tenant->name }} Terms and Conditions</title>
        @if ($tenant->logo_path)
            <link rel="icon" type="image/png" href="{{ url(Storage::disk('public')->url($tenant->logo_path)) }}">
        @endif
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-stone-950 text-stone-50" data-theme="dark">
        <main class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
            <section class="rounded-[2rem] border border-white/10 bg-gradient-to-br from-cyan-300/15 via-stone-900 to-amber-300/10 p-6 shadow-2xl shadow-black/20 sm:p-8">
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
                        <p class="text-xs uppercase tracking-[0.3em] text-stone-400">Booking Terms</p>
                    </div>
                </div>
                <p class="text-sm uppercase tracking-[0.35em] text-cyan-200">Terms and Conditions</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $tenant->name }}</h1>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-stone-300">
                    Please review these booking terms before confirming your booking and paying the deposit.
                </p>
            </section>

            <section class="mt-6 space-y-4 rounded-[2rem] border border-white/10 bg-white/5 p-6">
                <article class="rounded-3xl border border-white/10 bg-stone-950/50 p-5">
                    <h2 class="text-xl font-semibold">1. Booking and Deposit</h2>
                    <p class="mt-3 text-sm leading-7 text-stone-300">
                        A booking is considered confirmed once the deposit has been paid through the secure Stripe checkout flow.
                        The remaining balance will be invoiced separately and must be paid according to the installment schedule shown on your invoice.
                    </p>
                </article>

                <article class="rounded-3xl border border-white/10 bg-stone-950/50 p-5">
                    <h2 class="text-xl font-semibold">2. Customer Details</h2>
                    <p class="mt-3 text-sm leading-7 text-stone-300">
                        You agree that the contact and event details you provide are accurate and can be used to prepare your quote,
                        booking, invoice, and follow-up communication.
                    </p>
                </article>

                <article class="rounded-3xl border border-white/10 bg-stone-950/50 p-5">
                    <h2 class="text-xl font-semibold">3. Changes and Availability</h2>
                    <p class="mt-3 text-sm leading-7 text-stone-300">
                        Package inclusions, add-ons, scheduling, and availability remain subject to final confirmation.
                        If any requested item becomes unavailable, we will contact you to agree on a suitable replacement or update.
                    </p>
                </article>

                <article class="rounded-3xl border border-white/10 bg-stone-950/50 p-5">
                    <h2 class="text-xl font-semibold">4. Cancellations and Refunds</h2>
                    <p class="mt-3 text-sm leading-7 text-stone-300">
                        Deposit handling, refunds, and cancellations are managed according to the booking terms provided by {{ $tenant->name }}.
                        Please contact the business directly if you need clarification before paying.
                    </p>
                </article>
            </section>
        </main>
    </body>
</html>
