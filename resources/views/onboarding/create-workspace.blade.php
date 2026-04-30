<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Create Your MemoShot Workspace</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-stone-950 text-stone-50">
        <div class="relative min-h-screen overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(251,191,36,0.25),transparent_30%),radial-gradient(circle_at_bottom_right,rgba(14,165,233,0.2),transparent_25%),linear-gradient(145deg,#0c0a09_0%,#111827_55%,#1f2937_100%)]"></div>
            <main class="relative mx-auto grid min-h-screen max-w-6xl items-center gap-10 px-6 py-12 lg:grid-cols-[1.08fr_0.92fr]">
                <section class="space-y-7">
                    <div class="flex items-center justify-between gap-4">
                        <p class="text-xl font-semibold tracking-tight text-white">MemoShot</p>
                        <a href="{{ route('login') }}" class="rounded-full border border-white/10 bg-white/8 px-4 py-2 text-sm font-medium text-stone-200 transition hover:border-cyan-300/40 hover:text-white">Login</a>
                    </div>

                    <p class="inline-flex items-center rounded-full border border-amber-300/30 bg-amber-300/15 px-3 py-1 text-sm font-medium text-amber-100">
                        Built for photo booth businesses
                    </p>
                    <div class="space-y-5">
                        <h1 class="max-w-2xl text-4xl font-semibold tracking-tight text-white sm:text-5xl">
                            Your all-in-one booking and marketing workspace.
                        </h1>
                        <p class="max-w-2xl text-base leading-7 text-stone-300 sm:text-lg">
                            Claim your own MemoShot link, showcase your packages, capture leads, send quotes, collect invoices, and give clients a polished place to book their next event.
                        </p>
                    </div>

                    <div class="max-w-2xl rounded-3xl border border-white/10 bg-white/8 p-4 shadow-2xl shadow-black/20 backdrop-blur">
                        <div class="flex flex-col gap-3 rounded-2xl border border-cyan-300/20 bg-black/25 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-100">Your public booking link</p>
                                <p class="mt-1 break-all text-xl font-semibold text-white">yourbrand.{{ config('app.tenant_base_domain') }}</p>
                            </div>
                            <span class="rounded-full bg-cyan-300 px-4 py-2 text-sm font-semibold text-stone-950">Start free</span>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-[0.85fr_1.15fr]">
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4">
                                <div class="h-28 rounded-2xl bg-[linear-gradient(135deg,rgba(251,191,36,0.9),rgba(14,165,233,0.7))]"></div>
                                <p class="mt-4 text-sm font-semibold text-white">Glow Booth Studio</p>
                                <p class="mt-1 text-xs leading-5 text-stone-400">Weddings, birthdays, corporate events, and private parties.</p>
                                <button type="button" class="mt-4 w-full rounded-xl bg-white px-3 py-2 text-sm font-semibold text-stone-950">Book now</button>
                            </div>
                            <div class="grid content-start gap-3">
                                <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-white">Classic Photo Booth</p>
                                            <p class="mt-1 text-xs text-stone-400">3 hours + prints + online gallery</p>
                                        </div>
                                        <p class="text-sm font-semibold text-amber-100">$599</p>
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-white">360 Video Booth</p>
                                            <p class="mt-1 text-xs text-stone-400">Slow-motion clips + sharing station</p>
                                        </div>
                                        <p class="text-sm font-semibold text-amber-100">$899</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-3 gap-2 text-center text-xs text-stone-300">
                                    <div class="rounded-xl border border-white/10 bg-white/10 px-2 py-3">
                                        <p class="font-semibold text-white">24</p>
                                        <p class="mt-1">leads</p>
                                    </div>
                                    <div class="rounded-xl border border-white/10 bg-white/10 px-2 py-3">
                                        <p class="font-semibold text-white">12</p>
                                        <p class="mt-1">quotes</p>
                                    </div>
                                    <div class="rounded-xl border border-white/10 bg-white/10 px-2 py-3">
                                        <p class="font-semibold text-white">$8.4k</p>
                                        <p class="mt-1">booked</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid max-w-2xl gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/10 bg-white/8 px-4 py-4">
                            <p class="text-sm font-semibold text-white">Profile + packages</p>
                            <p class="mt-1 text-xs leading-5 text-stone-400">Show services, add-ons, travel fees, and booking options.</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/8 px-4 py-4">
                            <p class="text-sm font-semibold text-white">Quotes + payments</p>
                            <p class="mt-1 text-xs leading-5 text-stone-400">Turn enquiries into invoices without losing the thread.</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/8 px-4 py-4">
                            <p class="text-sm font-semibold text-white">Promote anywhere</p>
                            <p class="mt-1 text-xs leading-5 text-stone-400">Add your link to Instagram, ads, QR codes, and emails.</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-white/10 bg-white/8 p-8 shadow-2xl shadow-black/30 backdrop-blur">
                    <div class="mb-6 space-y-2">
                        <p class="text-sm font-medium uppercase tracking-[0.18em] text-cyan-100">Reserve your workspace</p>
                        <h2 class="text-2xl font-semibold tracking-tight text-white">Pick your business link</h2>
                        <p class="text-sm leading-6 text-stone-300">This becomes the default address customers use to browse, book, and return to their event details.</p>
                    </div>

                    <form method="POST" action="{{ route('workspaces.store') }}" class="space-y-5">
                        @csrf
                        <input type="hidden" name="ref" value="{{ old('ref', request('ref')) }}">

                        @if (request('ref'))
                            <div class="rounded-2xl border border-emerald-300/20 bg-emerald-300/10 px-4 py-3 text-sm leading-6 text-emerald-50">
                                Referral code <span class="font-semibold">{{ request('ref') }}</span> will be applied to this workspace signup.
                            </div>
                        @endif

                        <div class="space-y-2">
                            <label for="tenant_name" class="text-sm font-medium text-stone-200">Business name</label>
                            <input id="tenant_name" name="tenant_name" type="text" value="{{ old('tenant_name') }}" required class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none placeholder:text-stone-500 focus:border-cyan-300/60" placeholder="Acme Studio">
                            @error('tenant_name')
                                <p class="text-sm text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="tenant_slug" class="text-sm font-medium text-stone-200">Booking link</label>
                            <div class="flex items-center overflow-hidden rounded-2xl border border-white/10 bg-black/20">
                                <input id="tenant_slug" name="tenant_slug" type="text" value="{{ old('tenant_slug') }}" required class="w-full bg-transparent px-4 py-3 text-white outline-none placeholder:text-stone-500" placeholder="acme">
                                <span class="border-l border-white/10 px-4 text-sm text-stone-400">.{{ config('app.tenant_base_domain') }}</span>
                            </div>
                            @error('tenant_slug')
                                <p class="text-sm text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label for="name" class="text-sm font-medium text-stone-200">Your name</label>
                                <input id="name" name="name" type="text" value="{{ old('name') }}" required class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none placeholder:text-stone-500 focus:border-cyan-300/60" placeholder="Alex Morgan">
                                @error('name')
                                    <p class="text-sm text-rose-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label for="email" class="text-sm font-medium text-stone-200">Email address</label>
                                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none placeholder:text-stone-500 focus:border-cyan-300/60" placeholder="you@company.com">
                                @error('email')
                                    <p class="text-sm text-rose-300">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="rounded-2xl border border-cyan-300/20 bg-cyan-300/10 px-4 py-3 text-sm leading-6 text-cyan-50">
                            Your owner account uses passwordless sign-in, so you can launch now and log in securely by email whenever you return.
                        </div>

                        <button type="submit" class="w-full rounded-2xl bg-cyan-300 px-4 py-3 font-semibold text-stone-950 transition hover:bg-cyan-200">
                            Launch my workspace
                        </button>
                    </form>
                </section>
            </main>
        </div>
    </body>
</html>
