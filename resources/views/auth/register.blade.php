<x-auth-shell
    :tenant="$tenant"
    heading="Create the first owner account"
    subheading="Registration is only open while this tenant has no users yet."
>
    @if (! $registrationOpen)
        <div class="rounded-2xl border border-amber-400/30 bg-amber-300/10 p-4 text-sm leading-6 text-amber-100">
            Registration is closed for this tenant. Ask an existing administrator to invite you instead.
        </div>
    @endif

    <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-5">
        @csrf

        <div class="space-y-2">
            <label for="name" class="text-sm font-medium text-stone-200">Full name</label>
            <input
                id="name"
                name="name"
                type="text"
                value="{{ old('name') }}"
                required
                class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none ring-0 placeholder:text-stone-500 focus:border-amber-300/60"
                placeholder="Alex Morgan"
            >
            @error('name')
                <p class="text-sm text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-2">
            <label for="email" class="text-sm font-medium text-stone-200">Email address</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                required
                class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none ring-0 placeholder:text-stone-500 focus:border-amber-300/60"
                placeholder="you@company.com"
            >
            @error('email')
                <p class="text-sm text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div class="rounded-2xl border border-white/10 bg-white/[0.04] p-4 text-sm leading-6 text-stone-300">
            This account will use passwordless sign-in. After registration, use your email address to receive one-time login codes.
        </div>

        <button
            type="submit"
            @disabled(! $registrationOpen)
            class="w-full rounded-2xl bg-amber-300 px-4 py-3 font-semibold text-stone-950 transition hover:bg-amber-200 disabled:cursor-not-allowed disabled:bg-stone-500 disabled:text-stone-200"
        >
            Create owner account
        </button>
    </form>

    <div class="mt-6 border-t border-white/10 pt-6 text-sm text-stone-300">
        <p>Already have access? <a href="{{ route('login') }}" class="font-medium text-amber-200 hover:text-amber-100">Sign in instead</a></p>
    </div>
</x-auth-shell>
