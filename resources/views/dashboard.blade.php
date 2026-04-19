<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $tenant->name }} Admin</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-stone-950 text-stone-50">
        <header class="fixed inset-x-0 top-0 z-50 border-b border-white/10 bg-stone-950/90 backdrop-blur-xl">
            <div class="mx-auto flex h-20 max-w-[1600px] items-center justify-between px-4 sm:px-6 lg:px-8">
                <div>
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-200">MemoShot Admin</p>
                    <h1 class="mt-1 text-xl font-semibold tracking-tight sm:text-2xl">{{ $tenant->name }}</h1>
                </div>

                <div class="flex items-center gap-3">
                    <div class="hidden rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-right text-sm text-stone-300 sm:block">
                        <span class="block text-[11px] uppercase tracking-[0.3em] text-stone-500">Workspace</span>
                        <span class="font-medium text-white">{{ $tenant->slug }}</span>
                    </div>
                    <div class="hidden rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-right text-sm text-stone-300 md:block">
                        <span class="block text-[11px] uppercase tracking-[0.3em] text-stone-500">Signed In</span>
                        <span class="font-medium text-white">{{ auth()->user()->name }}</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-2xl border border-white/10 px-4 py-2 text-sm font-medium text-stone-200 transition hover:border-amber-300/40 hover:text-white">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <div class="mx-auto flex max-w-[1600px] pt-20">
            <aside class="hidden w-72 shrink-0 border-r border-white/10 bg-stone-950/80 lg:fixed lg:bottom-0 lg:top-20 lg:block">
                <div class="flex h-full flex-col px-6 py-8">
                    <div class="rounded-[2rem] border border-white/10 bg-gradient-to-br from-amber-300/15 via-stone-900 to-rose-300/10 p-5">
                        <p class="text-xs uppercase tracking-[0.3em] text-amber-200">Control Center</p>
                        <p class="mt-3 text-sm leading-6 text-stone-300">
                            Open each admin module in its own dedicated workspace with a create panel, record list, and details panel.
                        </p>
                    </div>

                    <nav class="mt-8 space-y-2">
                        <a href="{{ route('dashboard') }}" class="block rounded-2xl border border-amber-300/40 bg-white/5 px-4 py-3 text-sm font-medium text-white">
                            Overview
                        </a>
                        <a href="{{ route('packages.index') }}" class="block rounded-2xl border border-white/10 px-4 py-3 text-sm text-stone-300 transition hover:border-amber-300/40 hover:bg-white/5 hover:text-white">
                            Packages
                        </a>
                        <a href="{{ route('equipment.index') }}" class="block rounded-2xl border border-white/10 px-4 py-3 text-sm text-stone-300 transition hover:border-cyan-300/40 hover:bg-white/5 hover:text-white">
                            Equipment
                        </a>
                        <a href="{{ route('items.index') }}" class="block rounded-2xl border border-white/10 px-4 py-3 text-sm text-stone-300 transition hover:border-rose-300/40 hover:bg-white/5 hover:text-white">
                            Inventory Items
                        </a>
                    </nav>

                    <div class="mt-8 grid gap-3">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Packages</p>
                            <p class="mt-2 text-2xl font-semibold">{{ $packages->count() }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Equipment</p>
                            <p class="mt-2 text-2xl font-semibold">{{ $equipment->count() }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Items</p>
                            <p class="mt-2 text-2xl font-semibold">{{ $inventoryItems->count() }}</p>
                        </div>
                    </div>
                </div>
            </aside>

            <main class="min-w-0 flex-1 px-4 py-8 sm:px-6 lg:ml-72 lg:px-8">
                <div class="mx-auto max-w-6xl space-y-8">
                    <section class="rounded-[2rem] border border-white/10 bg-gradient-to-br from-amber-300/15 via-stone-900 to-rose-300/10 p-6 shadow-2xl shadow-black/20 sm:p-8">
                        <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                            <div>
                                <p class="text-sm uppercase tracking-[0.35em] text-amber-200">Dashboard</p>
                                <h2 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Photobooth rental operations</h2>
                                <p class="mt-3 max-w-3xl text-sm leading-6 text-stone-300">
                                    Use the overview to monitor counts, then jump into dedicated split-screen workspaces for packages, equipment, and inventory items.
                                </p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4">
                                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Active Packages</p>
                                    <p class="mt-2 text-3xl font-semibold">{{ $packages->where('is_active', true)->count() }}</p>
                                </div>
                                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4">
                                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Equipment</p>
                                    <p class="mt-2 text-3xl font-semibold">{{ $equipment->count() }}</p>
                                </div>
                                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4">
                                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Inventory Items</p>
                                    <p class="mt-2 text-3xl font-semibold">{{ $inventoryItems->count() }}</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-6 xl:grid-cols-3">
                        <a href="{{ route('packages.index') }}" class="rounded-[2rem] border border-white/10 bg-white/5 p-6 transition hover:border-amber-300/40 hover:bg-white/10">
                            <p class="text-sm uppercase tracking-[0.3em] text-amber-200">Packages</p>
                            <h3 class="mt-3 text-2xl font-semibold">Manage packages</h3>
                            <p class="mt-3 text-sm leading-6 text-stone-300">
                                Add new packages, browse the package list, and edit a selected package from the right-side details panel.
                            </p>
                            <p class="mt-6 text-sm font-medium text-amber-200">Open package workspace</p>
                        </a>

                        <a href="{{ route('equipment.index') }}" class="rounded-[2rem] border border-white/10 bg-white/5 p-6 transition hover:border-cyan-300/40 hover:bg-white/10">
                            <p class="text-sm uppercase tracking-[0.3em] text-cyan-200">Equipment</p>
                            <h3 class="mt-3 text-2xl font-semibold">Manage booth equipment</h3>
                            <p class="mt-3 text-sm leading-6 text-stone-300">
                                Create equipment records, inspect status and rental pricing, and update maintenance information from a dedicated split screen.
                            </p>
                            <p class="mt-6 text-sm font-medium text-cyan-200">Open equipment workspace</p>
                        </a>

                        <a href="{{ route('items.index') }}" class="rounded-[2rem] border border-white/10 bg-white/5 p-6 transition hover:border-rose-300/40 hover:bg-white/10">
                            <p class="text-sm uppercase tracking-[0.3em] text-rose-200">Inventory Items</p>
                            <h3 class="mt-3 text-2xl font-semibold">Manage stock and supplies</h3>
                            <p class="mt-3 text-sm leading-6 text-stone-300">
                                Track quantities, prices, photos, and maintenance details for props, backdrops, and other rental inventory items.
                            </p>
                            <p class="mt-6 text-sm font-medium text-rose-200">Open inventory workspace</p>
                        </a>
                    </section>
                </div>
            </main>
        </div>
    </body>
</html>
