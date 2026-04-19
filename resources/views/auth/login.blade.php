<x-auth-shell
    :tenant="$tenant"
    heading="Sign in to your workspace"
    subheading="Enter your email address and we will send a six-digit code to complete sign-in."
>
    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf

        @if (session('status'))
            <div class="rounded-2xl border border-emerald-300/20 bg-emerald-300/10 px-4 py-3 text-sm text-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        <div class="space-y-2">
            <label for="email" class="text-sm font-medium text-stone-200">Email address</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                required
                autofocus
                class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none ring-0 placeholder:text-stone-500 focus:border-amber-300/60"
                placeholder="you@company.com"
            >
            @error('email')
                <p class="text-sm text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-3 text-sm text-stone-300">
            <input type="checkbox" name="remember" class="h-4 w-4 rounded border-white/20 bg-black/20 text-amber-300 focus:ring-amber-300">
            <span>Keep me signed in</span>
        </label>

        <button type="submit" class="w-full rounded-2xl bg-amber-300 px-4 py-3 font-semibold text-stone-950 transition hover:bg-amber-200">
            Send login code
        </button>
    </form>

    <div class="mt-6 border-t border-white/10 pt-6 text-sm text-stone-300">
        <p>New workspace setup? <a href="{{ route('register') }}" class="font-medium text-amber-200 hover:text-amber-100">Create the first owner account</a></p>
    </div>
</x-auth-shell>
