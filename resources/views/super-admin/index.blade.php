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
            <header class="flex flex-col gap-4 rounded-[2rem] border border-white/10 bg-white/[0.05] px-6 py-4 shadow-2xl shadow-black/20 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.35em] text-sky-200">Platform Admin</p>
                    <h1 class="mt-2 text-base font-semibold italic tracking-tight text-slate-100">
                        Tenants and subscriptions <span class="font-normal text-slate-400">/ Manage workspace plan, price, validity, and access status.</span>
                    </h1>
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

            <div class="mt-8 grid gap-6 lg:grid-cols-[230px_minmax(0,1fr)]">
                <aside class="lg:sticky lg:top-6 lg:self-start">
                    <nav class="overflow-hidden rounded-[1.5rem] border border-white/10 bg-white/[0.04] text-sm shadow-xl shadow-black/20">
                        <a href="#plans" class="block border-b border-white/10 px-4 py-3 font-semibold text-sky-100 transition hover:bg-sky-300/10">Subscription plans</a>
                        <a href="#tenants" class="block border-b border-white/10 px-4 py-3 text-slate-300 transition hover:bg-white/5 hover:text-white">Tenant access</a>
                        <a href="#support" class="block border-b border-white/10 px-4 py-3 text-slate-300 transition hover:bg-white/5 hover:text-white">Support tickets</a>
                        <a href="#referrals" class="block border-b border-white/10 px-4 py-3 text-slate-300 transition hover:bg-white/5 hover:text-white">Referrals</a>
                        <a href="#unpaid" class="block border-b border-white/10 px-4 py-3 text-slate-300 transition hover:bg-white/5 hover:text-white">Unpaid tenants</a>
                        <a href="#history" class="block border-b border-white/10 px-4 py-3 text-slate-300 transition hover:bg-white/5 hover:text-white">Payment history</a>
                        <a href="#tools" class="block border-b border-white/10 px-4 py-3 text-slate-300 transition hover:bg-white/5 hover:text-white">SSH tools</a>
                        <a href="#environment" class="block px-4 py-3 text-slate-300 transition hover:bg-white/5 hover:text-white">.env editor</a>
                    </nav>
                </aside>

                <div class="space-y-6">
            <section id="plans" class="grid scroll-mt-6 gap-6">
                <div class="overflow-hidden rounded-[1.5rem] border border-white/10 bg-white/[0.04]">
                    <div class="flex flex-col gap-3 border-b border-white/10 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.3em] text-sky-200">Subscription Maintenance</p>
                            <h2 class="mt-2 text-base font-semibold italic text-white">Available plans <span class="font-normal text-slate-400">/ view plans and edit from the action column.</span></h2>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl bg-sky-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-200"
                            data-plan-modal-open
                            data-mode="create"
                            data-action="{{ route('super-admin.subscriptions.store') }}"
                        >
                            Add plan
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/10">
                            <thead class="bg-white/[0.03] text-left text-[11px] uppercase tracking-[0.22em] text-slate-400">
                                <tr>
                                    <th class="px-4 py-3">Plan</th>
                                    <th class="px-4 py-3">Period</th>
                                    <th class="px-4 py-3">Price</th>
                                    <th class="px-4 py-3">Validity</th>
                                    <th class="px-4 py-3">Active</th>
                                    <th class="px-4 py-3">Tenants</th>
                                    <th class="px-4 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                @foreach ($subscriptions as $subscription)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-white">{{ $subscription->name }}</p>
                                            <p class="mt-1 max-w-sm text-xs text-slate-500">{{ $subscription->description ?: 'No description.' }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-300">
                                            {{ $billingPeriods[$subscription->billing_period] ?? $subscription->billing_period }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-300">
                                            {{ strtoupper($subscription->currency) }} {{ number_format((float) $subscription->price, 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-300">
                                            @if ($subscription->billing_period === 'free_for_life' || ! $subscription->validity_count)
                                                No expiry
                                            @else
                                                {{ $subscription->validity_count }} {{ $subscription->validity_unit }}{{ $subscription->validity_count > 1 ? 's' : '' }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $subscription->is_active ? 'bg-emerald-300/15 text-emerald-200' : 'bg-slate-300/15 text-slate-300' }}">
                                                {{ $subscription->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-400">{{ $subscription->tenants_count }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <button
                                                type="button"
                                                class="rounded-lg border border-white/10 px-3 py-2 text-sm font-semibold text-white transition hover:border-sky-300/50 hover:bg-white/5"
                                                data-plan-modal-open
                                                data-mode="edit"
                                                data-action="{{ route('super-admin.subscriptions.update', $subscription) }}"
                                                data-name="{{ e($subscription->name) }}"
                                                data-billing-period="{{ e($subscription->billing_period) }}"
                                                data-price="{{ number_format((float) $subscription->price, 2, '.', '') }}"
                                                data-currency="{{ e($subscription->currency) }}"
                                                data-validity-count="{{ $subscription->validity_count }}"
                                                data-validity-unit="{{ e($subscription->validity_unit) }}"
                                                data-description="{{ e($subscription->description) }}"
                                                data-is-active="{{ $subscription->is_active ? '1' : '0' }}"
                                            >
                                                Edit
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="tenants" class="scroll-mt-6 rounded-[1.5rem] border border-white/10 bg-white/[0.04]">
                    <div class="border-b border-white/10 p-5">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-sky-200">Tenant Access</p>
                        <h2 class="mt-1 text-sm font-semibold italic text-white">Selected subscriptions <span class="font-normal text-slate-400">/ manually enable or disable tenant access.</span></h2>
                    </div>
                <div class="overflow-x-auto overflow-y-visible">
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
                                    </td>
                                    <td class="px-5 py-5 text-sm text-slate-300">
                                        {{ $tenant->users_count }}
                                    </td>
                                    <td class="px-5 py-5 text-sm">
                                        @if ($subscription)
                                            <p class="font-semibold text-white">
                                                {{ $subscription->name }}
                                                <span class="font-normal text-slate-400">· {{ strtoupper($subscription->currency) }} {{ number_format((float) $subscription->price, 2) }} · {{ $billingPeriods[$subscription->billing_period] ?? $subscription->billing_period }}</span>
                                            </p>
                                            <p class="mt-1 text-sm text-slate-400">
                                                @if ($subscription->billing_period === 'free_for_life' || ! $subscription->validity_count)
                                                    Validity: No expiry
                                                @else
                                                    Validity: {{ $subscription->validity_count }} {{ $subscription->validity_unit }}{{ $subscription->validity_count > 1 ? 's' : '' }}
                                                @endif
                                                @if ($latestCharge)
                                                    · Latest: {{ strtoupper($latestCharge->currency) }} {{ number_format((float) $latestCharge->amount, 2) }} for {{ \App\Support\DateFormatter::date($latestCharge->period_starts_at) }}
                                                    @if ($latestCharge->period_ends_at)
                                                        - {{ \App\Support\DateFormatter::date($latestCharge->period_ends_at) }}
                                                    @endif
                                                    <span class="ml-2 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ in_array($latestCharge->status, ['paid', 'waived'], true) ? 'bg-emerald-300/15 text-emerald-200' : 'bg-amber-300/15 text-amber-100' }}">
                                                        {{ ucfirst($latestCharge->status) }}
                                                    </span>
                                                @endif
                                            </p>
                                        @else
                                            <span class="text-slate-500">No subscription selected</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-5">
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $tenant->subscription_enabled ? 'bg-emerald-300/15 text-emerald-200' : 'bg-rose-400/15 text-rose-200' }}">
                                            {{ $tenant->subscription_enabled ? 'Enabled' : 'Disabled' }}
                                        </span>
                                        @if ($tenant->subscription_disabled_at)
                                            <p class="mt-2 text-xs text-slate-500">Disabled {{ \App\Support\DateFormatter::date($tenant->subscription_disabled_at) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-5 text-right">
                                        <div class="relative inline-flex justify-end" data-tenant-action-menu>
                                            <button
                                                type="button"
                                                class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-sky-300/50 hover:bg-white/5"
                                                data-tenant-action-toggle
                                            >
                                                Action
                                            </button>

                                            <div class="absolute right-0 top-full z-50 mt-2 hidden min-w-44 overflow-hidden rounded-2xl border border-white/10 bg-slate-950 shadow-2xl shadow-black/40" data-tenant-action-panel>
                                                <button
                                                    type="button"
                                                    class="block w-full px-4 py-3 text-left text-sm font-semibold text-sky-100 transition hover:bg-sky-300/10"
                                                    data-tenant-subscription-modal-open
                                                    data-action="{{ route('super-admin.tenants.subscription.update', $tenant) }}"
                                                    data-tenant-name="{{ e($tenant->name) }}"
                                                    data-subscription-id="{{ $tenant->subscription_id }}"
                                                >
                                                    Change plan
                                                </button>
                                                <form method="POST" action="{{ route('super-admin.tenants.access.update', $tenant) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="subscription_enabled" value="{{ $tenant->subscription_enabled ? '0' : '1' }}">
                                                    <button type="submit" class="block w-full px-4 py-3 text-left text-sm font-semibold transition {{ $tenant->subscription_enabled ? 'text-rose-100 hover:bg-rose-400/10' : 'text-emerald-100 hover:bg-emerald-300/10' }}">
                                                        {{ $tenant->subscription_enabled ? 'Disable tenant' : 'Enable tenant' }}
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
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

            <section id="support" class="scroll-mt-6 overflow-hidden rounded-[1.5rem] border border-white/10 bg-white/[0.04]">
                <div class="border-b border-white/10 p-5">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-sky-200">Support Tickets</p>
                    <h2 class="mt-1 text-sm font-semibold italic text-white">Latest tenant bug reports <span class="font-normal text-slate-400">/ submitted from tenant admin.</span></h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-white/[0.03] text-left text-[11px] uppercase tracking-[0.25em] text-slate-400">
                            <tr>
                                <th class="px-5 py-4">Ticket</th>
                                <th class="px-5 py-4">Tenant</th>
                                <th class="px-5 py-4">Issue</th>
                                <th class="px-5 py-4">Priority</th>
                                <th class="px-5 py-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            @forelse ($supportTickets as $ticket)
                                <tr>
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-sky-100">{{ $ticket->ticket_number }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ \App\Support\DateFormatter::dateTime($ticket->created_at) }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-300">
                                        {{ $ticket->tenant?->name ?? 'Unknown tenant' }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-white">{{ $ticket->subject }}</p>
                                        <p class="mt-1 max-w-xl truncate text-xs text-slate-500">{{ $ticket->description }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $ticket->priority === 'urgent' ? 'bg-rose-400/15 text-rose-200' : ($ticket->priority === 'high' ? 'bg-amber-300/15 text-amber-100' : 'bg-sky-300/15 text-sky-100') }}">
                                            {{ ucfirst($ticket->priority) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="rounded-full bg-emerald-300/15 px-2.5 py-1 text-xs font-semibold text-emerald-200">
                                            {{ str($ticket->status)->replace('_', ' ')->title() }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">No support tickets yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="referrals" class="scroll-mt-6 overflow-hidden rounded-[1.5rem] border border-white/10 bg-white/[0.04]">
                <div class="border-b border-white/10 p-5">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-emerald-200">Tenant Referrals</p>
                    <h2 class="mt-1 text-sm font-semibold italic text-white">Platform referral activity <span class="font-normal text-slate-400">/ signups tracked from referral links.</span></h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-white/[0.03] text-left text-[11px] uppercase tracking-[0.25em] text-slate-400">
                            <tr>
                                <th class="px-5 py-4">Referrer</th>
                                <th class="px-5 py-4">Referred tenant</th>
                                <th class="px-5 py-4">Owner email</th>
                                <th class="px-5 py-4">Code</th>
                                <th class="px-5 py-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            @forelse ($tenantReferrals as $referral)
                                <tr>
                                    <td class="px-5 py-4 text-sm font-semibold text-white">{{ $referral->referrerTenant?->name ?? 'Unknown tenant' }}</td>
                                    <td class="px-5 py-4 text-sm text-slate-300">{{ $referral->referredTenant?->name ?? $referral->referred_workspace_name }}</td>
                                    <td class="px-5 py-4 text-sm text-slate-400">{{ $referral->referred_owner_email ?: 'Not recorded' }}</td>
                                    <td class="px-5 py-4 text-sm text-emerald-100">{{ $referral->referral_code }}</td>
                                    <td class="px-5 py-4">
                                        <span class="rounded-full bg-emerald-300/15 px-2.5 py-1 text-xs font-semibold text-emerald-200">
                                            {{ str($referral->status)->replace('_', ' ')->title() }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">No tenant referrals yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="unpaid" class="scroll-mt-6 overflow-hidden rounded-[1.5rem] border border-amber-300/20 bg-amber-300/10">
                <div class="border-b border-amber-300/20 p-5">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-amber-100">Unpaid Tenants</p>
                    <h2 class="mt-1 text-sm font-semibold italic text-amber-50">Pending or failed subscription payments <span class="font-normal text-amber-50/70">/ tenant disabling remains manual.</span></h2>
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
                                        {{ \App\Support\DateFormatter::date($charge->period_starts_at) }}
                                        @if ($charge->period_ends_at)
                                            - {{ \App\Support\DateFormatter::date($charge->period_ends_at) }}
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

            <section id="history" class="scroll-mt-6 overflow-hidden rounded-[1.5rem] border border-white/10 bg-white/[0.04]">
                <div class="border-b border-white/10 p-5">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-sky-200">Subscription History</p>
                    <h2 class="mt-1 text-sm font-semibold italic text-white">Tenant fee snapshots <span class="font-normal text-slate-400">/ exact plan, period, amount, and payment status.</span></h2>
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
                                        {{ \App\Support\DateFormatter::date($charge->period_starts_at) }}
                                        @if ($charge->period_ends_at)
                                            - {{ \App\Support\DateFormatter::date($charge->period_ends_at) }}
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-300">{{ strtoupper($charge->currency) }} {{ number_format((float) $charge->amount, 2) }}</td>
                                    <td class="px-5 py-4">
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ in_array($charge->status, ['paid', 'waived'], true) ? 'bg-emerald-300/15 text-emerald-200' : 'bg-amber-300/15 text-amber-100' }}">
                                            {{ ucfirst($charge->status) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-400">{{ \App\Support\DateFormatter::dateTime($charge->paid_at, '-') }}</td>
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

            <section id="tools" class="scroll-mt-6 rounded-[1.5rem] border border-white/10 bg-white/[0.04] px-5 py-4">
                <div class="flex flex-wrap items-center gap-3">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-sky-200">SSH Artisan</p>
                    <p class="text-sm text-slate-400">Copy and run from the Laravel app folder on the server.</p>
                    @php($sshCommand = 'ssh -p 65002 u838520432@157.173.209.64')
                    <button
                        type="button"
                        data-command="{{ $sshCommand }}"
                        class="rounded-full bg-sky-300 px-3 py-1.5 text-xs font-semibold text-slate-950 transition hover:bg-sky-200"
                        onclick="navigator.clipboard?.writeText(this.dataset.command); this.textContent = 'SSH copied'; setTimeout(() => this.textContent = 'Copy SSH login', 1200);"
                    >
                        Copy SSH login
                    </button>
                    <a
                        href="ssh://u838520432@157.173.209.64:65002"
                        class="rounded-full border border-sky-300/40 px-3 py-1.5 text-xs font-semibold text-sky-100 transition hover:bg-sky-300/10"
                    >
                        Open SSH
                    </a>
                    @php($artisanCommands = [
                        'Goto Laravel' => 'cd /home/u838520432/domains/memoshot.com/laravel_app',
                        'Clear cache' => '/opt/alt/php84/usr/bin/php artisan optimize:clear',
                        'Clear link' => 'rm -f /home/u838520432/domains/memoshot.com/public_html/storage',
                        'Storage link' => 'ln -s /home/u838520432/domains/memoshot.com/laravel_app/storage/app/public /home/u838520432/domains/memoshot.com/public_html/storage',
                        'Migrate' => '/opt/alt/php84/usr/bin/php artisan migrate',
                        'All' => 'cd /home/u838520432/domains/memoshot.com/laravel_app && \
/opt/alt/php84/usr/bin/php artisan optimize:clear && \
rm -f /home/u838520432/domains/memoshot.com/public_html/storage && \
ln -s /home/u838520432/domains/memoshot.com/laravel_app/storage/app/public /home/u838520432/domains/memoshot.com/public_html/storage && \
/opt/alt/php84/usr/bin/php artisan migrate
'])
                    <div class="flex flex-wrap gap-2">
                        @foreach ($artisanCommands as $label => $command)
                            <button
                                type="button"
                                data-command="{{ $command }}"
                                class="rounded-full border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:border-sky-300/50 hover:bg-sky-300/10 hover:text-sky-100"
                                onclick="navigator.clipboard?.writeText(this.dataset.command); this.textContent = 'Copied'; setTimeout(() => this.textContent = '{{ $label }}', 1200);"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap gap-2 border-l border-white/10 pl-3">
                        @php($stripeEndpoints = [
                            'Tenant webhook' => url('/stripe/webhook'),
                            'Platform webhook' => url('/platform/stripe/webhook'),
                        ])
                        @foreach ($stripeEndpoints as $label => $endpoint)
                            <button
                                type="button"
                                data-command="{{ $endpoint }}"
                                class="rounded-full border border-emerald-300/30 px-3 py-1.5 text-xs font-semibold text-emerald-100 transition hover:bg-emerald-300/10"
                                onclick="navigator.clipboard?.writeText(this.dataset.command); this.textContent = 'Copied'; setTimeout(() => this.textContent = '{{ $label }}', 1200);"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="environment" class="scroll-mt-6 rounded-[1.5rem] border border-rose-300/20 bg-rose-300/10 px-5 py-4">
                <details>
                    <summary class="cursor-pointer text-sm font-semibold text-rose-100">
                        Edit .env
                        <span class="ml-2 text-xs font-normal text-rose-100/70">Super admin only. Saving creates a timestamped backup.</span>
                    </summary>
                    <form method="POST" action="{{ route('super-admin.environment.update') }}" class="mt-4 space-y-3">
                        @csrf
                        <textarea
                            name="environment_content"
                            rows="18"
                            spellcheck="false"
                            class="w-full rounded-2xl border border-white/10 bg-slate-950/90 px-4 py-3 font-mono text-xs leading-5 text-slate-100 outline-none transition focus:border-rose-200"
                        >{{ old('environment_content', $environmentContent) }}</textarea>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs text-rose-100/70">
                                After changing cached values, run: /opt/alt/php84/usr/bin/php artisan optimize:clear
                            </p>
                            <button type="submit" class="rounded-xl bg-rose-200 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-rose-100">
                                Save .env
                            </button>
                        </div>
                    </form>
                </details>
            </section>
                </div>
            </div>

            <div id="plan-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/80 px-4 py-8 backdrop-blur-sm">
                <div class="w-full max-w-3xl overflow-hidden rounded-[1.75rem] border border-white/10 bg-slate-950 shadow-2xl shadow-black/50">
                    <div class="flex items-start justify-between gap-4 border-b border-white/10 px-6 py-5">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.3em] text-sky-200">Subscription Plan</p>
                            <h2 id="plan-modal-title" class="mt-1 text-sm font-semibold italic text-white">Add plan</h2>
                        </div>
                        <button type="button" data-plan-modal-close class="rounded-xl border border-white/10 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:border-sky-300/50 hover:bg-white/5">
                            Close
                        </button>
                    </div>

                    <form id="plan-modal-form" method="POST" action="{{ route('super-admin.subscriptions.store') }}" class="space-y-4 px-6 py-5">
                        @csrf
                        <input id="plan-modal-method" type="hidden" name="_method" value="PUT" disabled>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block">
                                <span class="text-[11px] uppercase tracking-[0.24em] text-slate-400">Plan name</span>
                                <input id="plan-modal-name" name="name" type="text" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300">
                            </label>

                            <label class="block">
                                <span class="text-[11px] uppercase tracking-[0.24em] text-slate-400">Billing period</span>
                                <select id="plan-modal-billing-period" name="billing_period" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300">
                                    @foreach ($billingPeriods as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-[11px] uppercase tracking-[0.24em] text-slate-400">Price</span>
                                <input id="plan-modal-price" name="price" type="number" min="0" step="0.01" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300">
                            </label>

                            <label class="block">
                                <span class="text-[11px] uppercase tracking-[0.24em] text-slate-400">Currency</span>
                                <input id="plan-modal-currency" name="currency" type="text" maxlength="3" required class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm uppercase text-white outline-none transition focus:border-sky-300">
                            </label>

                            <label class="block">
                                <span class="text-[11px] uppercase tracking-[0.24em] text-slate-400">Validity count</span>
                                <input id="plan-modal-validity-count" name="validity_count" type="number" min="1" placeholder="Optional" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-sky-300">
                            </label>

                            <label class="block">
                                <span class="text-[11px] uppercase tracking-[0.24em] text-slate-400">Validity unit</span>
                                <select id="plan-modal-validity-unit" name="validity_unit" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300">
                                    <option value="">No expiry</option>
                                    @foreach ($validityUnits as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <label class="block">
                            <span class="text-[11px] uppercase tracking-[0.24em] text-slate-400">Description</span>
                            <textarea id="plan-modal-description" name="description" rows="3" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-sky-300" placeholder="Description shown to tenants"></textarea>
                        </label>

                        <label class="flex items-center gap-2 text-sm text-slate-200">
                            <input name="is_active" type="hidden" value="0">
                            <input id="plan-modal-is-active" name="is_active" type="checkbox" value="1" class="h-4 w-4 rounded border-white/20 bg-slate-950 text-sky-300 focus:ring-sky-300">
                            Active and selectable by tenants
                        </label>

                        <div class="flex justify-end gap-3 border-t border-white/10 pt-4">
                            <button type="button" data-plan-modal-close class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-100 transition hover:border-sky-300/50 hover:bg-white/5">
                                Cancel
                            </button>
                            <button id="plan-modal-submit" type="submit" class="rounded-xl bg-sky-300 px-5 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-200">
                                Save plan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="tenant-subscription-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/80 px-4 py-8 backdrop-blur-sm">
                <div class="w-full max-w-2xl overflow-hidden rounded-[1.75rem] border border-white/10 bg-slate-950 shadow-2xl shadow-black/50">
                    <div class="flex items-start justify-between gap-4 border-b border-white/10 px-6 py-5">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.3em] text-sky-200">Selected Subscription</p>
                            <h2 id="tenant-subscription-title" class="mt-1 text-sm font-semibold italic text-white">Change tenant plan</h2>
                        </div>
                        <button type="button" data-tenant-subscription-modal-close class="rounded-xl border border-white/10 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:border-sky-300/50 hover:bg-white/5">
                            Close
                        </button>
                    </div>

                    <form id="tenant-subscription-form" method="POST" action="#" class="space-y-4 px-6 py-5">
                        @csrf
                        @method('PUT')

                        <label class="block">
                            <span class="text-[11px] uppercase tracking-[0.24em] text-slate-400">Subscription</span>
                            <select id="tenant-subscription-select" name="subscription_id" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300">
                                <option value="">No subscription</option>
                                @foreach ($subscriptionOptions as $option)
                                    <option value="{{ $option->id }}">
                                        {{ $option->name }} · {{ strtoupper($option->currency) }} {{ number_format((float) $option->price, 2) }} · {{ $billingPeriods[$option->billing_period] ?? $option->billing_period }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <p class="rounded-2xl border border-sky-300/20 bg-sky-300/10 px-4 py-3 text-xs leading-5 text-sky-50/80">
                            This changes the selected plan only. Tenant access is still controlled manually from the table.
                        </p>

                        <div class="flex justify-end gap-3 border-t border-white/10 pt-4">
                            <button type="button" data-tenant-subscription-modal-close class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-100 transition hover:border-sky-300/50 hover:bg-white/5">
                                Cancel
                            </button>
                            <button type="submit" class="rounded-xl bg-sky-300 px-5 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-200">
                                Assign plan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                (() => {
                    const closeMenus = (except = null) => {
                        document.querySelectorAll('[data-tenant-action-panel]').forEach((panel) => {
                            if (panel !== except) {
                                panel.classList.add('hidden');
                                panel.style.position = '';
                                panel.style.left = '';
                                panel.style.top = '';
                                panel.style.right = '';
                                panel.style.width = '';
                            }
                        });
                    };

                    const positionMenu = (button, panel) => {
                        const rect = button.getBoundingClientRect();
                        const width = Math.max(176, rect.width);
                        const left = Math.min(window.innerWidth - width - 12, Math.max(12, rect.right - width));
                        const top = Math.min(window.innerHeight - 96, rect.bottom + 8);

                        panel.style.position = 'fixed';
                        panel.style.left = `${left}px`;
                        panel.style.top = `${top}px`;
                        panel.style.right = 'auto';
                        panel.style.width = `${width}px`;
                    };

                    document.querySelectorAll('[data-tenant-action-toggle]').forEach((button) => {
                        button.addEventListener('click', (event) => {
                            event.stopPropagation();
                            const panel = button.closest('[data-tenant-action-menu]')?.querySelector('[data-tenant-action-panel]');

                            if (!panel) {
                                return;
                            }

                            const willOpen = panel.classList.contains('hidden');
                            closeMenus(panel);
                            panel.classList.toggle('hidden', !willOpen);

                            if (willOpen) {
                                positionMenu(button, panel);
                            }
                        });
                    });

                    document.querySelectorAll('[data-tenant-action-panel]').forEach((panel) => {
                        panel.addEventListener('click', (event) => event.stopPropagation());
                    });

                    document.addEventListener('click', () => closeMenus());
                    window.addEventListener('resize', () => closeMenus());
                    window.addEventListener('scroll', () => closeMenus(), true);
                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape') {
                            closeMenus();
                        }
                    });
                })();

                (() => {
                    const modal = document.getElementById('plan-modal');
                    const form = document.getElementById('plan-modal-form');
                    const method = document.getElementById('plan-modal-method');
                    const title = document.getElementById('plan-modal-title');
                    const submit = document.getElementById('plan-modal-submit');
                    const fields = {
                        name: document.getElementById('plan-modal-name'),
                        billingPeriod: document.getElementById('plan-modal-billing-period'),
                        price: document.getElementById('plan-modal-price'),
                        currency: document.getElementById('plan-modal-currency'),
                        validityCount: document.getElementById('plan-modal-validity-count'),
                        validityUnit: document.getElementById('plan-modal-validity-unit'),
                        description: document.getElementById('plan-modal-description'),
                        isActive: document.getElementById('plan-modal-is-active'),
                    };

                    const openModal = (button) => {
                        const mode = button.dataset.mode || 'create';
                        form.action = button.dataset.action;
                        title.textContent = mode === 'edit' ? 'Edit plan' : 'Add plan';
                        submit.textContent = mode === 'edit' ? 'Save changes' : 'Add plan';
                        method.disabled = mode !== 'edit';

                        fields.name.value = mode === 'edit' ? button.dataset.name || '' : '';
                        fields.billingPeriod.value = mode === 'edit' ? button.dataset.billingPeriod || 'monthly' : 'monthly';
                        fields.price.value = mode === 'edit' ? button.dataset.price || '0.00' : '0.00';
                        fields.currency.value = mode === 'edit' ? button.dataset.currency || @json($platformCurrency) : @json($platformCurrency);
                        fields.validityCount.value = mode === 'edit' ? button.dataset.validityCount || '' : '';
                        fields.validityUnit.value = mode === 'edit' ? button.dataset.validityUnit || '' : '';
                        fields.description.value = mode === 'edit' ? button.dataset.description || '' : '';
                        fields.isActive.checked = mode === 'edit' ? button.dataset.isActive === '1' : true;

                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                        fields.name.focus();
                    };

                    const closeModal = () => {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    };

                    document.querySelectorAll('[data-plan-modal-open]').forEach((button) => {
                        button.addEventListener('click', () => openModal(button));
                    });

                    document.querySelectorAll('[data-plan-modal-close]').forEach((button) => {
                        button.addEventListener('click', closeModal);
                    });

                    modal.addEventListener('click', (event) => {
                        if (event.target === modal) {
                            closeModal();
                        }
                    });

                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                            closeModal();
                        }
                    });
                })();

                (() => {
                    const modal = document.getElementById('tenant-subscription-modal');
                    const form = document.getElementById('tenant-subscription-form');
                    const title = document.getElementById('tenant-subscription-title');
                    const select = document.getElementById('tenant-subscription-select');

                    const openModal = (button) => {
                        document.querySelectorAll('[data-tenant-action-panel]').forEach((panel) => panel.classList.add('hidden'));
                        form.action = button.dataset.action;
                        title.textContent = `${button.dataset.tenantName || 'Tenant'} subscription`;
                        select.value = button.dataset.subscriptionId || '';
                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                        select.focus();
                    };

                    const closeModal = () => {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    };

                    document.querySelectorAll('[data-tenant-subscription-modal-open]').forEach((button) => {
                        button.addEventListener('click', () => openModal(button));
                    });

                    document.querySelectorAll('[data-tenant-subscription-modal-close]').forEach((button) => {
                        button.addEventListener('click', closeModal);
                    });

                    modal.addEventListener('click', (event) => {
                        if (event.target === modal) {
                            closeModal();
                        }
                    });

                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                            closeModal();
                        }
                    });
                })();
            </script>
        </main>
    </body>
</html>
