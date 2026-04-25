<x-auth-shell
    :tenant="$tenant"
    heading="Open your client portal"
    subheading="Enter the same email address your booking used and we will send a six-digit code before showing your bookings."
>
    <form method="POST" action="{{ route('client.portal.send-code') }}" class="space-y-5">
        @csrf

        @if (session('status'))
            <div class="rounded-2xl border border-emerald-300/20 bg-emerald-300/10 px-4 py-3 text-sm text-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        <div class="space-y-2">
            <label for="email" class="text-sm font-medium text-stone-200">Booking email</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email', $prefillEmail) }}"
                required
                autofocus
                class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none ring-0 placeholder:text-stone-500 focus:border-cyan-300/60"
                placeholder="you@example.com"
            >
            @error('email')
                <p class="text-sm text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <input type="hidden" name="access" value="{{ $accessToken }}">

        <button type="submit" class="w-full rounded-2xl bg-cyan-300 px-4 py-3 font-semibold text-slate-950 transition hover:bg-cyan-200">
            Send portal code
        </button>
    </form>
</x-auth-shell>
