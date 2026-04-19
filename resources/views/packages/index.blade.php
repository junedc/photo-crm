<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $tenant->name }} Packages</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-stone-950 text-stone-50">
        <header class="fixed inset-x-0 top-0 z-50 border-b border-white/10 bg-stone-950/90 backdrop-blur-xl">
            <div class="mx-auto flex h-20 max-w-[1700px] items-center justify-between px-4 sm:px-6 lg:px-8">
                <div>
                    <p class="text-xs uppercase tracking-[0.35em] text-amber-200">MemoShot Admin</p>
                    <h1 class="mt-1 text-xl font-semibold tracking-tight sm:text-2xl">{{ $tenant->name }}</h1>
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ route('dashboard') }}" class="rounded-2xl border border-white/10 px-4 py-2 text-sm font-medium text-stone-200 transition hover:border-amber-300/40 hover:text-white">
                        Dashboard
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-2xl border border-white/10 px-4 py-2 text-sm font-medium text-stone-200 transition hover:border-amber-300/40 hover:text-white">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <div class="mx-auto flex max-w-[1700px] pt-20">
            <aside class="hidden w-72 shrink-0 border-r border-white/10 bg-stone-950/80 lg:fixed lg:bottom-0 lg:top-20 lg:block">
                <div class="flex h-full flex-col px-6 py-8">
                    <div class="rounded-[2rem] border border-white/10 bg-gradient-to-br from-amber-300/15 via-stone-900 to-rose-300/10 p-5">
                        <p class="text-xs uppercase tracking-[0.3em] text-amber-200">Packages</p>
                        <p class="mt-3 text-sm leading-6 text-stone-300">
                            Create photobooth packages, review the records you have created, and open one record at a time to edit its details.
                        </p>
                    </div>

                    <nav class="mt-8 space-y-2">
                        <a href="{{ route('dashboard') }}" class="block rounded-2xl border border-white/10 px-4 py-3 text-sm text-stone-300 transition hover:border-white/20 hover:bg-white/5 hover:text-white">
                            Overview Dashboard
                        </a>
                        <a href="{{ route('packages.index') }}" class="block rounded-2xl border border-amber-300/40 bg-white/5 px-4 py-3 text-sm font-medium text-white">
                            Packages
                        </a>
                        <a href="{{ route('equipment.index') }}" class="block rounded-2xl border border-white/10 px-4 py-3 text-sm text-stone-300 transition hover:border-cyan-300/40 hover:bg-white/5 hover:text-white">
                            Equipment
                        </a>
                        <a href="{{ route('items.index') }}" class="block rounded-2xl border border-white/10 px-4 py-3 text-sm text-stone-300 transition hover:border-rose-300/40 hover:bg-white/5 hover:text-white">
                            Inventory Items
                        </a>
                    </nav>

                    <div class="mt-8 rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Total Packages</p>
                        <p class="mt-2 text-3xl font-semibold">{{ $packages->count() }}</p>
                    </div>
                </div>
            </aside>

            <main class="min-w-0 flex-1 px-4 py-8 sm:px-6 lg:ml-72 lg:px-8">
                <div class="mx-auto max-w-7xl space-y-6">
                    <section class="rounded-[2rem] border border-white/10 bg-gradient-to-br from-amber-300/15 via-stone-900 to-rose-300/10 p-6 shadow-2xl shadow-black/20 sm:p-8">
                        <p class="text-sm uppercase tracking-[0.35em] text-amber-200">Package Workspace</p>
                        <h2 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Create and review packages</h2>
                        <p class="mt-3 max-w-3xl text-sm leading-6 text-stone-300">
                            The left panel is for creating packages, the middle panel lists the packages you already created, and the right panel shows the selected record details.
                        </p>
                    </section>

                    @if (session('status'))
                        <div class="rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="rounded-2xl border border-rose-400/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-100">
                            <p class="font-semibold">Please fix the highlighted form details.</p>
                            <ul class="mt-2 list-disc pl-5 text-rose-50">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <section class="grid gap-6 xl:grid-cols-[360px_320px_minmax(0,1fr)]">
                        <div class="rounded-[2rem] border border-white/10 bg-white/5 p-6">
                            <div class="mb-5">
                                <p class="text-sm uppercase tracking-[0.3em] text-amber-200">Add Package</p>
                                <h3 class="mt-2 text-2xl font-semibold">New record</h3>
                            </div>

                            <form method="POST" action="{{ route('packages.store') }}" enctype="multipart/form-data" class="space-y-4">
                                @csrf
                                <div>
                                    <label class="mb-2 block text-sm text-stone-300" for="package-name">Package name</label>
                                    <input id="package-name" name="name" type="text" value="{{ old('name') }}" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" required>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm text-stone-300" for="package-description">Description</label>
                                    <textarea id="package-description" name="description" rows="5" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-amber-300/50">{{ old('description') }}</textarea>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm text-stone-300" for="package-price">Base price</label>
                                    <input id="package-price" name="base_price" type="number" min="0" step="0.01" value="{{ old('base_price') }}" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" required>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm text-stone-300" for="package-photo">Photo</label>
                                    <input id="package-photo" name="photo" type="file" accept="image/*" class="block w-full rounded-2xl border border-dashed border-white/15 bg-stone-950/70 px-4 py-3 text-sm text-stone-300 file:mr-4 file:rounded-full file:border-0 file:bg-amber-300 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-stone-950">
                                </div>
                                <label class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-stone-200">
                                    <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-white/20 bg-stone-900 text-amber-300" @checked(old('is_active', true))>
                                    Active package
                                </label>
                                <button type="submit" class="w-full rounded-2xl bg-amber-300 px-4 py-3 text-sm font-semibold text-stone-950 transition hover:bg-amber-200">
                                    Save package
                                </button>
                            </form>
                        </div>

                        <div class="rounded-[2rem] border border-white/10 bg-white/5 p-4">
                            <div class="mb-4 px-2">
                                <p class="text-sm uppercase tracking-[0.3em] text-stone-400">Created Packages</p>
                                <h3 class="mt-2 text-xl font-semibold">Package list</h3>
                            </div>

                            <div class="space-y-3">
                                @forelse ($packages as $package)
                                    <a href="{{ route('packages.show', $package) }}" class="block rounded-2xl border px-4 py-4 transition {{ $selectedPackage?->is($package) ? 'border-amber-300/40 bg-amber-300/10' : 'border-white/10 bg-stone-950/40 hover:border-white/20 hover:bg-white/5' }}">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="font-medium text-white">{{ $package->name }}</p>
                                                <p class="mt-1 text-sm text-stone-400">${{ number_format((float) $package->base_price, 2) }}</p>
                                            </div>
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ $package->is_active ? 'bg-emerald-400/15 text-emerald-200' : 'bg-stone-700/60 text-stone-300' }}">
                                                {{ $package->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </div>
                                    </a>
                                @empty
                                    <div class="rounded-2xl border border-dashed border-white/15 bg-stone-950/40 px-4 py-5 text-sm text-stone-400">
                                        No package records yet.
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <div class="rounded-[2rem] border border-white/10 bg-white/5 p-6">
                            @if ($selectedPackage)
                                <div class="mb-5 flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm uppercase tracking-[0.3em] text-amber-200">Package Details</p>
                                        <h3 class="mt-2 text-2xl font-semibold">{{ $selectedPackage->name }}</h3>
                                    </div>
                                    <span class="rounded-full px-3 py-1 text-xs font-medium {{ $selectedPackage->is_active ? 'bg-emerald-400/15 text-emerald-200' : 'bg-stone-700/60 text-stone-300' }}">
                                        {{ $selectedPackage->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>

                                @if ($selectedPackage->photo_path)
                                    <img src="{{ asset('storage/'.$selectedPackage->photo_path) }}" alt="{{ $selectedPackage->name }}" class="mb-5 h-56 w-full rounded-3xl object-cover">
                                @endif

                                <div class="mb-5 grid gap-4 sm:grid-cols-2">
                                    <div class="rounded-2xl border border-white/10 bg-stone-950/40 p-4">
                                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Base Price</p>
                                        <p class="mt-2 text-2xl font-semibold">${{ number_format((float) $selectedPackage->base_price, 2) }}</p>
                                    </div>
                                    <div class="rounded-2xl border border-white/10 bg-stone-950/40 p-4">
                                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Created</p>
                                        <p class="mt-2 text-lg font-semibold">{{ $selectedPackage->created_at?->format('d M Y') }}</p>
                                    </div>
                                </div>

                                <div class="mb-5 rounded-2xl border border-white/10 bg-stone-950/40 p-4">
                                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Description</p>
                                    <p class="mt-3 text-sm leading-6 text-stone-300">
                                        {{ $selectedPackage->description ?: 'No description added yet.' }}
                                    </p>
                                </div>

                                <form method="POST" action="{{ route('packages.update', $selectedPackage) }}" enctype="multipart/form-data" class="space-y-4">
                                    @csrf
                                    @method('PUT')
                                    <div>
                                        <label class="mb-2 block text-sm text-stone-300" for="selected-name">Package name</label>
                                        <input id="selected-name" name="name" type="text" value="{{ old('name', $selectedPackage->name) }}" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" required>
                                    </div>
                                    <div>
                                        <label class="mb-2 block text-sm text-stone-300" for="selected-description">Description</label>
                                        <textarea id="selected-description" name="description" rows="5" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-amber-300/50">{{ old('description', $selectedPackage->description) }}</textarea>
                                    </div>
                                    <div>
                                        <label class="mb-2 block text-sm text-stone-300" for="selected-price">Base price</label>
                                        <input id="selected-price" name="base_price" type="number" min="0" step="0.01" value="{{ old('base_price', $selectedPackage->base_price) }}" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-amber-300/50" required>
                                    </div>
                                    <div>
                                        <label class="mb-2 block text-sm text-stone-300" for="selected-photo">Replace photo</label>
                                        <input id="selected-photo" name="photo" type="file" accept="image/*" class="block w-full rounded-2xl border border-dashed border-white/15 bg-stone-950/70 px-4 py-3 text-sm text-stone-300 file:mr-4 file:rounded-full file:border-0 file:bg-white file:px-4 file:py-2 file:text-sm file:font-semibold file:text-stone-950">
                                    </div>
                                    <label class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-stone-200">
                                        <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-white/20 bg-stone-900 text-amber-300" @checked(old('is_active', $selectedPackage->is_active))>
                                        Active package
                                    </label>
                                    <button type="submit" class="w-full rounded-2xl border border-white/10 px-4 py-3 text-sm font-semibold text-white transition hover:border-amber-300/40 hover:bg-white/5">
                                        Update package
                                    </button>
                                </form>
                            @else
                                <div class="flex h-full min-h-80 items-center justify-center rounded-[2rem] border border-dashed border-white/15 bg-stone-950/40 p-8 text-center text-stone-400">
                                    Create your first package on the left to see its details here.
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </body>
</html>
