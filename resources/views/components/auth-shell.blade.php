@props([
    'heading',
    'subheading' => null,
    'tenant',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $heading }} | {{ $tenant->name }}</title>
        @if ($tenant->logo_path)
            <link rel="icon" type="image/png" href="{{ url(Storage::disk('public')->url($tenant->logo_path)) }}">
        @endif
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-stone-950 text-stone-50">
        <div class="relative min-h-screen overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(245,158,11,0.25),transparent_32%),linear-gradient(135deg,#0c0a09_0%,#1c1917_50%,#111827_100%)]"></div>
            <div class="relative mx-auto flex min-h-screen max-w-6xl items-center px-6 py-12">
                <div class="grid w-full gap-10 lg:grid-cols-[1.1fr_0.9fr]">
                    <section class="space-y-6">
                        <div class="flex items-center gap-4">
                            @if ($tenant->logo_path)
                                <img src="{{ Storage::disk('public')->url($tenant->logo_path) }}" alt="{{ $tenant->name }} logo" class="h-14 w-14 rounded-2xl object-cover shadow-lg shadow-black/20">
                            @else
                                <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-lg font-semibold text-stone-300">
                                    {{ \Illuminate\Support\Str::of($tenant->name)->substr(0, 1) }}
                                </div>
                            @endif
                            <p class="inline-flex items-center rounded-full border border-amber-400/30 bg-amber-300/10 px-3 py-1 text-sm font-medium text-amber-200">
                                {{ $tenant->name }}
                            </p>
                        </div>
                        <div class="space-y-4">
                            <h1 class="max-w-xl text-4xl font-semibold tracking-tight text-white sm:text-5xl">{{ $heading }}</h1>
                            @if ($subheading)
                                <p class="max-w-2xl text-base leading-7 text-stone-300 sm:text-lg">{{ $subheading }}</p>
                            @endif
                        </div>
                    </section>

                    <section class="rounded-3xl border border-white/10 bg-white/8 p-8 shadow-2xl shadow-black/30 backdrop-blur">
                        {{ $slot }}
                    </section>
                </div>
            </div>
        </div>
    </body>
</html>
