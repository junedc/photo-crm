<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Platform Admin</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-50">
        <main class="w-full px-6 py-8 sm:px-8">
            <header class="flex flex-col gap-5 rounded-[2rem] border border-white/10 bg-white/[0.05] p-6 shadow-2xl shadow-black/20 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.35em] text-sky-200">Platform Admin</p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight">Tenants and subscriptions</h1>
                    <p class="mt-2 text-sm text-slate-300">
                        Manage workspace plan, price, validity, and current access status from the central domain.
                    </p>
                </div>
                <form method="POST" action="{{ route('super-admin.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-2xl border border-white/10 px-4 py-3 text-sm font-medium text-slate-100 transition hover:border-sky-300/50 hover:text-sky-200">
                        Sign out
                    </button>
                </form>
            </header>

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

            <section class="mt-8 grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
                <div class="rounded-[2rem] border border-white/10 bg-white/[0.04] p-5">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-sky-200">Subscription Maintenance</p>
                    <h2 class="mt-3 text-2xl font-semibold">Available plans</h2>
                    <p class="mt-2 text-sm text-slate-400">These are the plans tenants can choose from in workspace settings.</p>

                    <form method="POST" action="{{ route('super-admin.subscriptions.store') }}" class="mt-5 space-y-3 rounded-2xl border border-sky-300/20 bg-sky-300/10 p-4">
                        @csrf
                        <div class="grid gap-3 sm:grid-cols-2">
                            <input name="name" type="text" required placeholder="Plan name" class="rounded-xl border border-white/10 bg-slate-950/80 px-3 py-2 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-sky-300">
                            <select name="billing_period" class="rounded-xl border border-white/10 bg-slate-950/80 px-3 py-2 text-sm text-white outline-none transition focus:border-sky-300">
                                @foreach ($billingPeriods as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <input name="price" type="number" min="0" step="0.01" value="0.00" class="rounded-xl border border-white/10 bg-slate-950/80 px-3 py-2 text-sm text-white outline-none transition focus:border-sky-300">
                            <input name="currency" type="text" maxlength="3" value="{{ $platformCurrency }}" class="rounded-xl border border-white/10 bg-slate-950/80 px-3 py-2 text-sm uppercase text-white outline-none transition focus:border-sky-300">
                            <input name="validity_count" type="number" min="1" placeholder="Validity count" class="rounded-xl border border-white/10 bg-slate-950/80 px-3 py-2 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-sky-300">
                            <select name="validity_unit" class="rounded-xl border border-white/10 bg-slate-950/80 px-3 py-2 text-sm text-white outline-none transition focus:border-sky-300">
                                <option value="">No expiry</option>
                                @foreach ($validityUnits as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <textarea name="description" rows="2" placeholder="Description shown to tenants" class="w-full rounded-xl border border-white/10 bg-slate-950/80 px-3 py-2 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-sky-300"></textarea>
                        <label class="flex items-center gap-2 text-sm text-slate-200">
                            <input name="is_active" type="hidden" value="0">
                            <input name="is_active" type="checkbox" value="1" checked class="h-4 w-4 rounded border-white/20 bg-slate-950 text-sky-300 focus:ring-sky-300">
                            Active and selectable by tenants
                        </label>
                        <button type="submit" class="w-full rounded-xl bg-sky-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-200">
                            Add subscription
                        </button>
                    </form>

                    <div class="mt-5 space-y-4">
                        @foreach ($subscriptions as $subscription)
                            <form method="POST" action="{{ route('super-admin.subscriptions.update', $subscription) }}" class="rounded-2xl border border-white/10 bg-slate-950/50 p-4">
                                @csrf
                                @method('PUT')
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <input name="name" type="text" value="{{ old('name', $subscription->name) }}" required class="rounded-xl border border-white/10 bg-slate-950/80 px-3 py-2 text-sm text-white outline-none transition focus:border-sky-300">
                                    <select name="billing_period" class="rounded-xl border border-white/10 bg-slate-950/80 px-3 py-2 text-sm text-white outline-none transition focus:border-sky-300">
                                        @foreach ($billingPeriods as $value => $label)
                                            <option value="{{ $value }}" @selected(old('billing_period', $subscription->billing_period) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <input name="price" type="number" min="0" step="0.01" value="{{ old('price', $subscription->price) }}" class="rounded-xl border border-white/10 bg-slate-950/80 px-3 py-2 text-sm text-white outline-none transition focus:border-sky-300">
                                    <input name="currency" type="text" maxlength="3" value="{{ old('currency', $subscription->currency) }}" class="rounded-xl border border-white/10 bg-slate-950/80 px-3 py-2 text-sm uppercase text-white outline-none transition focus:border-sky-300">
                                    <input name="validity_count" type="number" min="1" value="{{ old('validity_count', $subscription->validity_count) }}" placeholder="Validity count" class="rounded-xl border border-white/10 bg-slate-950/80 px-3 py-2 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-sky-300">
                                    <select name="validity_unit" class="rounded-xl border border-white/10 bg-slate-950/80 px-3 py-2 text-sm text-white outline-none transition focus:border-sky-300">
                                        <option value="">No expiry</option>
                                        @foreach ($validityUnits as $value => $label)
                                            <option value="{{ $value }}" @selected(old('validity_unit', $subscription->validity_unit) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <textarea name="description" rows="2" class="mt-3 w-full rounded-xl border border-white/10 bg-slate-950/80 px-3 py-2 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-sky-300" placeholder="Description shown to tenants">{{ old('description', $subscription->description) }}</textarea>
                                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <label class="flex items-center gap-2 text-sm text-slate-200">
                                        <input name="is_active" type="hidden" value="0">
                                        <input name="is_active" type="checkbox" value="1" @checked(old('is_active', $subscription->is_active)) class="h-4 w-4 rounded border-white/20 bg-slate-950 text-sky-300 focus:ring-sky-300">
                                        Active
                                    </label>
                                    <p class="text-xs text-slate-500">{{ $subscription->tenants_count }} tenants selected this plan</p>
                                    <button type="submit" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-sky-300/50 hover:bg-white/5">
                                        Save plan
                                    </button>
                                </div>
                            </form>
                        @endforeach
                    </div>
                </div>

                <div class="overflow-hidden rounded-[2rem] border border-white/10 bg-white/[0.04]">
                    <div class="border-b border-white/10 p-5">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-sky-200">Tenant Access</p>
                        <h2 class="mt-3 text-2xl font-semibold">Selected subscriptions</h2>
                        <p class="mt-2 text-sm text-slate-400">Super admin can view selected plans and manually enable or disable tenant access.</p>
                    </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-white/[0.03] text-left text-[11px] uppercase tracking-[0.3em] text-slate-400">
                            <tr>
                                <th class="px-5 py-4">Tenant</th>
                                <th class="px-5 py-4">Users</th>
                                <th class="px-5 py-4">Selected Subscription</th>
                                <th class="px-5 py-4">Access</th>
                                <th class="px-5 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            @forelse ($tenants as $tenant)
                                @php($subscription = $tenant->subscription)
                                @php($latestCharge = $tenant->latestSubscriptionCharge)
                                <tr class="align-top">
                                    <td class="px-5 py-5">
                                        <p class="font-semibold text-white">{{ $tenant->name }}</p>
                                        <p class="mt-1 text-sm text-slate-400">{{ $tenant->slug }}.{{ $baseDomain }}</p>
                                        <p class="mt-2 text-xs uppercase tracking-[0.2em] text-slate-500">Theme: {{ $tenant->theme ?: 'dark' }}</p>
                                    </td>
                                    <td class="px-5 py-5 text-sm text-slate-300">
                                        {{ $tenant->users_count }}
                                    </td>
                                    <td class="px-5 py-5 text-sm">
                                        @if ($subscription)
                                            <p class="font-semibold text-white">{{ $subscription->name }}</p>
                                            <p class="mt-1 text-slate-400">{{ strtoupper($subscription->currency) }} {{ number_format((float) $subscription->price, 2) }} · {{ $billingPeriods[$subscription->billing_period] ?? $subscription->billing_period }}</p>
                                            <p class="mt-1 text-xs text-slate-500">
                                                Validity:
                                                @if ($subscription->billing_period === 'free_for_life' || ! $subscription->validity_count)
                                                    No expiry
                                                @else
                                                    {{ $subscription->validity_count }} {{ $subscription->validity_unit }}{{ $subscription->validity_count > 1 ? 's' : '' }}
                                                @endif
                                            </p>
                                            @if ($latestCharge)
                                                <p class="mt-2 text-xs text-slate-400">
                                                    Latest charge: {{ strtoupper($latestCharge->currency) }} {{ number_format((float) $latestCharge->amount, 2) }}
                                                    for {{ $latestCharge->period_starts_at->format('d M Y') }}
                                                    @if ($latestCharge->period_ends_at)
                                                        - {{ $latestCharge->period_ends_at->format('d M Y') }}
                                                    @endif
                                                </p>
                                                <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ in_array($latestCharge->status, ['paid', 'waived'], true) ? 'bg-emerald-300/15 text-emerald-200' : 'bg-amber-300/15 text-amber-100' }}">
                                                    {{ ucfirst($latestCharge->status) }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-slate-500">No subscription selected</span>
                                        @endif
                                        <form method="POST" action="{{ route('super-admin.tenants.subscription.update', $tenant) }}" class="mt-4 flex flex-col gap-2 sm:flex-row">
                                            @csrf
                                            @method('PUT')
                                            <select name="subscription_id" class="min-w-56 rounded-xl border border-white/10 bg-slate-950/80 px-3 py-2 text-sm text-white outline-none transition focus:border-sky-300">
                                                <option value="">No subscription</option>
                                                @foreach ($subscriptionOptions as $option)
                                                    <option value="{{ $option->id }}" @selected((int) $tenant->subscription_id === (int) $option->id)>
                                                        {{ $option->name }} · {{ strtoupper($option->currency) }} {{ number_format((float) $option->price, 2) }} · {{ $billingPeriods[$option->billing_period] ?? $option->billing_period }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="rounded-xl border border-white/10 px-3 py-2 text-sm font-semibold text-white transition hover:border-sky-300/50 hover:bg-white/5">
                                                Assign
                                            </button>
                                        </form>
                                    </td>
                                    <td class="px-5 py-5">
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $tenant->subscription_enabled ? 'bg-emerald-300/15 text-emerald-200' : 'bg-rose-400/15 text-rose-200' }}">
                                            {{ $tenant->subscription_enabled ? 'Enabled' : 'Disabled' }}
                                        </span>
                                        @if ($tenant->subscription_disabled_at)
                                            <p class="mt-2 text-xs text-slate-500">Disabled {{ $tenant->subscription_disabled_at->format('d M Y') }}</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-5 text-right">
                                        <form method="POST" action="{{ route('super-admin.tenants.access.update', $tenant) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="subscription_enabled" value="{{ $tenant->subscription_enabled ? '0' : '1' }}">
                                            <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold transition {{ $tenant->subscription_enabled ? 'border border-rose-300/30 text-rose-100 hover:bg-rose-400/10' : 'bg-emerald-300 text-slate-950 hover:bg-emerald-200' }}">
                                                {{ $tenant->subscription_enabled ? 'Disable' : 'Enable' }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-12 text-center text-sm text-slate-400">
                                        No tenants yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                </div>
            </section>

            <section class="mt-8 overflow-hidden rounded-[2rem] border border-amber-300/20 bg-amber-300/10">
                <div class="border-b border-amber-300/20 p-5">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-amber-100">Unpaid Tenants</p>
                    <h2 class="mt-3 text-2xl font-semibold">Pending or failed subscription payments</h2>
                    <p class="mt-2 text-sm text-amber-50/80">This list is informational only. Tenant disabling remains a manual super-admin action.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-amber-300/20">
                        <thead class="bg-slate-950/30 text-left text-[11px] uppercase tracking-[0.3em] text-amber-100/70">
                            <tr>
                                <th class="px-5 py-4">Tenant</th>
                                <th class="px-5 py-4">Subscription</th>
                                <th class="px-5 py-4">Period</th>
                                <th class="px-5 py-4">Amount</th>
                                <th class="px-5 py-4">Status</th>
                                <th class="px-5 py-4">Access</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-amber-300/20">
                            @forelse ($unpaidSubscriptionCharges as $charge)
                                <tr>
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-white">{{ $charge->tenant?->name ?? 'Deleted tenant' }}</p>
                                        <p class="mt-1 text-sm text-amber-50/60">{{ $charge->tenant?->slug }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-amber-50/80">
                                        {{ $charge->subscription_name }}
                                        <p class="mt-1 text-xs text-amber-50/50">{{ $billingPeriods[$charge->billing_period] ?? $charge->billing_period }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-amber-50/80">
                                        {{ $charge->period_starts_at->format('d M Y') }}
                                        @if ($charge->period_ends_at)
                                            - {{ $charge->period_ends_at->format('d M Y') }}
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-sm text-amber-50/80">{{ strtoupper($charge->currency) }} {{ number_format((float) $charge->amount, 2) }}</td>
                                    <td class="px-5 py-4">
                                        <span class="rounded-full bg-amber-300/15 px-3 py-1 text-xs font-semibold text-amber-100">
                                            {{ ucfirst($charge->status) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        @if ($charge->tenant)
                                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $charge->tenant->subscription_enabled ? 'bg-emerald-300/15 text-emerald-200' : 'bg-rose-400/15 text-rose-200' }}">
                                                {{ $charge->tenant->subscription_enabled ? 'Enabled' : 'Disabled' }}
                                            </span>
                                        @else
                                            <span class="text-amber-50/50">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-12 text-center text-sm text-amber-50/70">
                                        No unpaid subscription charges.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mt-8 overflow-hidden rounded-[2rem] border border-white/10 bg-white/[0.04]">
                <div class="border-b border-white/10 p-5">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-sky-200">Subscription History</p>
                    <h2 class="mt-3 text-2xl font-semibold">Tenant fee snapshots</h2>
                    <p class="mt-2 text-sm text-slate-400">Each row stores the exact plan, period, amount, and payment status at the time it was charged.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-white/[0.03] text-left text-[11px] uppercase tracking-[0.3em] text-slate-400">
                            <tr>
                                <th class="px-5 py-4">Tenant</th>
                                <th class="px-5 py-4">Subscription Snapshot</th>
                                <th class="px-5 py-4">Period</th>
                                <th class="px-5 py-4">Amount</th>
                                <th class="px-5 py-4">Status</th>
                                <th class="px-5 py-4">Paid</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            @forelse ($subscriptionCharges as $charge)
                                <tr>
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-white">{{ $charge->tenant?->name ?? 'Deleted tenant' }}</p>
                                        <p class="mt-1 text-sm text-slate-500">{{ $charge->tenant?->slug }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-300">
                                        {{ $charge->subscription_name }}
                                        <p class="mt-1 text-xs text-slate-500">{{ $billingPeriods[$charge->billing_period] ?? $charge->billing_period }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-300">
                                        {{ $charge->period_starts_at->format('d M Y') }}
                                        @if ($charge->period_ends_at)
                                            - {{ $charge->period_ends_at->format('d M Y') }}
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-300">{{ strtoupper($charge->currency) }} {{ number_format((float) $charge->amount, 2) }}</td>
                                    <td class="px-5 py-4">
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ in_array($charge->status, ['paid', 'waived'], true) ? 'bg-emerald-300/15 text-emerald-200' : 'bg-amber-300/15 text-amber-100' }}">
                                            {{ ucfirst($charge->status) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-400">{{ $charge->paid_at?->format('d M Y g:i A') ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-12 text-center text-sm text-slate-400">
                                        No subscription payment history yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </body>
</html>
