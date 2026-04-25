<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $tenant->name }} Booking</title>
        @if ($tenant->logo_path)
            <link rel="icon" type="image/png" href="{{ url(Storage::disk('public')->url($tenant->logo_path)) }}">
        @endif
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
        <style>
            input:checked + article .selection-indicator {
                border-color: rgb(167 243 208);
                background: rgb(167 243 208);
                color: rgb(12 10 9);
            }

            [data-wizard-step][hidden] {
                display: none;
            }
        </style>
    </head>
    <body class="min-h-screen bg-stone-950 text-stone-50" data-theme="{{ $tenant->theme ?: 'dark' }}">
        <script>
            window.googleMapsApiKey = @json($googleMapsApiKey);
        </script>
        @php
            $customerPackageDiscountPercentage = (float) ($customerPackageDiscountPercentage ?? 0);
            $applyCustomerPackageDiscount = static fn (float $amount): float => round($amount * (1 - ($customerPackageDiscountPercentage / 100)), 2);
            $bookingCurrencyCode = strtoupper($tenant?->stripe_currency ?: config('services.platform_stripe.currency', 'usd'));
            $wizardErrorSteps = [
                'customer_name' => 1,
                'customer_phone' => 1,
                'customer_email' => 1,
                'event_date' => 1,
                'event_type' => 1,
                'start_time' => 1,
                'end_time' => 1,
                'total_hours' => 1,
                'package_id' => 2,
                'package_hourly_price_id' => 2,
                'add_on_ids' => 3,
                'add_on_ids.*' => 3,
                'equipment_ids' => 3,
                'equipment_ids.*' => 3,
                'event_location' => 4,
                'travel_distance_km' => 4,
                'travel_fee' => 4,
                'terms_accepted' => 5,
            ];
            $firstErrorKey = $errors->keys()[0] ?? null;
            $initialWizardStep = $wizardErrorSteps[$firstErrorKey] ?? 1;
            $firstErrorMessage = $firstErrorKey ? $errors->first($firstErrorKey) : null;
        @endphp
        <div id="booking-toast" class="pointer-events-none fixed right-4 top-24 z-[90] hidden max-w-sm rounded-2xl border px-4 py-3 text-sm shadow-2xl shadow-black/30 backdrop-blur">
            <p id="booking-toast-message"></p>
        </div>

        <div class="fixed inset-x-0 top-0 z-[70] border-b border-white/10 bg-stone-950/95 backdrop-blur-xl">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <div class="min-w-0">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-cyan-200">Booking Summary</p>
                    <p id="booking-summary-package" class="mt-1 truncate text-sm font-medium text-white">No package selected</p>
                </div>
                <div class="flex items-center gap-6 text-right">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Travel Fee</p>
                        <p id="booking-summary-travel" class="mt-1 text-sm font-medium text-stone-200">{{ $bookingCurrencyCode }} $0.00</p>
                    </div>
                    <p id="booking-summary-discount" class="hidden">-$0.00</p>
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Deposit</p>
                        <p id="booking-summary-deposit" class="mt-1 text-sm font-medium text-amber-200">{{ $bookingCurrencyCode }} $0.00</p>
                    </div>
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Total Price</p>
                        <p id="booking-summary-total" class="mt-1 text-xl font-semibold text-cyan-200">{{ $bookingCurrencyCode }} $0.00</p>
                    </div>
                    <button
                        type="button"
                        id="booking-cart-toggle"
                        class="relative inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-cyan-300/30 bg-cyan-300/10 text-cyan-100 transition hover:bg-cyan-300/20"
                        aria-expanded="false"
                        aria-controls="booking-cart-panel"
                        title="View selected package and add-ons"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M5 6h16l-2 8H8L5 3H2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M9 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2ZM18 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span id="booking-cart-count" class="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-200 px-1 text-[10px] font-bold text-stone-950">0</span>
                    </button>
                </div>
            </div>
        </div>

        <div id="booking-cart-panel" class="fixed right-4 top-24 z-[75] hidden w-[min(24rem,calc(100vw-2rem))] rounded-3xl border border-white/10 bg-stone-950/95 p-4 text-sm text-stone-200 shadow-2xl shadow-black/40 backdrop-blur-xl sm:right-6">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.28em] text-cyan-200">Booking Cart</p>
                    <p class="mt-1 text-xs text-stone-400">Selected package and add-ons</p>
                </div>
                <button type="button" id="booking-cart-close" class="rounded-xl border border-white/10 px-3 py-1.5 text-xs font-semibold text-stone-300 transition hover:bg-white/5">
                    Close
                </button>
            </div>
            <div id="booking-cart-content" class="space-y-3">
                <p class="rounded-2xl border border-dashed border-white/10 bg-white/[0.03] px-4 py-3 text-stone-400">No package selected yet.</p>
            </div>
        </div>

        <main class="mx-auto max-w-6xl px-4 pb-10 pt-28 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mt-6 rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('warning'))
                <div class="mt-6 rounded-2xl border border-amber-400/30 bg-amber-500/10 px-5 py-4 text-sm text-amber-100">
                    {{ session('warning') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-6 rounded-2xl border border-rose-400/30 bg-rose-500/10 px-5 py-4 text-sm font-medium text-rose-100">
                    Please review the highlighted fields below.
                    @if ($firstErrorMessage)
                        <p class="mt-2 text-sm font-normal text-rose-200">{{ $firstErrorMessage }}</p>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('bookings.store') }}" class="mt-8 space-y-6" novalidate>
                @csrf
                <input type="hidden" name="lead_token" id="lead-token" value="{{ old('lead_token', $leadToken) }}">
                <input type="hidden" name="package_hourly_price_id" id="package-hourly-price-id" value="{{ old('package_hourly_price_id') }}">
                <input type="hidden" name="travel_distance_km" id="travel-distance-km" value="{{ old('travel_distance_km', '0.00') }}">
                <input type="hidden" name="travel_fee" id="travel-fee" value="{{ old('travel_fee', '0.00') }}">
                <input type="hidden" name="total_hours" id="total-hours" value="{{ old('total_hours', '0.00') }}">

                <nav class="rounded-3xl border border-white/10 bg-white/5 px-3 py-3" aria-label="Booking steps">
                    <div class="flex items-start overflow-x-auto overflow-y-hidden">
                        @foreach ([
                            ['Customer', 'Your details'],
                            ['Package', 'Choose package'],
                            ['Add-ons', 'Optional extras'],
                            ['Location', 'Travel fee'],
                            ['Summary', 'Quote or book'],
                        ] as $index => [$label, $description])
                            <button
                                type="button"
                                class="group flex min-w-[5.25rem] shrink-0 flex-col items-center text-center transition"
                                data-wizard-nav="{{ $index + 1 }}"
                                aria-current="{{ $index === 0 ? 'step' : 'false' }}"
                            >
                                <span data-step-circle class="flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-stone-950 text-stone-400 shadow-lg shadow-black/20 transition group-hover:border-cyan-300/40">
                                    @switch($index)
                                        @case(0)
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M5 5h14v14H5V5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                                                <path d="M8 9h.01M8 15h.01M11 9h5M11 15h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                            </svg>
                                            @break
                                        @case(1)
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="1.8" />
                                                <path d="M4 21a8 8 0 0 1 16 0M19 8v6M16 11h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                            </svg>
                                            @break
                                        @case(2)
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="1.8" />
                                                <path d="M19 12h2M3 12h2M12 3v2M12 19v2M17 7l1.5-1.5M5.5 18.5 7 17M7 7 5.5 5.5M18.5 18.5 17 17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                            </svg>
                                            @break
                                        @case(3)
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                                                <path d="M12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" stroke="currentColor" stroke-width="1.8" />
                                            </svg>
                                            @break
                                        @default
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M20 7 10 17l-5-5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                                                <path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z" stroke="currentColor" stroke-width="1.8" />
                                            </svg>
                                    @endswitch
                                </span>
                                <span data-step-label class="mt-2 text-xs font-semibold text-stone-300 transition">{{ $label }}</span>
                                <span class="mt-0.5 hidden text-[10px] leading-none text-stone-500 sm:block">{{ $description }}</span>
                            </button>

                            @if (! $loop->last)
                                <span class="mt-5 h-1.5 min-w-10 flex-1 overflow-hidden rounded-full bg-white/5 sm:min-w-16" aria-hidden="true">
                                    <span data-wizard-connector="{{ $index + 1 }}" class="block h-full w-0 rounded-full bg-emerald-300 transition-all duration-300"></span>
                                </span>
                            @endif
                        @endforeach
                    </div>
                </nav>

                <section class="rounded-[2rem] border border-white/10 bg-white/5 p-5" data-wizard-step="1">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <p class="text-xs uppercase tracking-[0.3em] text-cyan-200">Customer Details</p>
                            <span class="hidden text-stone-600 sm:inline">•</span>
                            <h2 class="text-lg font-semibold">Booking request</h2>
                        </div>
                        <p id="lead-autosave-status" class="text-right text-xs text-stone-400">
                            Customer details are saved automatically.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div class="grid gap-4 lg:grid-cols-12">
                            <div class="lg:col-span-5">
                                <label class="mb-2 block text-sm text-stone-300" for="customer-name">Full name <span class="text-rose-300" aria-hidden="true">*</span></label>
                                <input id="customer-name" name="customer_name" type="text" value="{{ old('customer_name') }}" class="w-full rounded-2xl border bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50 {{ $errors->has('customer_name') ? 'border-rose-300/70' : 'border-white/10' }}" required>
                                <p class="mt-2 hidden text-xs font-medium text-rose-200" data-validation-for="customer_name"></p>
                                @error('customer_name')
                                    <p class="mt-2 text-xs font-medium text-rose-200">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="lg:col-span-3">
                                <label class="mb-2 block text-sm text-stone-300" for="customer-phone">Phone number <span class="text-rose-300" aria-hidden="true">*</span></label>
                                <input id="customer-phone" name="customer_phone" type="text" value="{{ old('customer_phone') }}" class="w-full rounded-2xl border bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50 {{ $errors->has('customer_phone') ? 'border-rose-300/70' : 'border-white/10' }}" required>
                                <p class="mt-2 hidden text-xs font-medium text-rose-200" data-validation-for="customer_phone"></p>
                                @error('customer_phone')
                                    <p class="mt-2 text-xs font-medium text-rose-200">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="lg:col-span-4">
                                <label class="mb-2 block text-sm text-stone-300" for="customer-email">Email address <span class="text-rose-300" aria-hidden="true">*</span></label>
                                <input id="customer-email" name="customer_email" type="email" value="{{ old('customer_email') }}" class="w-full rounded-2xl border bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50 {{ $errors->has('customer_email') ? 'border-rose-300/70' : 'border-white/10' }}" required>
                                <p class="mt-2 hidden text-xs font-medium text-rose-200" data-validation-for="customer_email"></p>
                                @error('customer_email')
                                    <p class="mt-2 text-xs font-medium text-rose-200">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm text-stone-300" for="event-date">Event date <span class="text-rose-300" aria-hidden="true">*</span></label>
                                <input id="event-date" name="event_date" type="date" value="{{ old('event_date') }}" class="w-full rounded-2xl border bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50 {{ $errors->has('event_date') ? 'border-rose-300/70' : 'border-white/10' }}" onkeydown="return false" required>
                                <p class="mt-2 hidden text-xs font-medium text-rose-200" data-validation-for="event_date"></p>
                                @error('event_date')
                                    <p class="mt-2 text-xs font-medium text-rose-200">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-sm text-stone-300" for="event-type">Event type <span class="text-rose-300" aria-hidden="true">*</span></label>
                                <select id="event-type" name="event_type" class="w-full rounded-2xl border bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50 {{ $errors->has('event_type') ? 'border-rose-300/70' : 'border-white/10' }}" required>
                                    <option value="" disabled @selected(! old('event_type'))>Select event type</option>
                                    @foreach (['Wedding', 'Birthday', 'Anniversary', 'Others'] as $eventType)
                                        <option value="{{ $eventType }}" @selected(old('event_type') === $eventType)>{{ $eventType }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-2 hidden text-xs font-medium text-rose-200" data-validation-for="event_type"></p>
                                @error('event_type')
                                    <p class="mt-2 text-xs font-medium text-rose-200">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="lg:col-span-2">
                                <label class="mb-2 block text-sm text-stone-300" for="event-location">Event location <span class="text-rose-300" aria-hidden="true">*</span></label>
                                <input id="event-location" name="event_location" type="text" value="{{ old('event_location') }}" data-google-address="true" autocomplete="street-address" class="w-full rounded-2xl border bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50 {{ $errors->has('event_location') ? 'border-rose-300/70' : 'border-white/10' }}" required>
                                <p class="mt-2 hidden text-xs font-medium text-rose-200" data-validation-for="event_location"></p>
                                @error('event_location')
                                    <p class="mt-2 text-xs font-medium text-rose-200">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-2 block text-sm text-stone-300" for="start-time">Start hour <span class="text-rose-300" aria-hidden="true">*</span></label>
                                <select id="start-time" name="start_time" class="w-full rounded-2xl border bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50 {{ $errors->has('start_time') ? 'border-rose-300/70' : 'border-white/10' }}" required>
                                    <option value="" disabled @selected(! old('start_time'))>Select start time</option>
                                    @for ($hour = 8; $hour < 24; $hour++)
                                        @foreach (['00', '30'] as $minute)
                                            @continue($hour === 8 && $minute === '00')
                                            @php
                                                $timeValue = sprintf('%02d:%s', $hour, $minute);
                                            @endphp
                                            <option value="{{ $timeValue }}" @selected(old('start_time') === $timeValue)>{{ \App\Support\DateFormatter::time($timeValue) }}</option>
                                        @endforeach
                                    @endfor
                                </select>
                                <p class="mt-2 hidden text-xs font-medium text-rose-200" data-validation-for="start_time"></p>
                                @error('start_time')
                                    <p class="mt-2 text-xs font-medium text-rose-200">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-sm text-stone-300" for="end-time-display">End hour <span class="text-rose-300" aria-hidden="true">*</span></label>
                                <input id="end-time-display" type="text" value="{{ old('end_time') ? \App\Support\DateFormatter::time(old('end_time')) : '' }}" class="w-full rounded-2xl border bg-stone-900/60 px-4 py-3 text-white outline-none {{ $errors->has('end_time') ? 'border-rose-300/70' : 'border-white/10' }}" readonly>
                                <input id="end-time" name="end_time" type="hidden" value="{{ old('end_time') }}" required>
                                <p class="mt-2 hidden text-xs font-medium text-rose-200" data-validation-for="end_time"></p>
                                @error('end_time')
                                    <p class="mt-2 text-xs font-medium text-rose-200">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-sm text-stone-300" for="total-hours-display">Duration <span class="text-rose-300" aria-hidden="true">*</span></label>
                                <input id="total-hours-display" type="number" min="0.50" step="0.50" value="{{ old('total_hours', '0.00') }}" class="w-full rounded-2xl border bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50 {{ $errors->has('total_hours') ? 'border-rose-300/70' : 'border-white/10' }}" required>
                                <p class="mt-2 hidden text-xs font-medium text-rose-200" data-validation-for="total_hours"></p>
                                @error('total_hours')
                                    <p class="mt-2 text-xs font-medium text-rose-200">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm text-stone-300" for="booking-notes">Notes</label>
                            <textarea id="booking-notes" name="notes" rows="5" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="button" class="rounded-2xl bg-cyan-300 px-5 py-3 text-sm font-semibold text-stone-950 transition hover:bg-cyan-200" data-wizard-next>
                            Continue to Packages
                        </button>
                    </div>

                </section>

                <div class="space-y-6">
                    <section class="rounded-[2rem] border border-white/10 bg-white/5 p-6" data-wizard-step="2" hidden>
                        <div class="mb-5">
                            <h2 class="text-lg font-semibold text-white">
                                <span class="text-amber-200">Choose Package</span>
                                <span class="mx-2 text-stone-500">•</span>
                                <span>Available packages</span>
                            </h2>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            @forelse ($packages as $package)
                                @php
                                    $packageHourlyPrices = $package->hourlyPrices
                                        ->map(function ($tier) {
                                            return [
                                                'id' => $tier->id,
                                                'hours' => number_format((float) $tier->hours, 2, '.', ''),
                                                'price' => number_format((float) $tier->price, 2, '.', ''),
                                            ];
                                        })
                                        ->values()
                                        ->all();
                                @endphp
                                <label class="block cursor-pointer">
                                    <input
                                        type="radio"
                                        name="package_id"
                                        value="{{ $package->id }}"
                                        class="peer sr-only"
                                        data-package-name="{{ $package->name }}"
                                        data-package-price="{{ number_format((float) $package->base_price, 2, '.', '') }}"
                                        data-package-display-price="{{ number_format((float) (($package->hourlyPrices->min('price')) ?? $package->base_price), 2, '.', '') }}"
                                        data-package-discount-percentage="{{ number_format($customerPackageDiscountPercentage, 2, '.', '') }}"
                                        data-package-hourly-prices='@json($packageHourlyPrices)'
                                        data-package-addon-ids='@json($package->addOns->pluck('id')->values()->all())'
                                        data-package-photo-url="{{ $package->photo_path ? Storage::disk('public')->url($package->photo_path) : '' }}"
                                        @checked((int) old('package_id') === $package->id)
                                        required
                                    >
                                    <article class="h-full overflow-hidden rounded-3xl border border-white/10 bg-stone-950/50 transition peer-checked:border-amber-300/60 peer-checked:bg-amber-300/10 hover:border-white/20">
                                        <div class="relative">
                                            @if ($package->photo_path)
                                                <img src="{{ Storage::disk('public')->url($package->photo_path) }}" alt="{{ $package->name }}" class="h-40 w-full object-cover">
                                            @else
                                                <div class="flex h-40 items-center justify-center bg-stone-900 text-5xl text-stone-600">P</div>
                                            @endif
                                            <span class="absolute right-3 top-3 rounded-full border border-rose-300/40 bg-stone-950/90 px-3.5 py-1.5 text-sm font-bold tracking-wide text-rose-300 shadow-lg shadow-black/30">
                                                @php
                                                    $packageFromPrice = (float) (($package->hourlyPrices->min('price')) ?? $package->base_price);
                                                    $discountedFromPrice = $applyCustomerPackageDiscount($packageFromPrice);
                                                @endphp
                                                @if ($customerPackageDiscountPercentage > 0)
                                                    <span class="mr-1 text-xs font-medium text-stone-400 line-through">${{ number_format($packageFromPrice, 2) }}</span>
                                                @endif
                                                From ${{ number_format($discountedFromPrice, 2) }}
                                            </span>
                                        </div>

                                        <div class="p-4">
                                            <div>
                                                <h3 class="text-lg font-semibold text-white">{{ $package->name }}</h3>
                                            </div>
                                            <div class="mt-3 flex items-center justify-between gap-2">
                                                <button
                                                    type="button"
                                                    class="inline-flex rounded-full border border-amber-300/30 bg-amber-300/10 px-3 py-1.5 text-xs font-medium text-amber-100 transition hover:border-amber-200/60 hover:bg-amber-300/20"
                                                    data-details-modal="package-details-{{ $package->id }}"
                                                    data-details-name="{{ $package->name }}"
                                                    data-details-label="Package Details"
                                                >
                                                    View details
                                                </button>
                                                <span class="selection-indicator flex h-8 w-8 items-center justify-center rounded-xl border border-white/20 bg-stone-950/85 text-transparent shadow-lg shadow-black/30 transition">
                                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                                        <path d="M4.5 10.5 8.2 14l7.3-8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                                                    </svg>
                                                </span>
                                            </div>
                                        </div>
                                    </article>
                                </label>

                                <template id="package-details-{{ $package->id }}">
                                    <div class="space-y-5">
                                        <div class="overflow-hidden rounded-3xl border border-white/10 bg-stone-950/60">
                                            @if ($package->photo_path)
                                                <img src="{{ Storage::disk('public')->url($package->photo_path) }}" alt="{{ $package->name }}" class="h-56 w-full object-cover">
                                            @else
                                                <div class="flex h-56 items-center justify-center bg-stone-900 text-6xl text-stone-600">P</div>
                                            @endif
                                        </div>

                                        <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <p class="text-sm uppercase tracking-[0.3em] text-amber-200">Package</p>
                                                    <h3 class="mt-2 text-2xl font-semibold">{{ $package->name }}</h3>
                                                </div>
                                                <p class="text-lg font-semibold text-amber-100">
                                                    @if ($customerPackageDiscountPercentage > 0)
                                                        <span class="mr-1 text-sm font-medium text-stone-400 line-through">${{ number_format($packageFromPrice, 2) }}</span>
                                                    @endif
                                                    From ${{ number_format($discountedFromPrice, 2) }}
                                                </p>
                                            </div>
                                            <p class="mt-4 text-sm leading-6 text-stone-300">{{ $package->description ?: 'No package description provided yet.' }}</p>
                                            @if ($package->hourlyPrices->isNotEmpty())
                                                <div class="mt-4 rounded-2xl border border-white/10 bg-stone-950/60 p-4">
                                                    <p class="text-xs uppercase tracking-[0.3em] text-cyan-200">Hourly pricing</p>
                                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                                        @foreach ($package->hourlyPrices->sortBy('hours') as $hourlyPrice)
                                                            <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                                                                <p class="text-sm font-semibold text-white">{{ rtrim(rtrim(number_format((float) $hourlyPrice->hours, 2), '0'), '.') }} hours</p>
                                                                <p class="mt-1 text-sm text-amber-100">${{ number_format((float) $hourlyPrice->price, 2) }}</p>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                                            <p class="text-sm uppercase tracking-[0.3em] text-cyan-200">Included Equipment</p>
                                            @if ($package->equipment->isNotEmpty())
                                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                                    @foreach ($package->equipment as $equipment)
                                                        <div class="rounded-2xl border border-white/10 bg-stone-950/60 p-4">
                                                            <p class="text-base font-semibold text-white">{{ $equipment->name }}</p>
                                                            <p class="mt-1 text-xs text-stone-400">{{ $equipment->category ?: 'Uncategorized' }}</p>
                                                            <p class="mt-3 text-sm leading-6 text-stone-300">{{ $equipment->description ?: 'No equipment description provided.' }}</p>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="mt-4 text-sm text-stone-400">No equipment has been assigned to this package yet.</p>
                                            @endif
                                        </div>

                                        <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                                            <p class="text-sm uppercase tracking-[0.3em] text-emerald-200">Included Add-Ons</p>
                                            @if ($package->addOns->isNotEmpty())
                                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                                    @foreach ($package->addOns as $packageAddOn)
                                                        <div class="rounded-2xl border border-white/10 bg-stone-950/60 p-4">
                                                            <p class="text-base font-semibold text-white">{{ $packageAddOn->name }}</p>
                                                            <p class="mt-1 text-xs text-stone-400">
                                                                {{ $packageAddOn->sku ?: 'Add-On' }}
                                                                @if ($packageAddOn->duration)
                                                                    · {{ $packageAddOn->duration }}
                                                                @endif
                                                            </p>
                                                            <p class="mt-2 text-sm text-emerald-100">${{ number_format((float) $packageAddOn->unit_price, 2) }}</p>
                                                            <p class="mt-3 text-sm leading-6 text-stone-300">{{ $packageAddOn->description ?: 'No add-on description provided.' }}</p>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="mt-4 text-sm text-stone-400">No add-ons are included with this package yet.</p>
                                            @endif
                                        </div>
                                    </div>
                                </template>
                            @empty
                                <div class="rounded-3xl border border-dashed border-white/15 bg-stone-950/40 p-6 text-sm text-stone-400">
                                    No active packages are available for booking yet.
                                </div>
                            @endforelse
                        </div>
                        @error('package_id')
                            <p class="mt-3 text-xs font-medium text-rose-200">{{ $message }}</p>
                        @enderror

                        <div id="package-tier-summary" class="mt-5 hidden rounded-3xl border border-cyan-300/20 bg-cyan-300/5 p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm uppercase tracking-[0.3em] text-cyan-200">Package Timing</p>
                                    <h3 id="package-tier-title" class="mt-2 text-lg font-semibold text-white">Choose a timing and price option</h3>
                                    <p id="package-tier-selected-label" class="mt-2 text-sm text-stone-300">No timing option selected yet.</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div id="package-tier-selected-price" class="rounded-2xl border border-amber-300/30 bg-stone-950/70 px-4 py-2 text-sm font-semibold text-amber-100">
                                        $0.00
                                    </div>
                                    <button type="button" id="open-package-tier-modal" class="rounded-2xl border border-cyan-300/30 px-4 py-2 text-sm font-semibold text-cyan-100 transition hover:bg-cyan-300/10">
                                        Choose timing
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-between">
                            <button type="button" class="rounded-2xl border border-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/5" data-wizard-prev>
                                Back
                            </button>
                            <button type="button" class="rounded-2xl bg-cyan-300 px-5 py-3 text-sm font-semibold text-stone-950 transition hover:bg-cyan-200" data-wizard-next>
                                Continue to Add-Ons
                            </button>
                        </div>
                        @error('package_hourly_price_id')
                            <p class="mt-3 text-xs font-medium text-rose-200">{{ $message }}</p>
                        @enderror
                    </section>

                    <section class="rounded-[2rem] border border-white/10 bg-white/5 p-6" data-wizard-step="3" hidden>
                        <div class="mb-5">
                            <h2 class="text-lg font-semibold text-white">
                                <span class="text-emerald-200">Choose Add-Ons</span>
                                <span class="mx-2 text-stone-500">•</span>
                                <span>Optional extras</span>
                                <span class="mx-2 text-stone-500">•</span>
                                <span class="text-sm font-normal text-stone-300">Select any add-ons you would like included with your booking.</span>
                            </h2>
                        </div>

                        @if (($addOnCategories ?? collect())->isNotEmpty())
                            <div class="mb-5 flex flex-wrap gap-2" data-addon-category-filters>
                                <button type="button" class="rounded-full border border-emerald-300/40 bg-emerald-300/15 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-100 transition hover:border-emerald-200/70" data-addon-category-filter="all">
                                    All
                                </button>
                                @foreach ($addOnCategories as $category)
                                    <button type="button" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-300 transition hover:border-emerald-300/40 hover:text-emerald-100" data-addon-category-filter="{{ $category }}">
                                        {{ $category }}
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        <div id="addon-grid" class="overflow-hidden rounded-3xl border border-white/10 bg-stone-950/40">
                            <div class="hidden grid-cols-[3rem_minmax(0,1.6fr)_8rem_8rem_7rem_8rem] gap-3 border-b border-white/10 bg-stone-950/70 px-4 py-3 text-[11px] uppercase tracking-[0.2em] text-stone-500 lg:grid">
                                <span>Select</span>
                                <span>Add-on</span>
                                <span>Category</span>
                                <span>Duration</span>
                                <span class="text-right">Price</span>
                                <span class="text-right">Details</span>
                            </div>
                            @forelse ($addOns as $addOn)
                                <label class="block cursor-pointer border-b border-white/10 last:border-b-0" data-addon-card data-addon-id="{{ $addOn->id }}" data-addon-category="{{ $addOn->addon_category ?: 'Uncategorized' }}">
                                    <input
                                        type="checkbox"
                                        name="add_on_ids[]"
                                        value="{{ $addOn->id }}"
                                        class="peer sr-only"
                                        data-addon-name="{{ $addOn->name }}"
                                        data-addon-price="{{ number_format((float) $addOn->unit_price, 2, '.', '') }}"
                                        data-addon-id="{{ $addOn->id }}"
                                        data-addon-category="{{ $addOn->addon_category ?: 'Uncategorized' }}"
                                        data-addon-description="{{ Str::limit($addOn->description ?: 'No add-on description provided yet.', 120) }}"
                                        data-addon-photo-url="{{ $addOn->photo_path ? Storage::disk('public')->url($addOn->photo_path) : '' }}"
                                        @checked(collect(old('add_on_ids', []))->map(fn ($id) => (int) $id)->contains($addOn->id))
                                    >
                                    <article class="grid gap-3 px-4 py-3 transition peer-checked:bg-emerald-300/10 hover:bg-white/[0.03] lg:grid-cols-[3rem_minmax(0,1.6fr)_8rem_8rem_7rem_8rem] lg:items-center">
                                        <div class="flex items-center justify-between gap-3 lg:justify-center">
                                            <span class="selection-indicator flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-white/20 bg-stone-950/85 text-transparent shadow-lg shadow-black/30 transition">
                                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                                    <path d="M4.5 10.5 8.2 14l7.3-8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                            </span>
                                            <span class="text-xs uppercase tracking-[0.2em] text-stone-500 lg:hidden">Select</span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[11px] uppercase tracking-[0.22em] text-stone-500">{{ $addOn->sku ?: 'Add-On' }}</p>
                                            <h3 class="mt-1 truncate text-sm font-semibold text-white">{{ $addOn->name }}</h3>
                                            <p class="mt-1 line-clamp-2 text-xs leading-5 text-stone-400 lg:hidden">{{ Str::limit($addOn->description ?: 'No description provided.', 90) }}</p>
                                        </div>
                                        <div>
                                            <p class="mb-1 text-[10px] uppercase tracking-[0.2em] text-stone-500 lg:hidden">Category</p>
                                            <span class="inline-flex rounded-full border border-emerald-300/20 bg-emerald-300/10 px-2.5 py-1 text-xs font-medium text-emerald-100">{{ $addOn->addon_category ?: 'Uncategorized' }}</span>
                                        </div>
                                        <div>
                                            <p class="mb-1 text-[10px] uppercase tracking-[0.2em] text-stone-500 lg:hidden">Duration</p>
                                            <span class="text-sm text-stone-300">{{ $addOn->duration ?: 'Not set' }}</span>
                                        </div>
                                        <div>
                                            <p class="mb-1 text-[10px] uppercase tracking-[0.2em] text-stone-500 lg:hidden">Price</p>
                                            <p class="text-sm font-semibold text-emerald-100 lg:text-right">${{ number_format((float) $addOn->unit_price, 2) }}</p>
                                        </div>
                                        <div class="flex lg:justify-end">
                                            <button
                                                type="button"
                                                class="inline-flex rounded-full border border-emerald-300/30 bg-emerald-300/10 px-3 py-1.5 text-xs font-medium text-emerald-100 transition hover:border-emerald-200/60 hover:bg-emerald-300/20"
                                                data-details-modal="addon-details-{{ $addOn->id }}"
                                                data-details-name="{{ $addOn->name }}"
                                                data-details-label="Add-On Details"
                                            >
                                                View details
                                            </button>
                                        </div>
                                    </article>
                                </label>

                                <template id="addon-details-{{ $addOn->id }}">
                                    <div class="space-y-5">
                                        <div class="overflow-hidden rounded-3xl border border-white/10 bg-stone-950/60">
                                            @if ($addOn->photo_path)
                                                <img src="{{ Storage::disk('public')->url($addOn->photo_path) }}" alt="{{ $addOn->name }}" class="h-56 w-full object-cover">
                                            @else
                                                <div class="flex h-56 items-center justify-center bg-stone-900 text-6xl text-stone-600">A</div>
                                            @endif
                                        </div>

                                        <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <p class="text-sm uppercase tracking-[0.3em] text-emerald-200">Add-On</p>
                                                    <h3 class="mt-2 text-2xl font-semibold">{{ $addOn->name }}</h3>
                                                </div>
                                                <p class="text-lg font-semibold text-emerald-100">${{ number_format((float) $addOn->unit_price, 2) }}</p>
                                            </div>
                                            <div class="mt-4 flex flex-wrap gap-3 text-xs uppercase tracking-[0.2em] text-stone-400">
                                                <span>{{ $addOn->sku ?: 'Add-On' }}</span>
                                                @if ($addOn->duration)
                                                    <span>{{ $addOn->duration }}</span>
                                                @endif
                                            </div>
                                            <p class="mt-4 text-sm leading-6 text-stone-300">{{ $addOn->description ?: 'No add-on description provided yet.' }}</p>
                                        </div>
                                    </div>
                                </template>
                            @empty
                                <div class="p-6 text-sm text-stone-400">
                                    No add-ons are available yet.
                                </div>
                            @endforelse
                        </div>
                        <div id="addon-filter-empty" class="mt-4 hidden rounded-3xl border border-dashed border-white/15 bg-stone-950/40 p-6 text-sm text-stone-400">
                            No add-ons match this category.
                        </div>

                        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-between">
                            <button type="button" class="rounded-2xl border border-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/5" data-wizard-prev>
                                Back
                            </button>
                            <button type="button" class="rounded-2xl bg-cyan-300 px-5 py-3 text-sm font-semibold text-stone-950 transition hover:bg-cyan-200" data-wizard-next>
                                Continue to Location
                            </button>
                        </div>
                    </section>

                    <select id="discount-id" name="discount_id" class="hidden" aria-hidden="true" tabindex="-1">
                        <option value="">No discount</option>
                        @foreach ($discounts as $discount)
                            <option
                                value="{{ $discount->id }}"
                                data-discount-code="{{ $discount->code }}"
                                data-discount-name="{{ $discount->name }}"
                                data-discount-type="{{ $discount->discount_type }}"
                                data-discount-value="{{ number_format((float) $discount->discount_value, 2, '.', '') }}"
                                data-starts-at="{{ $discount->starts_at?->format('Y-m-d') }}"
                                data-ends-at="{{ $discount->ends_at?->format('Y-m-d') }}"
                                data-package-ids='@json($discount->packages->pluck('id')->values()->all())'
                                @selected((int) old('discount_id') === $discount->id)
                            >
                                {{ $discount->code }} - {{ $discount->name }}
                            </option>
                        @endforeach
                    </select>
                    <p id="discount-amount-label" class="hidden">-$0.00</p>
                    <p id="discount-note" class="hidden">No discount selected.</p>

                    <section class="rounded-[2rem] border border-white/10 bg-white/5 p-6" data-wizard-step="4" hidden>
                        <div class="mb-5">
                            <h2 class="text-lg font-semibold text-white">
                                <span class="text-cyan-200">Location Address</span>
                                <span class="mx-2 text-stone-500">&bull;</span>
                                <span>Calculate travel fee</span>
                            </h2>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                            <div class="min-w-0">
                                <label class="mb-2 block text-sm text-stone-300" for="event-location-travel">Event location <span class="text-rose-300" aria-hidden="true">*</span></label>
                                <input id="event-location-travel" type="text" value="{{ old('event_location') }}" data-google-address="true" data-location-mirror autocomplete="street-address" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50" required>
                                <p class="mt-2 hidden text-xs font-medium text-rose-200" data-validation-for="event_location_travel"></p>
                                <h2 class="sr-only">
                                    <span class="text-cyan-200">Travel Fee</span>
                                    <span class="mx-2 text-stone-500">•</span>
                                    <span>Auto distance pricing</span>
                                </h2>
                                <p id="travel-fee-note" class="mt-1 text-xs text-stone-400">
                                    {{ $workspaceAddress ? 'Round trip from '.$workspaceAddress.'. '.number_format((float) $travelFreeKilometers, 2).' km free each way, then $'.number_format((float) $travelFeePerKilometer, 2).' per km.' : 'Set your workspace address and travel rate in Settings to enable travel pricing.' }}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 text-right">
                                <div class="rounded-2xl border border-white/10 bg-stone-950/50 px-4 py-3">
                                    <p class="text-[10px] uppercase tracking-[0.24em] text-stone-500">Chargeable</p>
                                    <p id="travel-distance-label" class="mt-1 text-sm font-semibold text-cyan-200">{{ old('travel_distance_km', '0.00') }} km</p>
                                </div>
                                <div class="rounded-2xl border border-white/10 bg-stone-950/50 px-4 py-3">
                                    <p class="text-[10px] uppercase tracking-[0.24em] text-stone-500">Travel Fee</p>
                                    <p id="travel-fee-label" class="mt-1 text-sm font-semibold text-cyan-200">${{ number_format((float) old('travel_fee', 0), 2) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-between">
                            <button type="button" class="rounded-2xl border border-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/5" data-wizard-prev>
                                Back
                            </button>
                            <button type="button" class="rounded-2xl bg-cyan-300 px-5 py-3 text-sm font-semibold text-stone-950 transition hover:bg-cyan-200" data-wizard-next>
                                Continue to Summary
                            </button>
                        </div>
                    </section>

                    <section class="rounded-[2rem] border border-white/10 bg-white/5 p-6" data-wizard-step="5" hidden>
                        <div class="mb-5">
                            <h2 class="text-lg font-semibold text-white">
                                <span class="text-cyan-200">Summary</span>
                                <span class="mx-2 text-stone-500">&bull;</span>
                                <span>Review your booking</span>
                            </h2>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.3fr)_minmax(18rem,0.7fr)]">
                            <div class="space-y-4">
                                <div class="rounded-3xl border border-white/10 bg-stone-950/50 p-5">
                                    <p class="text-sm uppercase tracking-[0.3em] text-cyan-200">Selected Items</p>
                                    <div id="wizard-summary-items" class="mt-4 text-sm text-stone-200">
                                        <p class="text-stone-400">No package selected.</p>
                                    </div>
                                </div>

                                <div class="rounded-3xl border border-white/10 bg-stone-950/50 p-5">
                                    <p class="text-sm uppercase tracking-[0.3em] text-stone-400">Customer and location</p>
                                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                        <div>
                                            <dt class="text-stone-500">Name</dt>
                                            <dd id="wizard-summary-customer" class="mt-1 font-medium text-white">Not entered</dd>
                                        </div>
                                        <div>
                                            <dt class="text-stone-500">Event date</dt>
                                            <dd id="wizard-summary-date" class="mt-1 font-medium text-white">Not selected</dd>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <dt class="text-stone-500">Location</dt>
                                            <dd id="wizard-summary-location" class="mt-1 font-medium text-white">Not entered</dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>

                            <div class="rounded-3xl border border-white/10 bg-stone-950/50 p-5">
                                <p class="text-sm uppercase tracking-[0.3em] text-amber-200">Totals</p>
                                <div class="mt-5 space-y-3 text-sm">
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-stone-400">Package</span>
                                        <span id="wizard-summary-package-total" class="font-medium text-white">$0.00</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-stone-400">Add-ons</span>
                                        <span id="wizard-summary-addon-total" class="font-medium text-white">$0.00</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-stone-400">Travel fee</span>
                                        <span id="wizard-summary-travel" class="font-medium text-white">$0.00</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-4 border-t border-white/10 pt-4">
                                        <span class="text-stone-300">Total</span>
                                        <span id="wizard-summary-total" class="text-2xl font-semibold text-cyan-200">$0.00</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-stone-400">Deposit</span>
                                        <span id="wizard-summary-deposit" class="font-semibold text-amber-200">$0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <button type="button" class="rounded-2xl border border-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/5" data-wizard-prev>
                                Back
                            </button>
                            <div class="grid flex-1 gap-3 sm:max-w-xl sm:grid-cols-2">
                                <button
                                    type="submit"
                                    class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:border-cyan-300/40 hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-60"
                                    @disabled($packages->isEmpty())
                                >
                                    Get Quote
                                </button>
                                <button
                                    type="button"
                                    id="open-book-now-modal"
                                    class="w-full rounded-2xl bg-cyan-300 px-4 py-3 text-sm font-semibold text-stone-950 transition hover:bg-cyan-200 disabled:cursor-not-allowed disabled:opacity-60"
                                    @disabled($packages->isEmpty())
                                >
                                    Book Now
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            </form>
        </main>

        <div id="details-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-stone-950/85 p-4 backdrop-blur-sm">
            <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-[2rem] border border-white/10 bg-stone-950 shadow-2xl shadow-black/40">
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-white/10 bg-stone-950/95 px-6 py-4 backdrop-blur">
                    <div>
                        <p id="details-modal-label" class="text-sm uppercase tracking-[0.3em] text-amber-200">Details</p>
                        <h2 id="details-modal-title" class="mt-2 text-2xl font-semibold">Selected item</h2>
                    </div>
                    <button type="button" id="details-modal-close" class="rounded-2xl border border-white/10 px-4 py-2 text-sm font-medium text-stone-200 transition hover:border-amber-300/40 hover:text-white">
                        Close
                    </button>
                </div>
                <div id="details-modal-content" class="p-6"></div>
            </div>
        </div>

        <div id="book-now-modal" class="fixed inset-0 z-[85] hidden items-center justify-center bg-stone-950/55 p-4 backdrop-blur-[2px]">
            <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-[2rem] border border-white/10 bg-stone-950/95 shadow-2xl shadow-black/40">
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-white/10 bg-stone-950/90 px-6 py-4 backdrop-blur">
                    <div>
                        <p class="text-sm uppercase tracking-[0.3em] text-cyan-200">Book Now</p>
                        <h2 class="mt-2 text-2xl font-semibold">Confirm your booking and pay the deposit</h2>
                    </div>
                    <button type="button" id="book-now-modal-close" class="rounded-2xl border border-white/10 px-4 py-2 text-sm font-medium text-stone-200 transition hover:border-cyan-300/40 hover:text-white">
                        Close
                    </button>
                </div>

                <div class="space-y-5 p-6">
                    <section class="rounded-3xl border border-cyan-300/20 bg-cyan-300/10 p-5">
                        <p class="text-sm uppercase tracking-[0.3em] text-cyan-200">Selected Package</p>
                        <div class="mt-3 flex items-start justify-between gap-4">
                            <div class="flex min-w-0 items-center gap-4">
                                <div id="book-now-package-thumbnail" class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-stone-900 text-3xl font-semibold text-stone-600">P</div>
                                <div class="min-w-0">
                                    <h3 id="book-now-package-name" class="text-2xl font-semibold text-white">No package selected</h3>
                                    <p class="mt-2 text-sm text-stone-300">This will be used to create your booking, invoice, and deposit payment.</p>
                                </div>
                            </div>
                            <p id="book-now-package-price" class="text-xl font-semibold text-cyan-100">$0.00</p>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-white/10 bg-white/5 p-5">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm uppercase tracking-[0.3em] text-emerald-200">Selected Add-Ons</p>
                                <p class="mt-2 text-sm text-stone-300">Optional items included in this checkout.</p>
                            </div>
                            <p id="book-now-addon-total" class="text-lg font-semibold text-emerald-100">$0.00</p>
                        </div>
                        <div id="book-now-addon-list" class="mt-4 space-y-3 text-sm text-stone-200">
                            <p class="text-stone-400">No add-ons selected.</p>
                        </div>
                    </section>

                    <p id="book-now-discount-name" class="hidden">No discount selected.</p>
                    <p id="book-now-discount-amount" class="hidden">-$0.00</p>

                    <section class="rounded-3xl border border-white/10 bg-white/5 p-5">
                        <label class="text-sm uppercase tracking-[0.3em] text-violet-200" for="book-now-discount-code">
                            Discount Code
                        </label>
                        <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                            <input
                                id="book-now-discount-code"
                                type="text"
                                autocomplete="off"
                                placeholder="Enter code"
                                class="min-w-0 flex-1 rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-sm text-white outline-none transition placeholder:text-stone-500 focus:border-violet-300/50"
                            >
                            <button type="button" id="book-now-discount-apply" class="rounded-2xl border border-violet-300/30 px-4 py-3 text-sm font-semibold text-violet-100 transition hover:bg-violet-300/10">
                                Apply
                            </button>
                        </div>
                        <p id="book-now-discount-feedback" class="mt-3 text-sm text-stone-400">
                            Enter a discount code if you have one.
                        </p>
                    </section>

                    <section class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                            <p class="text-sm uppercase tracking-[0.3em] text-amber-200">Deposit Due Today</p>
                            <p id="book-now-deposit-amount" class="mt-3 text-3xl font-semibold text-white">$0.00</p>
                            <p class="mt-2 text-sm text-stone-300">
                                Deposit percentage: {{ number_format((float) config('invoicing.deposit_percentage', 30), 0) }}%
                            </p>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                            <p class="text-sm uppercase tracking-[0.3em] text-stone-400">Booking Total</p>
                            <p id="book-now-total-amount" class="mt-3 text-3xl font-semibold text-cyan-200">$0.00</p>
                            <p class="mt-2 text-sm text-stone-300">The balance invoice will be created automatically and paid later through your invoice link.</p>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-white/10 bg-white/5 p-5">
                        <label class="flex items-start gap-3 text-sm text-stone-200" for="terms-accepted">
                            <input id="terms-accepted" type="checkbox" class="mt-1 h-4 w-4 rounded border-white/20 bg-stone-950 text-cyan-300 focus:ring-cyan-300">
                            <span>
                                I agree to the
                                <a href="{{ $termsUrl }}" target="_blank" rel="noopener noreferrer" class="font-medium text-cyan-200 underline decoration-cyan-300/50 underline-offset-4 hover:text-cyan-100">
                                    terms and conditions
                                </a>
                                and understand that clicking confirm will create my booking invoice and send me to Stripe to pay the deposit.
                            </span>
                        </label>
                        <p id="book-now-terms-error" class="mt-3 hidden text-sm text-rose-200">
                            Please accept the terms and conditions before continuing.
                        </p>
                    </section>

                    <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                        <button type="button" id="book-now-cancel" class="rounded-2xl border border-white/10 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/5">
                            Cancel
                        </button>
                        <button type="button" id="book-now-confirm" class="rounded-2xl bg-cyan-300 px-4 py-3 text-sm font-semibold text-stone-950 transition hover:bg-cyan-200">
                            Confirm and Pay Deposit
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="package-tier-modal" class="fixed inset-0 z-[84] hidden items-center justify-center bg-stone-950/55 p-4 backdrop-blur-[2px]">
            <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-[2rem] border border-white/10 bg-stone-950/95 shadow-2xl shadow-black/40">
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-white/10 bg-stone-950/90 px-6 py-4 backdrop-blur">
                    <div>
                        <p class="text-sm uppercase tracking-[0.3em] text-cyan-200">Package Timing</p>
                        <h2 id="package-tier-modal-title" class="mt-2 text-2xl font-semibold">Choose a timing and price option</h2>
                    </div>
                    <button type="button" id="package-tier-modal-close" class="rounded-2xl border border-white/10 px-4 py-2 text-sm font-medium text-stone-200 transition hover:border-cyan-300/40 hover:text-white">
                        Close
                    </button>
                </div>
                <div class="space-y-5 p-6">
                    <p class="text-sm leading-6 text-stone-300">Some packages have different prices depending on the booking duration. Choose the option you want included with your booking.</p>
                    <div id="package-tier-options" class="grid gap-3 md:grid-cols-2"></div>
                </div>
            </div>
        </div>

        <script>
            (() => {
                const modal = document.getElementById('details-modal');
                const modalLabel = document.getElementById('details-modal-label');
                const modalTitle = document.getElementById('details-modal-title');
                const modalContent = document.getElementById('details-modal-content');
                const closeButton = document.getElementById('details-modal-close');
                const triggers = document.querySelectorAll('[data-details-modal]');
                const summaryPackage = document.getElementById('booking-summary-package');
                const summaryTotal = document.getElementById('booking-summary-total');
                const summaryTravel = document.getElementById('booking-summary-travel');
                const summaryDiscount = document.getElementById('booking-summary-discount');
                const summaryDeposit = document.getElementById('booking-summary-deposit');
                const bookingCartToggle = document.getElementById('booking-cart-toggle');
                const bookingCartClose = document.getElementById('booking-cart-close');
                const bookingCartPanel = document.getElementById('booking-cart-panel');
                const bookingCartCount = document.getElementById('booking-cart-count');
                const bookingCartContent = document.getElementById('booking-cart-content');
                const toast = document.getElementById('booking-toast');
                const toastMessage = document.getElementById('booking-toast-message');
                const packageInputs = document.querySelectorAll('input[name="package_id"]');
                const addOnInputs = document.querySelectorAll('input[name="add_on_ids[]"]');
                const addOnCategoryButtons = document.querySelectorAll('[data-addon-category-filter]');
                const addOnCards = document.querySelectorAll('[data-addon-card]');
                const addOnFilterEmpty = document.getElementById('addon-filter-empty');
                const form = document.querySelector('form[action="{{ route('bookings.store') }}"]');
                const leadTokenInput = document.getElementById('lead-token');
                const packageHourlyPriceIdInput = document.getElementById('package-hourly-price-id');
                const travelDistanceInput = document.getElementById('travel-distance-km');
                const travelFeeInput = document.getElementById('travel-fee');
                const totalHoursInput = document.getElementById('total-hours');
                const totalHoursDisplay = document.getElementById('total-hours-display');
                const eventLocationInput = document.getElementById('event-location');
                const eventLocationMirrorInput = document.getElementById('event-location-travel');
                const startTimeInput = document.getElementById('start-time');
                const endTimeInput = document.getElementById('end-time');
                const endTimeDisplay = document.getElementById('end-time-display');
                const packageTierSummary = document.getElementById('package-tier-summary');
                const packageTierTitle = document.getElementById('package-tier-title');
                const packageTierSelectedLabel = document.getElementById('package-tier-selected-label');
                const packageTierSelectedPrice = document.getElementById('package-tier-selected-price');
                const packageTierModal = document.getElementById('package-tier-modal');
                const packageTierModalTitle = document.getElementById('package-tier-modal-title');
                const openPackageTierModalButton = document.getElementById('open-package-tier-modal');
                const closePackageTierModalButton = document.getElementById('package-tier-modal-close');
                const packageTierOptions = document.getElementById('package-tier-options');
                const autosaveStatus = document.getElementById('lead-autosave-status');
                const travelDistanceLabel = document.getElementById('travel-distance-label');
                const travelFeeLabel = document.getElementById('travel-fee-label');
                const travelFeeNote = document.getElementById('travel-fee-note');
                const discountSelect = document.getElementById('discount-id');
                const discountAmountLabel = document.getElementById('discount-amount-label');
                const discountNote = document.getElementById('discount-note');
                const openBookNowButton = document.getElementById('open-book-now-modal');
                const bookNowModal = document.getElementById('book-now-modal');
                const closeBookNowButton = document.getElementById('book-now-modal-close');
                const cancelBookNowButton = document.getElementById('book-now-cancel');
                const confirmBookNowButton = document.getElementById('book-now-confirm');
                const termsAcceptedCheckbox = document.getElementById('terms-accepted');
                const termsError = document.getElementById('book-now-terms-error');
                const bookNowPackageThumbnail = document.getElementById('book-now-package-thumbnail');
                const bookNowPackageName = document.getElementById('book-now-package-name');
                const bookNowPackagePrice = document.getElementById('book-now-package-price');
                const bookNowAddonList = document.getElementById('book-now-addon-list');
                const bookNowAddonTotal = document.getElementById('book-now-addon-total');
                const bookNowDiscountName = document.getElementById('book-now-discount-name');
                const bookNowDiscountAmount = document.getElementById('book-now-discount-amount');
                const bookNowDiscountCode = document.getElementById('book-now-discount-code');
                const bookNowDiscountApply = document.getElementById('book-now-discount-apply');
                const bookNowDiscountFeedback = document.getElementById('book-now-discount-feedback');
                const bookNowDepositAmount = document.getElementById('book-now-deposit-amount');
                const bookNowTotalAmount = document.getElementById('book-now-total-amount');
                const initialWizardStep = Number(@json($initialWizardStep)) || 1;
                const wizardSteps = Array.from(document.querySelectorAll('[data-wizard-step]'));
                const wizardNavButtons = Array.from(document.querySelectorAll('[data-wizard-nav]'));
                const wizardConnectors = Array.from(document.querySelectorAll('[data-wizard-connector]'));
                const wizardNextButtons = Array.from(document.querySelectorAll('[data-wizard-next]'));
                const wizardPrevButtons = Array.from(document.querySelectorAll('[data-wizard-prev]'));
                const wizardSummaryItems = document.getElementById('wizard-summary-items');
                const wizardSummaryCustomer = document.getElementById('wizard-summary-customer');
                const wizardSummaryDate = document.getElementById('wizard-summary-date');
                const wizardSummaryLocation = document.getElementById('wizard-summary-location');
                const wizardSummaryPackageTotal = document.getElementById('wizard-summary-package-total');
                const wizardSummaryAddonTotal = document.getElementById('wizard-summary-addon-total');
                const wizardSummaryTravel = document.getElementById('wizard-summary-travel');
                const wizardSummaryTotal = document.getElementById('wizard-summary-total');
                const wizardSummaryDeposit = document.getElementById('wizard-summary-deposit');
                const customerNameInput = document.getElementById('customer-name');
                const customerEmailInput = document.getElementById('customer-email');
                const customerPhoneInput = document.getElementById('customer-phone');
                const autosaveFields = [
                    customerNameInput,
                    customerEmailInput,
                    customerPhoneInput,
                    document.getElementById('event-date'),
                    document.getElementById('event-location'),
                    document.getElementById('event-location-travel'),
                    document.getElementById('booking-notes'),
                ].filter(Boolean);

                if (!modal || !modalLabel || !modalTitle || !modalContent || !closeButton || !summaryPackage || !summaryTotal || !summaryTravel || !summaryDiscount || !summaryDeposit || !bookingCartToggle || !bookingCartClose || !bookingCartPanel || !bookingCartCount || !bookingCartContent || !toast || !toastMessage || !form || !leadTokenInput || !packageHourlyPriceIdInput || !travelDistanceInput || !travelFeeInput || !totalHoursInput || !totalHoursDisplay || !eventLocationInput || !eventLocationMirrorInput || !startTimeInput || !endTimeInput || !endTimeDisplay || !packageTierSummary || !packageTierTitle || !packageTierSelectedLabel || !packageTierSelectedPrice || !packageTierModal || !packageTierModalTitle || !openPackageTierModalButton || !closePackageTierModalButton || !packageTierOptions || !autosaveStatus || !travelDistanceLabel || !travelFeeLabel || !travelFeeNote || !discountSelect || !discountAmountLabel || !discountNote || !bookNowModal || !openBookNowButton || !closeBookNowButton || !cancelBookNowButton || !confirmBookNowButton || !termsAcceptedCheckbox || !termsError || !bookNowPackageThumbnail || !bookNowPackageName || !bookNowPackagePrice || !bookNowAddonList || !bookNowAddonTotal || !bookNowDiscountName || !bookNowDiscountAmount || !bookNowDiscountCode || !bookNowDiscountApply || !bookNowDiscountFeedback || !bookNowDepositAmount || !bookNowTotalAmount || wizardSteps.length !== 5 || !wizardSummaryItems || !wizardSummaryCustomer || !wizardSummaryDate || !wizardSummaryLocation || !wizardSummaryPackageTotal || !wizardSummaryAddonTotal || !wizardSummaryTravel || !wizardSummaryTotal || !wizardSummaryDeposit) {
                    return;
                }

                let toastTimeout;
                const toastClasses = {
                    warning: 'border-amber-300/30 bg-amber-300/15 text-amber-100',
                    success: 'border-emerald-300/30 bg-emerald-300/15 text-emerald-100',
                    error: 'border-rose-300/30 bg-rose-300/15 text-rose-100',
                };

                const showToast = (message, tone = 'warning') => {
                    toastMessage.textContent = message;
                    toast.className = `pointer-events-none fixed right-4 top-24 z-[90] max-w-sm rounded-2xl border px-4 py-3 text-sm shadow-2xl shadow-black/30 backdrop-blur ${toastClasses[tone] || toastClasses.warning}`;
                    toast.classList.remove('hidden');
                    window.clearTimeout(toastTimeout);
                    toastTimeout = window.setTimeout(() => {
                        toast.classList.add('hidden');
                    }, 2800);
                };

                const customerSessionStorageKey = `memoshot-booking-customer:${@json($tenant?->slug ?: 'default')}`;

                const readCustomerSessionDetails = () => {
                    try {
                        const raw = window.sessionStorage.getItem(customerSessionStorageKey);

                        if (!raw) {
                            return null;
                        }

                        return JSON.parse(raw);
                    } catch {
                        return null;
                    }
                };

                const writeCustomerSessionDetails = () => {
                    if (!customerNameInput || !customerEmailInput || !customerPhoneInput) {
                        return;
                    }

                    const payload = {
                        customer_name: customerNameInput.value.trim(),
                        customer_email: customerEmailInput.value.trim(),
                        customer_phone: customerPhoneInput.value.trim(),
                    };

                    try {
                        if (!payload.customer_name && !payload.customer_email && !payload.customer_phone) {
                            window.sessionStorage.removeItem(customerSessionStorageKey);
                            return;
                        }

                        window.sessionStorage.setItem(customerSessionStorageKey, JSON.stringify(payload));
                    } catch {
                        // Ignore session storage write issues.
                    }
                };

                const restoreCustomerSessionDetails = () => {
                    const stored = readCustomerSessionDetails();

                    if (!stored) {
                        return;
                    }

                    if (customerNameInput && !customerNameInput.value.trim() && stored.customer_name) {
                        customerNameInput.value = stored.customer_name;
                    }

                    if (customerEmailInput && !customerEmailInput.value.trim() && stored.customer_email) {
                        customerEmailInput.value = stored.customer_email;
                    }

                    if (customerPhoneInput && !customerPhoneInput.value.trim() && stored.customer_phone) {
                        customerPhoneInput.value = stored.customer_phone;
                    }
                };

                let currentWizardStep = 1;

                const stepElement = (step) => wizardSteps.find((element) => Number(element.dataset.wizardStep) === step);

                const updateWizardNav = () => {
                    wizardNavButtons.forEach((button) => {
                        const step = Number(button.dataset.wizardNav);
                        const active = step === currentWizardStep;
                        const completed = step < currentWizardStep;
                        const circle = button.querySelector('[data-step-circle]');
                        const label = button.querySelector('[data-step-label]');

                        button.setAttribute('aria-current', active ? 'step' : 'false');
                        circle?.classList.toggle('border-emerald-300/60', active || completed);
                        circle?.classList.toggle('bg-emerald-400/80', active || completed);
                        circle?.classList.toggle('text-stone-950', active || completed);
                        circle?.classList.toggle('border-white/10', !active && !completed);
                        circle?.classList.toggle('bg-stone-950', !active && !completed);
                        circle?.classList.toggle('text-stone-400', !active && !completed);
                        circle?.classList.toggle('shadow-emerald-950/30', active || completed);
                        label?.classList.toggle('text-white', active);
                        label?.classList.toggle('text-emerald-100', completed && !active);
                        label?.classList.toggle('text-stone-300', !active && !completed);
                    });

                    wizardConnectors.forEach((connector) => {
                        const step = Number(connector.dataset.wizardConnector);
                        connector.classList.toggle('w-full', step < currentWizardStep);
                        connector.classList.toggle('w-1/3', step === currentWizardStep);
                        connector.classList.toggle('w-0', step > currentWizardStep);
                    });
                };

                const showWizardStep = (step) => {
                    currentWizardStep = Math.max(1, Math.min(5, step));
                    wizardSteps.forEach((element) => {
                        element.hidden = Number(element.dataset.wizardStep) !== currentWizardStep;
                    });
                    updateWizardNav();
                    updateSummary();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                };

                const fieldLabels = {
                    customer_name: 'Full name',
                    customer_phone: 'Phone number',
                    customer_email: 'Email address',
                    event_date: 'Event date',
                    event_type: 'Event type',
                    event_location: 'Event location',
                    event_location_travel: 'Event location',
                    start_time: 'Start hour',
                    end_time: 'End hour',
                    total_hours: 'Duration',
                };

                const fieldKey = (field) => {
                    if (field.id === 'total-hours-display') {
                        return 'total_hours';
                    }

                    if (field.id === 'event-location-travel') {
                        return 'event_location_travel';
                    }

                    return field.name || field.id;
                };

                const visibleFieldFor = (field) => {
                    if (field.id === 'end-time') {
                        return endTimeDisplay;
                    }

                    return field;
                };

                const fieldValue = (field) => {
                    if (field.id === 'total-hours-display') {
                        return field.value || totalHoursInput.value;
                    }

                    if (field.id === 'event-location-travel') {
                        return field.value || eventLocationInput.value;
                    }

                    return field.value;
                };

                const clearFieldError = (field) => {
                    const key = fieldKey(field);
                    const message = form.querySelector(`[data-validation-for="${key}"]`);
                    const visibleField = visibleFieldFor(field);

                    visibleField.classList.remove('border-rose-300/70');
                    visibleField.classList.add('border-white/10');

                    if (message) {
                        message.textContent = '';
                        message.classList.add('hidden');
                    }
                };

                const setFieldError = (field, messageText) => {
                    const key = fieldKey(field);
                    const message = form.querySelector(`[data-validation-for="${key}"]`);
                    const visibleField = visibleFieldFor(field);

                    visibleField.classList.add('border-rose-300/70');
                    visibleField.classList.remove('border-white/10');

                    if (message) {
                        message.textContent = messageText;
                        message.classList.remove('hidden');
                    }
                };

                const focusInvalidField = (field) => {
                    const visibleField = visibleFieldFor(field);
                    visibleField.focus({ preventScroll: true });
                    visibleField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                };

                const validationMessageFor = (field) => {
                    const key = fieldKey(field);
                    const value = (fieldValue(field) || '').trim();
                    const label = fieldLabels[key] || 'This field';

                    if (field.required && !value) {
                        return `${label} is required.`;
                    }

                    if (key === 'customer_email' && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                        return 'Enter a valid email address.';
                    }

                    if (key === 'event_date' && value) {
                        const localDate = new Date();
                        localDate.setMinutes(localDate.getMinutes() - localDate.getTimezoneOffset());
                        const today = localDate.toISOString().slice(0, 10);

                        if (value < today) {
                            return 'Event date must be today or later.';
                        }
                    }

                    if ((key === 'start_time' || key === 'end_time') && value && !/^\d{2}:(00|30)$/.test(value)) {
                        return `${label} must be in 30 minute intervals.`;
                    }

                    if (key === 'end_time' && value && startTimeInput.value && value <= startTimeInput.value) {
                        return 'End hour must be later than the start hour.';
                    }

                    if (key === 'total_hours' && parseAmount(value) < 0.5) {
                        return 'Duration must be at least 0.5 hours.';
                    }

                    return '';
                };

                const validateField = (field) => {
                    const message = validationMessageFor(field);

                    if (message) {
                        setFieldError(field, message);
                        return false;
                    }

                    clearFieldError(field);
                    return true;
                };

                const validateWizardStep = (step) => {
                    const section = stepElement(step);

                    if (!section) {
                        return true;
                    }

                    let firstInvalidField = null;
                    const fields = Array.from(section.querySelectorAll('input, select, textarea'));

                    for (const field of fields) {
                        if (step === 2 && field.matches('input[name="package_id"]')) {
                            continue;
                        }

                        if (!validateField(field)) {
                            firstInvalidField ??= field;
                        }
                    }

                    if (firstInvalidField) {
                        focusInvalidField(firstInvalidField);
                        return false;
                    }

                    if (step === 2) {
                        const selectedPackage = document.querySelector('input[name="package_id"]:checked');

                        if (!selectedPackage) {
                            showToast('Please choose a package before continuing.', 'error');
                            return false;
                        }

                        let hourlyPrices = [];
                        try {
                            hourlyPrices = JSON.parse(selectedPackage.dataset.packageHourlyPrices || '[]');
                        } catch {
                            hourlyPrices = [];
                        }

                        if (hourlyPrices.length && !selectedPackageTimingOption()) {
                            showToast('Please choose the package timing and price option first.', 'error');
                            openPackageTierModal();
                            return false;
                        }
                    }

                    return true;
                };

                const validateBookingDetails = () => {
                    for (let step = 1; step <= 4; step += 1) {
                        showWizardStep(step);

                        if (!validateWizardStep(step)) {
                            return false;
                        }
                    }

                    showWizardStep(5);
                    return true;
                };

                const escapeHtml = (value) => {
                    const element = document.createElement('div');
                    element.textContent = value || '';

                    return element.innerHTML;
                };

                const renderBookNowPackageThumbnail = (packageInput) => {
                    const photoUrl = packageInput?.dataset.packagePhotoUrl || '';
                    const packageName = packageInput?.dataset.packageName || 'Selected package';

                    bookNowPackageThumbnail.innerHTML = photoUrl
                        ? `<img src="${escapeHtml(photoUrl)}" alt="${escapeHtml(packageName)}" class="h-full w-full object-cover">`
                        : 'P';
                };

                const cartImageMarkup = (photoUrl, name, fallback) => photoUrl
                    ? `<img src="${escapeHtml(photoUrl)}" alt="${escapeHtml(name)}" class="h-12 w-12 rounded-2xl object-cover">`
                    : `<div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-stone-900 text-lg font-semibold text-stone-600">${fallback}</div>`;

                const cartRemoveButton = (type, value, label) =>
                    `<button type="button" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-rose-300/30 bg-rose-300/10 text-sm font-bold leading-none text-rose-100 transition hover:bg-rose-300/20" data-cart-remove-type="${type}" data-cart-remove-value="${escapeHtml(value)}" aria-label="${escapeHtml(label)}">×</button>`;

                const renderBookingCart = () => {
                    const totals = quoteTotals();
                    const count = (totals.selectedPackage ? 1 : 0) + totals.selectedAddOns.length;
                    bookingCartCount.textContent = String(count);

                    if (!totals.selectedPackage && !totals.selectedAddOns.length) {
                        bookingCartContent.innerHTML = '<p class="rounded-2xl border border-dashed border-white/10 bg-white/[0.03] px-4 py-3 text-stone-400">No package selected yet.</p>';
                        return;
                    }

                    const packageMarkup = totals.selectedPackage
                        ? `<div class="rounded-2xl border border-cyan-300/20 bg-cyan-300/10 p-3"><div class="flex items-center justify-between gap-3"><div class="flex min-w-0 items-center gap-3">${cartImageMarkup(totals.selectedPackage.dataset.packagePhotoUrl || '', totals.packageName, 'P')}<div class="min-w-0"><p class="truncate font-semibold text-white">${escapeHtml(totals.packageName)}</p><p class="mt-1 text-xs uppercase tracking-[0.18em] text-cyan-200">Package</p></div></div><div class="flex shrink-0 items-center gap-2"><span class="font-semibold text-cyan-100">${formatCurrency(totals.packageTotal)}</span>${cartRemoveButton('package', totals.selectedPackage.value, 'Remove selected package')}</div></div></div>`
                        : '<p class="rounded-2xl border border-dashed border-white/10 bg-white/[0.03] px-4 py-3 text-stone-400">No package selected.</p>';

                    const addOnMarkup = totals.selectedAddOns.length
                        ? totals.selectedAddOns.map((input) => {
                            const name = input.dataset.addonName || 'Add-On';
                            const category = input.dataset.addonCategory || 'Add-on';
                            const photoUrl = input.dataset.addonPhotoUrl || '';
                            const price = formatCurrency(parseAmount(input.dataset.addonPrice));

                            return `<div class="flex items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/[0.03] p-3"><div class="flex min-w-0 items-center gap-3">${cartImageMarkup(photoUrl, name, 'A')}<div class="min-w-0"><p class="truncate font-medium text-white">${escapeHtml(name)}</p><p class="mt-1 text-xs uppercase tracking-[0.18em] text-emerald-200">${escapeHtml(category)}</p></div></div><div class="flex shrink-0 items-center gap-2"><span class="font-medium text-emerald-100">${price}</span>${cartRemoveButton('addon', input.value, `Remove ${name}`)}</div></div>`;
                        }).join('')
                        : '<p class="rounded-2xl border border-dashed border-white/10 bg-white/[0.03] px-4 py-3 text-stone-400">No add-ons selected.</p>';

                    bookingCartContent.innerHTML = `${packageMarkup}<div class="space-y-2">${addOnMarkup}</div><div class="flex items-center justify-between border-t border-white/10 pt-3"><span class="text-xs uppercase tracking-[0.2em] text-stone-500">Total</span><span class="text-lg font-semibold text-cyan-200">${formatCurrency(totals.total)}</span></div>`;
                };

                const setBookingCartOpen = (open) => {
                    bookingCartPanel.classList.toggle('hidden', !open);
                    bookingCartToggle.setAttribute('aria-expanded', open ? 'true' : 'false');

                    if (open) {
                        renderBookingCart();
                    }
                };

                const updateBookNowAmounts = () => {
                    const totals = bookNowTotals();

                    bookNowPackagePrice.textContent = formatCurrency(totals.packageTotal);
                    bookNowAddonTotal.textContent = formatCurrency(totals.addOnTotal);
                    bookNowDiscountName.textContent = totals.selectedDiscount ? totals.selectedDiscount.textContent.trim() : 'No discount selected.';
                    bookNowDiscountAmount.textContent = `-${formatCurrency(totals.discountAmount)}`;
                    bookNowTotalAmount.textContent = formatCurrency(totals.total);
                    bookNowDepositAmount.textContent = formatCurrency(totals.depositAmount);

                    return totals;
                };

                const setDiscountFeedback = (message, tone = 'neutral') => {
                    const toneClasses = {
                        neutral: 'text-stone-400',
                        success: 'text-emerald-200',
                        error: 'text-rose-200',
                    };

                    bookNowDiscountFeedback.textContent = message;
                    bookNowDiscountFeedback.className = `mt-3 text-sm ${toneClasses[tone] || toneClasses.neutral}`;
                };

                const selectedPackageIncludedAddOnIds = () => {
                    const selectedPackage = document.querySelector('input[name="package_id"]:checked');

                    if (!selectedPackage?.dataset.packageAddonIds) {
                        return [];
                    }

                    try {
                        return JSON.parse(selectedPackage.dataset.packageAddonIds).map((id) => Number(id));
                    } catch {
                        return [];
                    }
                };

                const notifyIncludedAddOns = (inputs) => {
                    const includedIds = selectedPackageIncludedAddOnIds();

                    if (!includedIds.length) {
                        return;
                    }

                    const duplicatedNames = inputs
                        .filter((input) => includedIds.includes(Number(input.dataset.addonId)))
                        .map((input) => input.dataset.addonName || 'This add-on');

                    if (!duplicatedNames.length) {
                        return;
                    }

                    showToast(`${duplicatedNames.join(', ')} ${duplicatedNames.length > 1 ? 'are' : 'is'} already included in the selected package.`);
                };

                const normalizeCategory = (value) => String(value || '').trim().toLowerCase();
                let activeAddOnCategory = 'all';

                const syncPackageIncludedAddOnVisibility = () => {
                    const includedIds = selectedPackageIncludedAddOnIds();

                    addOnCards.forEach((card) => {
                        const addOnId = Number(card.dataset.addonId);
                        const includedInPackage = includedIds.includes(addOnId);
                        const input = card.querySelector('input[name="add_on_ids[]"]');

                        card.dataset.includedInPackage = includedInPackage ? 'true' : 'false';

                        if (includedInPackage && input) {
                            input.checked = false;
                        }
                    });
                };

                const applyAddOnCategoryFilter = (category) => {
                    activeAddOnCategory = category || 'all';
                    const selectedCategory = normalizeCategory(category);
                    let visibleCount = 0;

                    addOnCards.forEach((card) => {
                        const includedInPackage = card.dataset.includedInPackage === 'true';
                        const matchesCategory = selectedCategory === 'all' || normalizeCategory(card.dataset.addonCategory) === selectedCategory;
                        const matches = matchesCategory && !includedInPackage;
                        card.classList.toggle('hidden', !matches);

                        if (matches) {
                            visibleCount += 1;
                        }
                    });

                    addOnCategoryButtons.forEach((button) => {
                        const isActive = normalizeCategory(button.dataset.addonCategoryFilter) === selectedCategory;
                        button.classList.toggle('border-emerald-300/40', isActive);
                        button.classList.toggle('bg-emerald-300/15', isActive);
                        button.classList.toggle('text-emerald-100', isActive);
                        button.classList.toggle('border-white/10', !isActive);
                        button.classList.toggle('bg-white/5', !isActive);
                        button.classList.toggle('text-stone-300', !isActive);
                    });

                    addOnFilterEmpty?.classList.toggle('hidden', visibleCount > 0);
                };

                const closeModal = () => {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    modalContent.innerHTML = '';
                    document.body.classList.remove('overflow-hidden');
                };

                const openModal = (trigger) => {
                    const templateId = trigger.getAttribute('data-details-modal');
                    const template = templateId ? document.getElementById(templateId) : null;

                    if (!(template instanceof HTMLTemplateElement)) {
                        return;
                    }

                    modalLabel.textContent = trigger.getAttribute('data-details-label') || 'Details';
                    modalTitle.textContent = trigger.getAttribute('data-details-name') || 'Selected item';
                    modalContent.innerHTML = template.innerHTML;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    document.body.classList.add('overflow-hidden');
                };

                triggers.forEach((trigger) => {
                    trigger.addEventListener('click', (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        openModal(trigger);
                    });
                });

                const workspaceAddress = @json($workspaceAddress);
                const travelFreeKilometers = {{ (float) $travelFreeKilometers }};
                const travelFeePerKilometer = {{ (float) $travelFeePerKilometer }};
                const depositPercentage = {{ (float) $depositPercentage }};
                const customerPackageDiscountPercentage = {{ $customerPackageDiscountPercentage }};
                const initialPackageTimingId = @json(old('package_hourly_price_id'));
                const requestedPackageId = new URLSearchParams(window.location.search).get('package_id');
                const bookingCurrencyCode = @json($bookingCurrencyCode);
                const formatCurrency = (value) => `$${value.toFixed(2)}`;
                const formatSummaryCurrency = (value) => `${bookingCurrencyCode} ${formatCurrency(value)}`;
                const parseAmount = (value) => Number.parseFloat(value || '0') || 0;
                const applyPackageDiscount = (amount) => {
                    const normalizedAmount = parseAmount(amount);

                    if (!customerPackageDiscountPercentage) {
                        return normalizedAmount;
                    }

                    return normalizedAmount * (1 - (customerPackageDiscountPercentage / 100));
                };
                const normalizeHalfHourTime = (value) => {
                    if (!value || !value.includes(':')) {
                        return value;
                    }

                    const [hourRaw, minuteRaw] = value.split(':');
                    const hour = Number.parseInt(hourRaw, 10);
                    const minute = Number.parseInt(minuteRaw, 10);

                    if (Number.isNaN(hour) || Number.isNaN(minute)) {
                        return value;
                    }

                    let normalizedHour = hour;
                    let normalizedMinute = 0;

                    if (minute >= 15 && minute < 45) {
                        normalizedMinute = 30;
                    } else if (minute >= 45) {
                        normalizedHour = (hour + 1) % 24;
                    }

                    return `${String(normalizedHour).padStart(2, '0')}:${String(normalizedMinute).padStart(2, '0')}`;
                };

                const snapTimeInput = (input) => {
                    const normalizedValue = normalizeHalfHourTime(input.value);

                    if (normalizedValue && normalizedValue !== input.value) {
                        input.value = normalizedValue;
                    }
                };

                const formatTimeLabel = (value) => {
                    if (!value || !value.includes(':')) {
                        return '';
                    }

                    const [hours, minutes] = value.split(':').map(Number);
                    const suffix = hours >= 12 ? 'PM' : 'AM';
                    const hour12 = (hours % 12) || 12;

                    return `${hour12}:${String(minutes).padStart(2, '0')} ${suffix}`;
                };

                const addHoursToTime = (timeValue, hoursToAdd) => {
                    if (!timeValue || !Number.isFinite(hoursToAdd) || hoursToAdd <= 0) {
                        return '';
                    }

                    const [hours, minutes] = timeValue.split(':').map(Number);

                    if (Number.isNaN(hours) || Number.isNaN(minutes)) {
                        return '';
                    }

                    const totalMinutes = Math.round(((hours * 60) + minutes) + (hoursToAdd * 60));
                    const normalizedMinutes = ((totalMinutes % (24 * 60)) + (24 * 60)) % (24 * 60);
                    const resultHours = Math.floor(normalizedMinutes / 60);
                    const resultMinutes = normalizedMinutes % 60;

                    return `${String(resultHours).padStart(2, '0')}:${String(resultMinutes).padStart(2, '0')}`;
                };

                const selectedPackageTimingOption = () => {
                    const selectedPackage = document.querySelector('input[name="package_id"]:checked');
                    const selectedTiming = document.querySelector('input[name="package_hourly_price_id"]:checked');

                    if (selectedPackage && selectedTiming && selectedTiming.dataset.packageId === selectedPackage.value) {
                        return selectedTiming;
                    }

                    if (!selectedPackage || !packageHourlyPriceIdInput.value) {
                        return null;
                    }

                    let hourlyPrices = [];
                    try {
                        hourlyPrices = JSON.parse(selectedPackage.dataset.packageHourlyPrices || '[]');
                    } catch {
                        hourlyPrices = [];
                    }

                    const matchedTiming = hourlyPrices.find((entry) => String(entry.id) === String(packageHourlyPriceIdInput.value));

                    if (!matchedTiming) {
                        return null;
                    }

                    return {
                        dataset: {
                            packageId: selectedPackage.value,
                            price: matchedTiming.price,
                            hours: matchedTiming.hours,
                        },
                        value: String(matchedTiming.id),
                    };
                };

                const findMatchingHourlyPrice = (selectedPackage, duration) => {
                    if (!selectedPackage) {
                        return null;
                    }

                    let hourlyPrices = [];
                    try {
                        hourlyPrices = JSON.parse(selectedPackage.dataset.packageHourlyPrices || '[]');
                    } catch {
                        hourlyPrices = [];
                    }

                    if (!hourlyPrices.length) {
                        return null;
                    }

                    const normalizedDuration = Number(parseAmount(duration).toFixed(2));

                    return hourlyPrices.find((entry) => Number(parseAmount(entry.hours).toFixed(2)) === normalizedDuration) || null;
                };

                const syncDurationFromSelectedTiming = (timingOption) => {
                    if (!timingOption) {
                        return;
                    }

                    const timingHours = parseAmount(timingOption.dataset.hours);

                    if (timingHours <= 0) {
                        return;
                    }

                    const computedEndTime = startTimeInput.value ? addHoursToTime(startTimeInput.value, timingHours) : '';

                    packageHourlyPriceIdInput.value = String(timingOption.value || '');
                    totalHoursInput.value = timingHours.toFixed(2);
                    totalHoursDisplay.value = timingHours.toFixed(2);
                    endTimeInput.value = computedEndTime;
                    endTimeDisplay.value = formatTimeLabel(computedEndTime);
                };

                const packagePriceForSelection = (selectedPackage) => {
                    if (!selectedPackage) {
                        return 0;
                    }

                    let hourlyPrices = [];
                    try {
                        hourlyPrices = JSON.parse(selectedPackage.dataset.packageHourlyPrices || '[]');
                    } catch {
                        hourlyPrices = [];
                    }

                    const selectedTiming = selectedPackageTimingOption();

                    if (selectedTiming) {
                        return applyPackageDiscount(selectedTiming.dataset.price);
                    }

                    const matchedTiming = findMatchingHourlyPrice(selectedPackage, totalHoursInput.value);

                    if (matchedTiming) {
                        return applyPackageDiscount(matchedTiming.price);
                    }

                    if (hourlyPrices.length) {
                        return 0;
                    }

                    return applyPackageDiscount(selectedPackage.dataset.packagePrice);
                };

                const selectedDiscountOption = () => {
                    const option = discountSelect.options[discountSelect.selectedIndex];

                    if (!option || !option.value) {
                        return null;
                    }

                    return option;
                };

                const selectedDiscountPackageIds = (option) => {
                    if (!option?.dataset.packageIds) {
                        return [];
                    }

                    try {
                        return JSON.parse(option.dataset.packageIds).map((id) => Number(id));
                    } catch {
                        return [];
                    }
                };

                const todayDate = @json(now()->toDateString());

                const todayWithinDiscountRange = (option) => {
                    const startsAt = option.dataset.startsAt || '';
                    const endsAt = option.dataset.endsAt || '';

                    if (!startsAt || !endsAt) {
                        return false;
                    }

                    return todayDate >= startsAt && todayDate <= endsAt;
                };

                const discountAmountForSelection = (selectedPackage, packageTotal) => {
                    const option = selectedDiscountOption();

                    if (!option) {
                        return 0;
                    }

                    const packageIds = selectedDiscountPackageIds(option);
                    const packageMatch = selectedPackage ? packageIds.includes(Number(selectedPackage.value)) : false;
                    const applicableSubtotal = packageMatch ? packageTotal : 0;

                    if (applicableSubtotal <= 0) {
                        return 0;
                    }

                    const discountValue = parseAmount(option.dataset.discountValue);

                    if (option.dataset.discountType === 'percentage') {
                        return Math.min(applicableSubtotal, applicableSubtotal * (discountValue / 100));
                    }

                    return Math.min(applicableSubtotal, discountValue);
                };

                const clearInvalidDiscountSelection = () => {
                    const selectedPackage = document.querySelector('input[name="package_id"]:checked');
                    const selectedOption = selectedDiscountOption();

                    if (!selectedOption) {
                        return;
                    }

                    const packageIds = selectedDiscountPackageIds(selectedOption);
                    const dateMatch = todayWithinDiscountRange(selectedOption);
                    const packageMatch = selectedPackage ? packageIds.includes(Number(selectedPackage.value)) : false;

                    if (!dateMatch || !packageMatch) {
                        discountSelect.value = '';
                    }
                };

                const refreshDiscountOptions = () => {
                    const selectedPackage = document.querySelector('input[name="package_id"]:checked');
                    let hasApplicableOption = false;

                    Array.from(discountSelect.options).forEach((option, index) => {
                        if (index === 0 || !option.value) {
                            option.disabled = false;
                            return;
                        }

                        const packageIds = selectedDiscountPackageIds(option);
                        const dateMatch = todayWithinDiscountRange(option);
                        const applicable = selectedPackage ? packageIds.includes(Number(selectedPackage.value)) && dateMatch : false;

                        option.disabled = !applicable;
                        hasApplicableOption = hasApplicableOption || applicable;
                    });

                    if (selectedDiscountOption()?.disabled) {
                        discountSelect.value = '';
                    }

                    if (!selectedPackage) {
                        discountNote.textContent = 'Choose a package to see valid discount options.';
                    } else if (!hasApplicableOption) {
                        discountNote.textContent = 'No discount currently applies to this package today.';
                    } else if (!discountSelect.value) {
                        discountNote.textContent = 'Choose an offer if you would like to apply discount savings.';
                    }
                };

                const applyDiscountCode = () => {
                    const code = bookNowDiscountCode.value.trim();

                    if (!code) {
                        discountSelect.value = '';
                        updateSummary();
                        updateBookNowAmounts();
                        setDiscountFeedback('Enter a discount code if you have one.');
                        return;
                    }

                    refreshDiscountOptions();

                    const option = Array.from(discountSelect.options).find((entry) =>
                        entry.value && String(entry.dataset.discountCode || '').toLowerCase() === code.toLowerCase(),
                    );

                    if (!option) {
                        discountSelect.value = '';
                        updateSummary();
                        updateBookNowAmounts();
                        setDiscountFeedback('We could not find that discount code.', 'error');
                        return;
                    }

                    if (option.disabled) {
                        discountSelect.value = '';
                        updateSummary();
                        updateBookNowAmounts();
                        setDiscountFeedback('That code is valid, but it does not apply to this package today.', 'error');
                        return;
                    }

                    discountSelect.value = option.value;
                    updateSummary();
                    const totals = updateBookNowAmounts();
                    const label = option.dataset.discountName || option.textContent.trim();

                    if (totals.discountAmount > 0) {
                        setDiscountFeedback(`${label} applied. You save ${formatCurrency(totals.discountAmount)} on this booking.`, 'success');
                    } else {
                        setDiscountFeedback(`${label} was found, but it does not change this booking total.`, 'neutral');
                    }
                };

                const renderPackageTimingOptions = () => {
                    const selectedPackage = document.querySelector('input[name="package_id"]:checked');

                    if (!selectedPackage) {
                        packageTierSummary.classList.add('hidden');
                        packageTierOptions.innerHTML = '';
                        packageTierSelectedPrice.textContent = '$0.00';
                        packageTierSelectedLabel.textContent = 'No timing option selected yet.';
                        return;
                    }

                    let hourlyPrices = [];

                    try {
                        hourlyPrices = JSON.parse(selectedPackage.dataset.packageHourlyPrices || '[]');
                    } catch {
                        hourlyPrices = [];
                    }

                    hourlyPrices = hourlyPrices.sort((left, right) => parseAmount(left.hours) - parseAmount(right.hours));

                    if (!hourlyPrices.length) {
                        packageTierSummary.classList.add('hidden');
                        packageTierOptions.innerHTML = '';
                        packageTierSelectedPrice.textContent = formatCurrency(applyPackageDiscount(selectedPackage.dataset.packagePrice));
                        packageTierSelectedLabel.textContent = 'No timing option selected yet.';
                        return;
                    }

                    packageTierSummary.classList.remove('hidden');
                    packageTierTitle.textContent = `${selectedPackage.dataset.packageName} timing and price`;
                    packageTierModalTitle.textContent = `${selectedPackage.dataset.packageName} timing and price`;

                    const existingSelection = packageHourlyPriceIdInput.value || document.querySelector('input[name="package_hourly_price_id"]:checked')?.value;
                    const selectedTimingId = existingSelection && hourlyPrices.some((entry) => String(entry.id) === String(existingSelection))
                        ? String(existingSelection)
                        : (initialPackageTimingId && hourlyPrices.some((entry) => String(entry.id) === String(initialPackageTimingId)) ? String(initialPackageTimingId) : String(hourlyPrices[0].id));

                    packageTierOptions.innerHTML = hourlyPrices
                        .map((entry) => {
                            const checked = String(entry.id) === selectedTimingId ? 'checked' : '';
                            const hoursLabel = `${parseAmount(entry.hours).toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1')} hours`;
                            const discountedPrice = applyPackageDiscount(entry.price);
                            const priceLabel = formatCurrency(discountedPrice);
                            const originalPriceLabel = customerPackageDiscountPercentage ? `<span class="text-xs font-medium text-stone-500 line-through">${formatCurrency(parseAmount(entry.price))}</span>` : '';

                            return `<label class="block cursor-pointer"><input class="peer sr-only" type="radio" name="package_hourly_price_id" value="${entry.id}" data-package-id="${selectedPackage.value}" data-hours="${entry.hours}" data-price="${entry.price}" ${checked}><div class="rounded-2xl border border-white/10 bg-stone-950/60 px-4 py-3 transition peer-checked:border-cyan-300/60 peer-checked:bg-cyan-300/10"><div class="flex items-center justify-between gap-4"><span class="text-sm font-semibold text-white">${hoursLabel}</span><span class="flex items-center gap-2 text-sm font-semibold text-amber-100">${originalPriceLabel}<span>${priceLabel}</span></span></div></div></label>`;
                        })
                        .join('');

                    const matchedTiming = findMatchingHourlyPrice(selectedPackage, totalHoursInput.value);
                    const activeTiming = selectedPackageTimingOption() ?? (matchedTiming ? {
                        dataset: {
                            price: matchedTiming.price,
                            hours: matchedTiming.hours,
                        },
                        value: String(matchedTiming.id),
                    } : null);
                    packageTierSelectedPrice.textContent = activeTiming
                        ? formatCurrency(applyPackageDiscount(activeTiming.dataset.price))
                        : 'Select an option';
                    packageTierSelectedLabel.textContent = activeTiming
                        ? `${parseAmount(activeTiming.dataset.hours).toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1')} hours selected`
                        : 'No timing option selected yet.';

                    packageTierOptions.querySelectorAll('input[name="package_hourly_price_id"]').forEach((input) => {
                        input.addEventListener('change', () => {
                            syncDurationFromSelectedTiming(input);
                            packageTierSelectedPrice.textContent = formatCurrency(applyPackageDiscount(input.dataset.price));
                            packageTierSelectedLabel.textContent = `${parseAmount(input.dataset.hours).toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1')} hours selected`;
                            updatePackageDrivenTiming();
                            updateSummary();
                            closePackageTierModal();
                        });
                    });

                    const initialSelectedTiming = packageTierOptions.querySelector(`input[name="package_hourly_price_id"][value="${selectedTimingId}"]`);

                    if (initialSelectedTiming) {
                        syncDurationFromSelectedTiming(initialSelectedTiming);
                    }

                    updatePackageDrivenTiming();
                };

                const quoteTotals = () => {
                    const selectedPackage = document.querySelector('input[name="package_id"]:checked');
                    const selectedAddOns = Array.from(document.querySelectorAll('input[name="add_on_ids[]"]:checked'));
                    const packageName = selectedPackage?.dataset.packageName || 'No package selected';
                    const selectedTiming = selectedPackageTimingOption();
                    const packageTotal = packagePriceForSelection(selectedPackage);
                    const addOnTotal = selectedAddOns.reduce((total, input) => total + parseAmount(input.dataset.addonPrice), 0);
                    const travelFee = parseAmount(travelFeeInput.value);
                    const total = Math.max(0, packageTotal + addOnTotal + travelFee);

                    return {
                        selectedPackage,
                        selectedTiming,
                        selectedAddOns,
                        packageName: selectedTiming ? `${packageName} · ${parseAmount(selectedTiming.dataset.hours).toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1')} hrs` : packageName,
                        packageTotal,
                        addOnTotal,
                        travelFee,
                        total,
                        depositAmount: total * (depositPercentage / 100),
                    };
                };

                const bookNowTotals = () => {
                    const totals = quoteTotals();
                    const discountAmount = discountAmountForSelection(totals.selectedPackage, totals.packageTotal);
                    const total = Math.max(0, totals.total - discountAmount);

                    return {
                        ...totals,
                        selectedDiscount: selectedDiscountOption(),
                        discountAmount,
                        total,
                        depositAmount: total * (depositPercentage / 100),
                    };
                };

                const renderWizardSummary = () => {
                    const totals = quoteTotals();
                    const customerName = document.getElementById('customer-name')?.value.trim();
                    const eventDate = document.getElementById('event-date')?.value;
                    const eventLocation = eventLocationInput.value.trim();

                    wizardSummaryPackageTotal.textContent = formatCurrency(totals.packageTotal);
                    wizardSummaryAddonTotal.textContent = formatCurrency(totals.addOnTotal);
                    wizardSummaryTravel.textContent = formatCurrency(totals.travelFee);
                    wizardSummaryTotal.textContent = formatCurrency(totals.total);
                    wizardSummaryDeposit.textContent = formatCurrency(totals.depositAmount);
                    wizardSummaryCustomer.textContent = customerName || 'Not entered';
                    wizardSummaryDate.textContent = eventDate || 'Not selected';
                    wizardSummaryLocation.textContent = eventLocation || 'Not entered';

                    if (!totals.selectedPackage) {
                        wizardSummaryItems.innerHTML = '<p class="text-stone-400">No package selected.</p>';
                        return;
                    }

                    const packageRow = `<tr class="border-t border-white/10"><td class="px-3 py-2.5"><span class="inline-flex rounded-full border border-cyan-300/20 bg-cyan-300/10 px-2.5 py-1 text-xs font-medium text-cyan-100">Package</span></td><td class="max-w-0 px-3 py-2.5"><p class="truncate font-medium text-white">${escapeHtml(totals.packageName)}</p></td><td class="px-3 py-2.5 text-stone-300">Package</td><td class="px-3 py-2.5 text-right font-medium text-cyan-100">${formatCurrency(totals.packageTotal)}</td></tr>`;
                    const addOnRows = totals.selectedAddOns
                        .map((input) => {
                            const name = input.dataset.addonName || 'Add-On';
                            const category = input.dataset.addonCategory || 'Add-on';
                            const price = formatCurrency(parseAmount(input.dataset.addonPrice));

                            return `<tr class="border-t border-white/10"><td class="px-3 py-2.5"><span class="inline-flex rounded-full border border-emerald-300/20 bg-emerald-300/10 px-2.5 py-1 text-xs font-medium text-emerald-100">Add-on</span></td><td class="max-w-0 px-3 py-2.5"><p class="truncate font-medium text-white">${escapeHtml(name)}</p></td><td class="px-3 py-2.5 text-stone-300">${escapeHtml(category)}</td><td class="px-3 py-2.5 text-right font-medium text-emerald-100">${price}</td></tr>`;
                        })
                        .join('');

                    wizardSummaryItems.innerHTML = `<div class="overflow-x-auto rounded-2xl border border-white/10 bg-white/[0.03]"><table class="w-full min-w-[34rem] table-fixed border-collapse text-left text-sm"><thead class="bg-stone-950/60 text-[11px] uppercase tracking-[0.18em] text-stone-500"><tr><th class="w-24 px-3 py-2 font-medium">Type</th><th class="w-1/2 px-3 py-2 font-medium">Name</th><th class="w-1/4 px-3 py-2 font-medium">Category</th><th class="w-28 px-3 py-2 text-right font-medium">Price</th></tr></thead><tbody>${packageRow}${addOnRows}</tbody></table></div>`;
                };

                const updatePackageDrivenTiming = () => {
                    const selectedPackage = document.querySelector('input[name="package_id"]:checked');
                    const enteredDuration = parseAmount(totalHoursDisplay.value);
                    const matchedTiming = findMatchingHourlyPrice(selectedPackage, enteredDuration);
                    const selectedDuration = enteredDuration > 0 ? enteredDuration : 0;
                    const computedEndTime = selectedDuration > 0 ? addHoursToTime(startTimeInput.value, selectedDuration) : '';

                    packageHourlyPriceIdInput.value = matchedTiming ? String(matchedTiming.id) : '';
                    totalHoursInput.value = selectedDuration > 0 ? selectedDuration.toFixed(2) : '0.00';
                    totalHoursDisplay.value = selectedDuration > 0 ? selectedDuration.toFixed(2) : '';
                    endTimeInput.value = computedEndTime;
                    endTimeDisplay.value = formatTimeLabel(computedEndTime);

                    if (matchedTiming) {
                        packageTierSelectedPrice.textContent = formatCurrency(applyPackageDiscount(matchedTiming.price));
                        packageTierSelectedLabel.textContent = `${parseAmount(matchedTiming.hours).toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1')} hours selected`;
                    } else if (selectedPackage && packageTierSummary && !packageTierSummary.classList.contains('hidden')) {
                        packageTierSelectedPrice.textContent = 'Select an option';
                        packageTierSelectedLabel.textContent = selectedDuration > 0
                            ? 'No package timing price matches the entered duration yet.'
                            : 'No timing option selected yet.';
                    }
                };

                const updateSummary = () => {
                    clearInvalidDiscountSelection();
                    const totals = quoteTotals();

                    summaryPackage.textContent = totals.packageName;
                    summaryTotal.textContent = formatSummaryCurrency(totals.total);
                    summaryTravel.textContent = formatSummaryCurrency(totals.travelFee);
                    summaryDiscount.textContent = '-$0.00';
                    summaryDeposit.textContent = formatSummaryCurrency(totals.depositAmount);

                    const discountedTotals = bookNowTotals();
                    discountAmountLabel.textContent = `-${formatCurrency(discountedTotals.discountAmount)}`;

                    if (discountedTotals.selectedDiscount && discountedTotals.discountAmount > 0) {
                        discountNote.textContent = `${discountedTotals.selectedDiscount.textContent.trim()} will be applied only with Book Now.`;
                    } else if (discountSelect.value) {
                        discountNote.textContent = 'This offer does not apply to the current package selection.';
                    }

                    renderBookingCart();
                    renderWizardSummary();
                };

                let travelFeeTimeout;
                const applyRequestedPackageSelection = () => {
                    if (!requestedPackageId) {
                        return;
                    }

                    const matchedPackageInput = Array.from(packageInputs).find((input) => String(input.value) === String(requestedPackageId));

                    if (!matchedPackageInput) {
                        return;
                    }

                    matchedPackageInput.checked = true;
                };

                const updateTravelLabels = (distanceKm, travelFee, note) => {
                    const chargeableKm = Math.ceil(Math.max(0, distanceKm));

                    travelDistanceInput.value = String(chargeableKm);
                    travelFeeInput.value = travelFee.toFixed(2);
                    travelDistanceLabel.textContent = `${chargeableKm} km`;
                    travelFeeLabel.textContent = formatCurrency(travelFee);
                    travelFeeNote.textContent = note;
                    updateSummary();
                };

                const queueTravelCalculation = () => {
                    window.clearTimeout(travelFeeTimeout);
                    travelFeeTimeout = window.setTimeout(async () => {
                        const destination = eventLocationInput.value.trim();

                        if (!workspaceAddress || !travelFeePerKilometer) {
                            updateTravelLabels(0, 0, 'Set a workspace address and travel rate to enable travel pricing.');
                            return;
                        }

                        if (!destination) {
                            updateTravelLabels(0, 0, 'Enter an event location to calculate travel pricing automatically.');
                            return;
                        }

                        travelFeeNote.textContent = 'Calculating travel distance...';
                        const distanceKm = await window.calculateGoogleAddressDistanceKm?.(workspaceAddress, destination);

                        if (distanceKm === null || distanceKm === undefined || !Number.isFinite(distanceKm)) {
                            updateTravelLabels(0, 0, 'Unable to calculate travel distance for that address right now.');
                            return;
                        }

                        const roundTripDistanceKm = distanceKm * 2;
                        const freeRoundTripKilometers = travelFreeKilometers * 2;
                        const chargeableDistanceKm = Math.ceil(Math.max(0, roundTripDistanceKm - freeRoundTripKilometers));
                        const travelFee = chargeableDistanceKm * travelFeePerKilometer;
                        updateTravelLabels(
                            chargeableDistanceKm,
                            travelFee,
                            `One-way distance is ${distanceKm.toFixed(2)} km. Round trip is ${roundTripDistanceKm.toFixed(2)} km minus ${freeRoundTripKilometers.toFixed(2)} free km, billed at $${travelFeePerKilometer.toFixed(2)} per kilometer.`,
                        );
                    }, 400);
                };

                const updateTotalHours = () => {
                    updatePackageDrivenTiming();
                    updateSummary();
                };

                const closeBookNowModal = () => {
                    bookNowModal.classList.add('hidden');
                    bookNowModal.classList.remove('flex');
                    document.body.classList.remove('overflow-hidden');
                    termsError.classList.add('hidden');
                };

                const closePackageTierModal = () => {
                    packageTierModal.classList.add('hidden');
                    packageTierModal.classList.remove('flex');
                    document.body.classList.remove('overflow-hidden');
                };

                const openPackageTierModal = () => {
                    const selectedPackage = document.querySelector('input[name="package_id"]:checked');

                    if (!selectedPackage) {
                        return;
                    }

                    let hourlyPrices = [];
                    try {
                        hourlyPrices = JSON.parse(selectedPackage.dataset.packageHourlyPrices || '[]');
                    } catch {
                        hourlyPrices = [];
                    }

                    if (!hourlyPrices.length) {
                        return;
                    }

                    packageTierModal.classList.remove('hidden');
                    packageTierModal.classList.add('flex');
                    document.body.classList.add('overflow-hidden');
                };

                const openBookNowModal = () => {
                    updatePackageDrivenTiming();

                    if (!validateBookingDetails()) {
                        return;
                    }

                    const totals = bookNowTotals();

                    if (!totals.selectedPackage) {
                        showToast('Please choose a package before continuing.', 'error');
                        return;
                    }

                    let hourlyPrices = [];
                    try {
                        hourlyPrices = JSON.parse(totals.selectedPackage.dataset.packageHourlyPrices || '[]');
                    } catch {
                        hourlyPrices = [];
                    }

                    if (hourlyPrices.length && !totals.selectedTiming) {
                        showToast('Please choose the package timing and price option first.', 'error');
                        return;
                    }

                    renderBookNowPackageThumbnail(totals.selectedPackage);
                    bookNowPackageName.textContent = totals.packageName;
                    updateBookNowAmounts();

                    if (totals.selectedAddOns.length) {
                        bookNowAddonList.innerHTML = totals.selectedAddOns
                            .map((input) => {
                                const name = input.dataset.addonName || 'Add-On';
                                const price = formatCurrency(parseAmount(input.dataset.addonPrice));
                                const category = input.dataset.addonCategory || 'Uncategorized';
                                const description = input.dataset.addonDescription || 'No add-on description provided yet.';
                                const photoUrl = input.dataset.addonPhotoUrl || '';
                                const imageMarkup = photoUrl
                                    ? `<img src="${escapeHtml(photoUrl)}" alt="${escapeHtml(name)}" class="h-16 w-16 rounded-2xl object-cover">`
                                    : `<div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-stone-900 text-2xl font-semibold text-stone-600">A</div>`;

                                return `<div class="flex items-center justify-between gap-4 rounded-2xl border border-white/10 bg-stone-950/60 p-3"><div class="flex min-w-0 items-center gap-3">${imageMarkup}<div class="min-w-0"><p class="truncate font-medium text-white">${escapeHtml(name)}</p><p class="mt-1 text-xs uppercase tracking-[0.18em] text-emerald-200">${escapeHtml(category)}</p><p class="mt-1 line-clamp-2 text-xs leading-5 text-stone-400">${escapeHtml(description)}</p></div></div><span class="shrink-0 font-medium text-emerald-100">${price}</span></div>`;
                            })
                            .join('');
                    } else {
                        bookNowAddonList.innerHTML = '<p class="text-stone-400">No add-ons selected.</p>';
                    }

                    if (bookNowDiscountCode.value.trim()) {
                        applyDiscountCode();
                    } else {
                        setDiscountFeedback('Enter a discount code if you have one.');
                    }

                    termsAcceptedCheckbox.checked = false;
                    termsError.classList.add('hidden');
                    bookNowModal.classList.remove('hidden');
                    bookNowModal.classList.add('flex');
                    document.body.classList.add('overflow-hidden');
                };

                packageInputs.forEach((input) => {
                    input.addEventListener('change', () => {
                        let hourlyPrices = [];
                        try {
                            hourlyPrices = JSON.parse(input.dataset.packageHourlyPrices || '[]');
                        } catch {
                            hourlyPrices = [];
                        }

                        renderPackageTimingOptions();
                        refreshDiscountOptions();
                        syncPackageIncludedAddOnVisibility();
                        applyAddOnCategoryFilter(activeAddOnCategory);
                        updateSummary();

                        if (input.checked && hourlyPrices.length) {
                            openPackageTierModal();
                        }
                    });
                });

                addOnInputs.forEach((input) => {
                    input.addEventListener('change', () => {
                        updateSummary();

                        if (input.checked) {
                            notifyIncludedAddOns([input]);
                        }
                    });
                });

                addOnCategoryButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        applyAddOnCategoryFilter(button.dataset.addonCategoryFilter || 'all');
                    });
                });

                bookingCartToggle.addEventListener('click', () => {
                    setBookingCartOpen(bookingCartPanel.classList.contains('hidden'));
                });
                bookingCartClose.addEventListener('click', () => {
                    setBookingCartOpen(false);
                });
                bookingCartContent.addEventListener('click', (event) => {
                    const removeButton = event.target.closest('[data-cart-remove-type]');

                    if (!removeButton) {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();

                    if (removeButton.dataset.cartRemoveType === 'package') {
                        const input = document.querySelector(`input[name="package_id"][value="${removeButton.dataset.cartRemoveValue}"]`);
                        if (input) {
                            input.checked = false;
                            packageHourlyPriceIdInput.value = '';
                        }
                    }

                    if (removeButton.dataset.cartRemoveType === 'addon') {
                        const input = document.querySelector(`input[name="add_on_ids[]"][value="${removeButton.dataset.cartRemoveValue}"]`);
                        if (input) {
                            input.checked = false;
                        }
                    }

                    renderPackageTimingOptions();
                    refreshDiscountOptions();
                    updateSummary();

                    if (!document.querySelector('input[name="package_id"]:checked') && !document.querySelector('input[name="add_on_ids[]"]:checked')) {
                        setBookingCartOpen(false);
                    }
                });
                document.addEventListener('click', (event) => {
                    if (bookingCartPanel.classList.contains('hidden')) {
                        return;
                    }

                    if (bookingCartPanel.contains(event.target) || bookingCartToggle.contains(event.target)) {
                        return;
                    }

                    setBookingCartOpen(false);
                });

                discountSelect.addEventListener('change', () => {
                    updateSummary();
                    updateBookNowAmounts();
                });

                let discountCodeTimeout;
                bookNowDiscountCode.addEventListener('input', () => {
                    window.clearTimeout(discountCodeTimeout);
                    discountCodeTimeout = window.setTimeout(applyDiscountCode, 250);
                });
                bookNowDiscountApply.addEventListener('click', () => {
                    window.clearTimeout(discountCodeTimeout);
                    applyDiscountCode();
                });
                wizardNextButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        if (!validateWizardStep(currentWizardStep)) {
                            return;
                        }

                        showWizardStep(currentWizardStep + 1);
                    });
                });
                wizardPrevButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        showWizardStep(currentWizardStep - 1);
                    });
                });
                wizardNavButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const targetStep = Number(button.dataset.wizardNav);

                        if (targetStep <= currentWizardStep) {
                            showWizardStep(targetStep);
                            return;
                        }

                        for (let step = currentWizardStep; step < targetStep; step += 1) {
                            if (!validateWizardStep(step)) {
                                return;
                            }
                        }

                        showWizardStep(targetStep);
                    });
                });
                applyAddOnCategoryFilter('all');
                applyRequestedPackageSelection();
                restoreCustomerSessionDetails();
                renderPackageTimingOptions();
                refreshDiscountOptions();
                syncPackageIncludedAddOnVisibility();
                applyAddOnCategoryFilter(activeAddOnCategory);
                updateSummary();
                showWizardStep(initialWizardStep);
                queueTravelCalculation();

                @if (session('status'))
                    showToast(@js(session('status')), 'success');
                @endif

                let autosaveTimeout;
                let currentAutosaveRequest = null;
                let lastAutosaveFingerprint = '';

                const setAutosaveStatus = (message, tone = 'neutral') => {
                    const toneClasses = {
                        neutral: 'text-stone-400',
                        saving: 'text-amber-200',
                        saved: 'text-emerald-200',
                        error: 'text-rose-200',
                    };

                    autosaveStatus.textContent = message;
                    autosaveStatus.className = `text-right text-xs ${toneClasses[tone] || toneClasses.neutral}`;
                };

                const collectAutosavePayload = () => {
                    const payload = new URLSearchParams();
                    payload.set('_token', form.querySelector('input[name="_token"]')?.value || '');
                    payload.set('lead_token', leadTokenInput.value || '');
                    payload.set('customer_name', document.getElementById('customer-name')?.value || '');
                    payload.set('customer_email', document.getElementById('customer-email')?.value || '');
                    payload.set('customer_phone', document.getElementById('customer-phone')?.value || '');
                    payload.set('event_date', document.getElementById('event-date')?.value || '');
                    payload.set('event_location', document.getElementById('event-location')?.value || '');
                    payload.set('notes', document.getElementById('booking-notes')?.value || '');

                    return payload;
                };

                const queueAutosave = () => {
                    window.clearTimeout(autosaveTimeout);
                    autosaveTimeout = window.setTimeout(async () => {
                        const payload = collectAutosavePayload();
                        const fingerprint = payload.toString();

                        if (fingerprint === lastAutosaveFingerprint) {
                            return;
                        }

                        const hasContent = ['customer_name', 'customer_email', 'customer_phone', 'event_date', 'event_location', 'notes']
                            .some((field) => (payload.get(field) || '').trim() !== '');

                        if (!hasContent && !leadTokenInput.value) {
                            setAutosaveStatus('Customer details are saved automatically.', 'neutral');
                            return;
                        }

                        if (currentAutosaveRequest) {
                            currentAutosaveRequest.abort();
                        }

                        currentAutosaveRequest = new AbortController();
                        setAutosaveStatus('Saving lead...', 'saving');

                        try {
                            const response = await fetch('{{ route('bookings.autosave-lead') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: payload.toString(),
                                signal: currentAutosaveRequest.signal,
                                credentials: 'same-origin',
                            });

                            if (!response.ok) {
                                throw new Error('Autosave failed.');
                            }

                            const result = await response.json();
                            leadTokenInput.value = result.lead_token || leadTokenInput.value;
                            lastAutosaveFingerprint = collectAutosavePayload().toString();
                            setAutosaveStatus('Lead saved for follow-up.', 'saved');
                        } catch (error) {
                            if (error.name === 'AbortError') {
                                return;
                            }

                            setAutosaveStatus('Lead save failed. Details stay on this page until submit.', 'error');
                        }
                    }, 700);
                };

                autosaveFields.forEach((field) => {
                    field.addEventListener('input', queueAutosave);
                    field.addEventListener('change', queueAutosave);
                    field.addEventListener('blur', queueAutosave);
                });
                [customerNameInput, customerEmailInput, customerPhoneInput].filter(Boolean).forEach((field) => {
                    field.addEventListener('input', writeCustomerSessionDetails);
                    field.addEventListener('change', writeCustomerSessionDetails);
                    field.addEventListener('blur', writeCustomerSessionDetails);
                });
                Array.from(form.querySelectorAll('input, select, textarea')).forEach((field) => {
                    field.addEventListener('input', () => clearFieldError(field));
                    field.addEventListener('change', () => clearFieldError(field));
                });
                eventLocationInput.addEventListener('input', () => {
                    eventLocationMirrorInput.value = eventLocationInput.value;
                });
                eventLocationMirrorInput.addEventListener('input', () => {
                    eventLocationInput.value = eventLocationMirrorInput.value;
                    clearFieldError(eventLocationInput);
                    clearFieldError(eventLocationMirrorInput);
                    queueAutosave();
                    queueTravelCalculation();
                });
                eventLocationMirrorInput.addEventListener('change', () => {
                    eventLocationInput.value = eventLocationMirrorInput.value;
                    clearFieldError(eventLocationInput);
                    clearFieldError(eventLocationMirrorInput);
                    queueAutosave();
                    queueTravelCalculation();
                });
                startTimeInput.addEventListener('input', updateTotalHours);
                startTimeInput.addEventListener('change', () => {
                    snapTimeInput(startTimeInput);
                    updateTotalHours();
                });
                startTimeInput.addEventListener('blur', () => {
                    snapTimeInput(startTimeInput);
                    updateTotalHours();
                });
                endTimeInput.addEventListener('input', updateTotalHours);
                endTimeInput.addEventListener('change', () => {
                    snapTimeInput(endTimeInput);
                    updateTotalHours();
                });
                endTimeInput.addEventListener('blur', () => {
                    snapTimeInput(endTimeInput);
                    updateTotalHours();
                });
                totalHoursDisplay.addEventListener('input', updateTotalHours);
                totalHoursDisplay.addEventListener('change', updateTotalHours);
                totalHoursDisplay.addEventListener('blur', () => {
                    const normalizedDuration = parseAmount(totalHoursDisplay.value);
                    totalHoursDisplay.value = normalizedDuration > 0 ? normalizedDuration.toFixed(2) : '';
                    updateTotalHours();
                });
                eventLocationInput.addEventListener('input', queueTravelCalculation);
                eventLocationInput.addEventListener('change', queueTravelCalculation);
                eventLocationInput.addEventListener('blur', queueTravelCalculation);
                eventLocationMirrorInput.addEventListener('blur', queueTravelCalculation);
                form.addEventListener('submit', (event) => {
                    updatePackageDrivenTiming();

                    if (currentWizardStep < 5) {
                        event.preventDefault();

                        if (validateWizardStep(currentWizardStep)) {
                            showWizardStep(currentWizardStep + 1);
                        }

                        return;
                    }

                    if (!validateBookingDetails()) {
                        event.preventDefault();
                    }
                });
                snapTimeInput(startTimeInput);
                snapTimeInput(endTimeInput);
                updateTotalHours();

                openBookNowButton.addEventListener('click', openBookNowModal);
                closeBookNowButton.addEventListener('click', closeBookNowModal);
                cancelBookNowButton.addEventListener('click', closeBookNowModal);
                openPackageTierModalButton.addEventListener('click', openPackageTierModal);
                closePackageTierModalButton.addEventListener('click', closePackageTierModal);
                confirmBookNowButton.addEventListener('click', () => {
                    updatePackageDrivenTiming();

                    if (!validateBookingDetails()) {
                        closeBookNowModal();
                        return;
                    }

                    if (!termsAcceptedCheckbox.checked) {
                        termsError.classList.remove('hidden');
                        return;
                    }

                    termsError.classList.add('hidden');
                    clearInvalidDiscountSelection();

                    const hiddenTermsInput = document.createElement('input');
                    hiddenTermsInput.type = 'hidden';
                    hiddenTermsInput.name = 'terms_accepted';
                    hiddenTermsInput.value = '1';
                    form.appendChild(hiddenTermsInput);
                    form.action = '{{ route('bookings.book-now') }}';
                    form.submit();
                });

                closeButton.addEventListener('click', closeModal);
                modal.addEventListener('click', (event) => {
                    if (event.target === modal) {
                        closeModal();
                    }
                });
                bookNowModal.addEventListener('click', (event) => {
                    if (event.target === bookNowModal) {
                        closeBookNowModal();
                    }
                });
                packageTierModal.addEventListener('click', (event) => {
                    if (event.target === packageTierModal) {
                        closePackageTierModal();
                    }
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && modal.classList.contains('flex')) {
                        closeModal();
                    }

                    if (event.key === 'Escape' && bookNowModal.classList.contains('flex')) {
                        closeBookNowModal();
                    }

                    if (event.key === 'Escape' && packageTierModal.classList.contains('flex')) {
                        closePackageTierModal();
                    }
                });
            })();
        </script>
    </body>
</html>
