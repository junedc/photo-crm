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
            <main class="relative mx-auto grid min-h-screen max-w-6xl items-center gap-10 px-6 py-12 lg:grid-cols-[1.1fr_0.9fr]">
                <section class="space-y-6">
                    <p class="inline-flex items-center rounded-full border border-cyan-300/25 bg-cyan-300/10 px-3 py-1 text-sm font-medium text-cyan-100">
                        Central workspace setup
                    </p>
                    <div class="space-y-4">
                        <h1 class="max-w-2xl text-4xl font-semibold tracking-tight text-white sm:text-5xl">Create your first MemoShot tenant and owner account.</h1>
                        <p class="max-w-2xl text-base leading-7 text-stone-300 sm:text-lg">
                            This creates a client workspace on its own subdomain, for example <span class="font-medium text-white">acme.memoshot.test</span>, and signs you in as the first owner.
                        </p>
                    </div>
                </section>

                <section class="rounded-3xl border border-white/10 bg-white/8 p-8 shadow-2xl shadow-black/30 backdrop-blur">
                    <form method="POST" action="{{ route('workspaces.store') }}" class="space-y-5">
                        @csrf

                        <div class="space-y-2">
                            <label for="tenant_name" class="text-sm font-medium text-stone-200">Workspace name</label>
                            <input id="tenant_name" name="tenant_name" type="text" value="{{ old('tenant_name') }}" required class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none placeholder:text-stone-500 focus:border-cyan-300/60" placeholder="Acme Studio">
                            @error('tenant_name')
                                <p class="text-sm text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="tenant_slug" class="text-sm font-medium text-stone-200">Subdomain</label>
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
                            This owner account uses passwordless sign-in. After setup, use your email address to receive a one-time login code.
                        </div>

                        <button type="submit" class="w-full rounded-2xl bg-cyan-300 px-4 py-3 font-semibold text-stone-950 transition hover:bg-cyan-200">
                            Create workspace
                        </button>
                    </form>
                </section>
            </main>
        </div>
    </body>
</html>
