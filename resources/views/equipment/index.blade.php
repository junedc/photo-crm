<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $tenant->name }} Equipment</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-stone-950 text-stone-50">
        <header class="fixed inset-x-0 top-0 z-50 border-b border-white/10 bg-stone-950/90 backdrop-blur-xl">
            <div class="mx-auto flex h-20 max-w-[1700px] items-center justify-between px-4 sm:px-6 lg:px-8">
                <div>
                    <p class="text-xs uppercase tracking-[0.35em] text-cyan-200">MemoShot Admin</p>
                    <h1 class="mt-1 text-xl font-semibold tracking-tight sm:text-2xl">{{ $tenant->name }}</h1>
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ route('dashboard') }}" class="rounded-2xl border border-white/10 px-4 py-2 text-sm font-medium text-stone-200 transition hover:border-cyan-300/40 hover:text-white">
                        Dashboard
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-2xl border border-white/10 px-4 py-2 text-sm font-medium text-stone-200 transition hover:border-cyan-300/40 hover:text-white">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <div class="mx-auto flex max-w-[1700px] pt-20">
            <aside class="hidden w-72 shrink-0 border-r border-white/10 bg-stone-950/80 lg:fixed lg:bottom-0 lg:top-20 lg:block">
                <div class="flex h-full flex-col px-6 py-8">
                    <div class="rounded-[2rem] border border-white/10 bg-gradient-to-br from-cyan-300/15 via-stone-900 to-blue-300/10 p-5">
                        <p class="text-xs uppercase tracking-[0.3em] text-cyan-200">Equipment</p>
                        <p class="mt-3 text-sm leading-6 text-stone-300">
                            Add booth equipment, browse your equipment records, and open each record to review pricing and maintenance details.
                        </p>
                    </div>

                    <nav class="mt-8 space-y-2">
                        <a href="{{ route('dashboard') }}" class="block rounded-2xl border border-white/10 px-4 py-3 text-sm text-stone-300 transition hover:border-white/20 hover:bg-white/5 hover:text-white">
                            Overview Dashboard
                        </a>
                        <a href="{{ route('packages.index') }}" class="block rounded-2xl border border-white/10 px-4 py-3 text-sm text-stone-300 transition hover:border-amber-300/40 hover:bg-white/5 hover:text-white">
                            Packages
                        </a>
                        <a href="{{ route('equipment.index') }}" class="block rounded-2xl border border-cyan-300/40 bg-white/5 px-4 py-3 text-sm font-medium text-white">
                            Equipment
                        </a>
                        <a href="{{ route('items.index') }}" class="block rounded-2xl border border-white/10 px-4 py-3 text-sm text-stone-300 transition hover:border-rose-300/40 hover:bg-white/5 hover:text-white">
                            Inventory Items
                        </a>
                    </nav>

                    <div class="mt-8 rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Total Equipment</p>
                        <p class="mt-2 text-3xl font-semibold">{{ $equipment->count() }}</p>
                    </div>
                </div>
            </aside>

            <main class="min-w-0 flex-1 px-4 py-8 sm:px-6 lg:ml-72 lg:px-8">
                <div class="mx-auto max-w-7xl space-y-6">
                    <section class="rounded-[2rem] border border-white/10 bg-gradient-to-br from-cyan-300/15 via-stone-900 to-blue-300/10 p-6 shadow-2xl shadow-black/20 sm:p-8">
                        <p class="text-sm uppercase tracking-[0.35em] text-cyan-200">Equipment Workspace</p>
                        <h2 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Create and review photobooth equipment</h2>
                        <p class="mt-3 max-w-3xl text-sm leading-6 text-stone-300">
                            The left panel creates equipment records, the middle panel lists created equipment, and the right panel shows the selected equipment record.
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
                                <p class="text-sm uppercase tracking-[0.3em] text-cyan-200">Add Equipment</p>
                                <h3 class="mt-2 text-2xl font-semibold">New record</h3>
                            </div>

                            <form method="POST" action="{{ route('equipment.store') }}" enctype="multipart/form-data" class="space-y-4">
                                @csrf
                                <input name="name" type="text" value="{{ old('name') }}" placeholder="DSLR Camera Booth" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50" required>
                                <input name="category" type="text" value="{{ old('category') }}" placeholder="Camera, Printer, Lighting" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50">
                                <input name="serial_number" type="text" value="{{ old('serial_number') }}" placeholder="Serial number" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50">
                                <textarea name="description" rows="4" placeholder="Notes about this equipment" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50">{{ old('description') }}</textarea>
                                <input name="daily_rate" type="number" min="0" step="0.01" value="{{ old('daily_rate') }}" placeholder="Daily rental price" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50" required>
                                <select name="maintenance_status" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50">
                                    @foreach ($maintenanceStatuses as $status)
                                        <option value="{{ $status }}" @selected(old('maintenance_status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                                    @endforeach
                                </select>
                                <input name="last_maintained_at" type="date" value="{{ old('last_maintained_at') }}" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50" onkeydown="return false">
                                <textarea name="maintenance_notes" rows="4" placeholder="Maintenance notes" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50">{{ old('maintenance_notes') }}</textarea>
                                <input name="photo" type="file" accept="image/*" class="block w-full rounded-2xl border border-dashed border-white/15 bg-stone-950/70 px-4 py-3 text-sm text-stone-300 file:mr-4 file:rounded-full file:border-0 file:bg-cyan-300 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-stone-950">
                                <button type="submit" class="w-full rounded-2xl bg-cyan-300 px-4 py-3 text-sm font-semibold text-stone-950 transition hover:bg-cyan-200">
                                    Save equipment
                                </button>
                            </form>
                        </div>

                        <div class="rounded-[2rem] border border-white/10 bg-white/5 p-4">
                            <div class="mb-4 px-2">
                                <p class="text-sm uppercase tracking-[0.3em] text-stone-400">Created Equipment</p>
                                <h3 class="mt-2 text-xl font-semibold">Equipment list</h3>
                            </div>

                            <div class="space-y-3">
                                @forelse ($equipment as $asset)
                                    <a href="{{ route('equipment.show', $asset) }}" class="block rounded-2xl border px-4 py-4 transition {{ $selectedEquipment?->is($asset) ? 'border-cyan-300/40 bg-cyan-300/10' : 'border-white/10 bg-stone-950/40 hover:border-white/20 hover:bg-white/5' }}">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="font-medium text-white">{{ $asset->name }}</p>
                                                <p class="mt-1 text-sm text-stone-400">{{ $asset->category ?: 'Uncategorized' }} · ${{ number_format((float) $asset->daily_rate, 2) }}/day</p>
                                            </div>
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ $asset->maintenance_status === 'ready' ? 'bg-emerald-400/15 text-emerald-200' : ($asset->maintenance_status === 'maintenance' ? 'bg-amber-300/15 text-amber-200' : 'bg-rose-400/15 text-rose-200') }}">
                                                {{ str($asset->maintenance_status)->replace('_', ' ')->title() }}
                                            </span>
                                        </div>
                                    </a>
                                @empty
                                    <div class="rounded-2xl border border-dashed border-white/15 bg-stone-950/40 px-4 py-5 text-sm text-stone-400">
                                        No equipment records yet.
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <div class="rounded-[2rem] border border-white/10 bg-white/5 p-6">
                            @if ($selectedEquipment)
                                <div class="mb-5 flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm uppercase tracking-[0.3em] text-cyan-200">Equipment Details</p>
                                        <h3 class="mt-2 text-2xl font-semibold">{{ $selectedEquipment->name }}</h3>
                                    </div>
                                    <span class="rounded-full px-3 py-1 text-xs font-medium {{ $selectedEquipment->maintenance_status === 'ready' ? 'bg-emerald-400/15 text-emerald-200' : ($selectedEquipment->maintenance_status === 'maintenance' ? 'bg-amber-300/15 text-amber-200' : 'bg-rose-400/15 text-rose-200') }}">
                                        {{ str($selectedEquipment->maintenance_status)->replace('_', ' ')->title() }}
                                    </span>
                                </div>

                                @if ($selectedEquipment->photo_path)
                                    <img src="{{ asset('storage/'.$selectedEquipment->photo_path) }}" alt="{{ $selectedEquipment->name }}" class="mb-5 h-56 w-full rounded-3xl object-cover">
                                @endif

                                <div class="mb-5 grid gap-4 sm:grid-cols-3">
                                    <div class="rounded-2xl border border-white/10 bg-stone-950/40 p-4">
                                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Daily Rate</p>
                                        <p class="mt-2 text-2xl font-semibold">${{ number_format((float) $selectedEquipment->daily_rate, 2) }}</p>
                                    </div>
                                    <div class="rounded-2xl border border-white/10 bg-stone-950/40 p-4">
                                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Category</p>
                                        <p class="mt-2 text-lg font-semibold">{{ $selectedEquipment->category ?: 'Uncategorized' }}</p>
                                    </div>
                                    <div class="rounded-2xl border border-white/10 bg-stone-950/40 p-4">
                                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Last Maintained</p>
                                        <p class="mt-2 text-lg font-semibold">{{ $selectedEquipment->last_maintained_at?->format('d M Y') ?: 'Not set' }}</p>
                                    </div>
                                </div>

                                <div class="mb-5 rounded-2xl border border-white/10 bg-stone-950/40 p-4">
                                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Notes</p>
                                    <p class="mt-3 text-sm leading-6 text-stone-300">
                                        {{ $selectedEquipment->description ?: 'No description added yet.' }}
                                    </p>
                                    <p class="mt-3 text-sm leading-6 text-stone-300">
                                        {{ $selectedEquipment->maintenance_notes ?: 'No maintenance notes added yet.' }}
                                    </p>
                                </div>

                                <form method="POST" action="{{ route('equipment.update', $selectedEquipment) }}" enctype="multipart/form-data" class="space-y-4">
                                    @csrf
                                    @method('PUT')
                                    <input name="name" type="text" value="{{ old('name', $selectedEquipment->name) }}" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50" required>
                                    <input name="category" type="text" value="{{ old('category', $selectedEquipment->category) }}" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50">
                                    <input name="serial_number" type="text" value="{{ old('serial_number', $selectedEquipment->serial_number) }}" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50">
                                    <textarea name="description" rows="4" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50">{{ old('description', $selectedEquipment->description) }}</textarea>
                                    <input name="daily_rate" type="number" min="0" step="0.01" value="{{ old('daily_rate', $selectedEquipment->daily_rate) }}" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50" required>
                                    <select name="maintenance_status" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50">
                                        @foreach ($maintenanceStatuses as $status)
                                            <option value="{{ $status }}" @selected(old('maintenance_status', $selectedEquipment->maintenance_status) === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                                        @endforeach
                                    </select>
                                    <input name="last_maintained_at" type="date" value="{{ old('last_maintained_at', optional($selectedEquipment->last_maintained_at)->format('Y-m-d')) }}" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50" onkeydown="return false">
                                    <textarea name="maintenance_notes" rows="4" class="w-full rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-white outline-none transition focus:border-cyan-300/50">{{ old('maintenance_notes', $selectedEquipment->maintenance_notes) }}</textarea>
                                    <input name="photo" type="file" accept="image/*" class="block w-full rounded-2xl border border-dashed border-white/15 bg-stone-950/70 px-4 py-3 text-sm text-stone-300 file:mr-4 file:rounded-full file:border-0 file:bg-white file:px-4 file:py-2 file:text-sm file:font-semibold file:text-stone-950">
                                    <button type="submit" class="w-full rounded-2xl border border-white/10 px-4 py-3 text-sm font-semibold text-white transition hover:border-cyan-300/40 hover:bg-white/5">
                                        Update equipment
                                    </button>
                                </form>
                            @else
                                <div class="flex h-full min-h-80 items-center justify-center rounded-[2rem] border border-dashed border-white/15 bg-stone-950/40 p-8 text-center text-stone-400">
                                    Create your first equipment record on the left to see its details here.
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </body>
</html>
