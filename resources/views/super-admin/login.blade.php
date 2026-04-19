<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Platform Admin Login</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-50">
        <main class="flex min-h-screen items-center justify-center overflow-hidden px-6 py-12">
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,rgba(56,189,248,0.24),transparent_36%),radial-gradient(circle_at_bottom_right,rgba(251,191,36,0.16),transparent_34%)]"></div>
            <section class="w-full max-w-md rounded-[2rem] border border-white/10 bg-white/[0.06] p-8 shadow-2xl shadow-black/30 backdrop-blur">
                <p class="text-xs uppercase tracking-[0.35em] text-sky-200">Photobooth CRM</p>
                <h1 class="mt-4 text-3xl font-semibold tracking-tight">Platform admin</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300">
                    Enter the configured super-admin email and we will send a one-time access code.
                </p>

                @if ($errors->any())
                    <div class="mt-6 rounded-2xl border border-rose-400/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('super-admin.login.store') }}" class="mt-6 space-y-5">
                    @csrf
                    <div class="space-y-2">
                        <label for="email" class="text-sm font-medium text-slate-200">Super-admin email</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            class="w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-sky-300/60"
                            placeholder="jtpayoran@yahoo.com"
                        >
                    </div>
                    <button type="submit" class="w-full rounded-2xl bg-sky-300 px-4 py-3 font-semibold text-slate-950 transition hover:bg-sky-200">
                        Send admin code
                    </button>
                </form>
            </section>
        </main>
    </body>
</html>
