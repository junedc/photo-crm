<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Verify Platform Admin</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-50">
        <main class="flex min-h-screen items-center justify-center overflow-hidden px-6 py-12">
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,rgba(56,189,248,0.24),transparent_36%),radial-gradient(circle_at_bottom_right,rgba(251,191,36,0.16),transparent_34%)]"></div>
            <section class="w-full max-w-md rounded-[2rem] border border-white/10 bg-white/[0.06] p-8 shadow-2xl shadow-black/30 backdrop-blur">
                <p class="text-xs uppercase tracking-[0.35em] text-sky-200">Secure access</p>
                <h1 class="mt-4 text-3xl font-semibold tracking-tight">Check your inbox</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300">
                    Enter the six-digit code to open the platform admin area.
                </p>

                @if (session('status'))
                    <div class="mt-6 rounded-2xl border border-emerald-300/20 bg-emerald-300/10 px-4 py-3 text-sm text-emerald-100">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mt-6 rounded-2xl border border-rose-400/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('super-admin.verify.store') }}" class="mt-6 space-y-5">
                    @csrf
                    <div class="space-y-2">
                        <label for="code" class="text-sm font-medium text-slate-200">Verification code</label>
                        <input
                            id="code"
                            name="code"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="6"
                            value="{{ old('code') }}"
                            required
                            autofocus
                            autocomplete="one-time-code"
                            class="w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-center text-2xl tracking-[0.5em] text-white outline-none transition placeholder:text-slate-500 focus:border-sky-300"
                            placeholder="000000"
                        >
                    </div>
                    <button type="submit" class="w-full rounded-2xl bg-sky-300 px-4 py-3 font-semibold text-slate-950 transition hover:bg-sky-200">
                        Verify and continue
                    </button>
                </form>

                <form method="POST" action="{{ route('super-admin.verify.resend') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="w-full rounded-2xl border border-white/10 px-4 py-3 text-sm font-medium text-slate-100 transition hover:border-sky-300 hover:text-sky-200">
                        Resend code
                    </button>
                </form>
            </section>
        </main>
    </body>
</html>
