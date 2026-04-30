<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Unsubscribed</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-stone-950 text-stone-50" data-theme="dark">
        <main class="flex min-h-screen items-center justify-center px-6">
            <section class="max-w-lg rounded-3xl border border-white/10 bg-white/[0.04] p-8 text-center shadow-2xl shadow-black/30">
                <p class="text-[11px] uppercase tracking-[0.35em] text-rose-200">Campaign Preferences</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight">You are unsubscribed</h1>
                <p class="mt-4 text-sm leading-6 text-stone-300">
                    {{ $recipient->email }} has been removed from subscriber groups. You will no longer receive campaign emails from this workspace.
                </p>
            </section>
        </main>
    </body>
</html>
