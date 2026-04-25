<x-auth-shell
    :tenant="$tenant"
    heading="Verify your portal access"
    subheading="Enter the six-digit code we sent to your booking email. If it expires, you can request a fresh one here."
>
    @if (session('status'))
        <div class="mb-5 rounded-2xl border border-emerald-300/20 bg-emerald-300/10 px-4 py-3 text-sm text-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('client.portal.verify.store') }}" class="space-y-5">
        @csrf

        <div class="space-y-2">
            <label for="code" class="text-sm font-medium text-stone-200">Verification code</label>
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
                class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-center text-2xl tracking-[0.5em] text-white outline-none transition placeholder:text-stone-500 focus:border-cyan-300"
                placeholder="000000"
            >
            @error('code')
                <p class="text-sm text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full rounded-2xl bg-cyan-300 px-4 py-3 font-semibold text-slate-950 transition hover:bg-cyan-200">
            Verify and continue
        </button>
    </form>

    <form method="POST" action="{{ route('client.portal.verify.resend') }}" class="mt-4">
        @csrf
        <button type="submit" class="w-full rounded-2xl border border-white/10 px-4 py-3 text-sm font-medium text-stone-100 transition hover:border-cyan-300 hover:text-cyan-200">
            Resend code
        </button>
    </form>
</x-auth-shell>
