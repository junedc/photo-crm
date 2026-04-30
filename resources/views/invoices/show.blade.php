<!DOCTYPE html>
@php use App\Support\DateFormatter; @endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $tenant->name }} Invoice</title>
        @if ($tenant->logo_path)
            <link rel="icon" type="image/png" href="{{ url(Storage::disk('public')->url($tenant->logo_path)) }}">
        @endif
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-stone-950 text-stone-50" data-theme="{{ $tenant->theme ?: 'dark' }}">
        <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            @if (request('payment') === 'success')
                <div id="invoice-payment-toast" class="mb-6 rounded-2xl border border-cyan-400/30 bg-cyan-500/10 px-5 py-4 text-sm text-cyan-100">
                    Payment received. We are updating your installment status now.
                </div>
            @elseif (request('payment') === 'cancel')
                <div class="mb-6 rounded-2xl border border-amber-400/30 bg-amber-500/10 px-5 py-4 text-sm text-amber-100">
                    Payment was cancelled. Your installment remains pending until payment is completed.
                </div>
            @endif

            <section class="rounded-[2rem] border border-white/10 bg-gradient-to-br from-cyan-300/15 via-stone-900 to-emerald-300/10 p-6 shadow-2xl shadow-black/20 sm:p-8">
                <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-center gap-4">
                        @if ($tenant->logo_path)
                            <img src="{{ Storage::disk('public')->url($tenant->logo_path) }}" alt="{{ $tenant->name }} logo" class="h-14 w-14 rounded-2xl object-cover shadow-lg shadow-black/20">
                        @else
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-lg font-semibold text-stone-300">
                                {{ \Illuminate\Support\Str::of($tenant->name)->substr(0, 1) }}
                            </div>
                        @endif
                        <div>
                            <p class="text-sm font-semibold text-white">{{ $tenant->name }}</p>
                            <p class="text-xs uppercase tracking-[0.3em] text-stone-400">Customer Invoice</p>
                        </div>
                    </div>
                    @if (filled($tenant->home_url) && (float) $invoice->amount_paid > 0)
                        <a href="{{ $tenant->home_url }}" class="inline-flex items-center justify-center rounded-2xl border border-cyan-300/30 bg-cyan-300/10 px-4 py-2.5 text-sm font-semibold text-cyan-100 transition hover:bg-cyan-300/20">
                            Home
                        </a>
                    @endif
                </div>
                <p class="text-sm uppercase tracking-[0.35em] text-cyan-200">Invoice</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $invoice->invoice_number }}</h1>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-stone-300">
                    Review your installment schedule and pay each installment as it becomes due.
                </p>
            </section>

            @if (session('status'))
                <div class="mt-6 rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                    {{ session('status') }}
                </div>
            @endif

            <section class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div class="space-y-6">
                    <div class="rounded-[2rem] border border-white/10 bg-white/5 p-6">
                        <p class="text-sm uppercase tracking-[0.3em] text-stone-400">Booking</p>
                        <h2 class="mt-2 text-2xl font-semibold">{{ $invoice->booking->customer_name }}</h2>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-white/10 bg-stone-950/50 p-4">
                                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Package</p>
                                <p class="mt-2 text-base font-semibold">{{ $invoice->booking->package?->name ?? 'No package selected' }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-stone-950/50 p-4">
                                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Event Date</p>
                                <p class="mt-2 text-base font-semibold">{{ DateFormatter::date($invoice->booking->event_date) }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-stone-950/50 p-4">
                                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Start Hour</p>
                                <p class="mt-2 text-base font-semibold">{{ DateFormatter::time($invoice->booking->start_time, 'N/A') }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-stone-950/50 p-4">
                                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">End Hour</p>
                                <p class="mt-2 text-base font-semibold">{{ DateFormatter::time($invoice->booking->end_time, 'N/A') }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-stone-950/50 p-4">
                                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Total Hours</p>
                                <p class="mt-2 text-base font-semibold">{{ number_format((float) ($invoice->booking->total_hours ?? 0), 2) }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-stone-950/50 p-4">
                                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Discount</p>
                                <p class="mt-2 text-base font-semibold">
                                    {{ $invoice->booking->discount?->code ? $invoice->booking->discount->code.' - '.$invoice->booking->discount->name : 'No discount selected' }}
                                </p>
                                <p class="mt-1 text-sm text-emerald-200">-${{ number_format((float) ($invoice->booking->discount_amount ?? 0), 2) }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-stone-950/50 p-4 sm:col-span-2">
                                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Travel</p>
                                <p class="mt-2 text-base font-semibold">
                                    ${{ number_format((float) ($invoice->booking->travel_fee ?? 0), 2) }}
                                    for {{ number_format((float) ($invoice->booking->travel_distance_km ?? 0), 2) }} km
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[2rem] border border-white/10 bg-white/5 p-6">
                        <p class="text-sm uppercase tracking-[0.3em] text-emerald-200">Installment Schedule</p>
                        <div class="mt-4 space-y-4">
                            @foreach ($invoice->installments as $installment)
                                <article class="rounded-2xl border border-white/10 bg-stone-950/60 p-5" data-installment-card="{{ $installment->id }}">
                                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-lg font-semibold">{{ $installment->label }}</p>
                                            <p class="mt-1 text-sm text-stone-400">Due {{ DateFormatter::date($installment->due_date) }}</p>
                                            <p class="mt-2 text-sm text-emerald-200 {{ $installment->paid_at ? '' : 'hidden' }}" data-installment-paid-at="{{ $installment->id }}">
                                                @if ($installment->paid_at)
                                                    Paid {{ DateFormatter::dateTime($installment->paid_at) }}
                                                @endif
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-2xl font-semibold text-cyan-200">${{ number_format((float) $installment->amount, 2) }}</p>
                                            <span class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $installment->status === 'paid' ? 'bg-emerald-400/15 text-emerald-200' : 'bg-amber-300/15 text-amber-200' }}" data-installment-status="{{ $installment->id }}">
                                                {{ str($installment->status)->replace('_', ' ')->title() }}
                                            </span>
                                        </div>
                                    </div>

                                    @if ($installment->status !== 'paid')
                                        <form method="POST" action="{{ route('invoices.installments.pay', [$invoice, $installment]) }}" class="mt-4" data-installment-pay-form="{{ $installment->id }}">
                                            @csrf
                                            <button type="submit" class="rounded-2xl bg-cyan-300 px-4 py-3 text-sm font-semibold text-stone-950 transition hover:bg-cyan-200">
                                                Pay This Installment
                                            </button>
                                        </form>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </div>
                </div>

                <aside class="rounded-[2rem] border border-white/10 bg-white/5 p-6">
                    <p class="text-sm uppercase tracking-[0.3em] text-stone-400">Summary</p>
                    <div class="mt-5 space-y-4">
                        <div class="rounded-2xl border border-white/10 bg-stone-950/60 p-4">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Invoice Status</p>
                            <span id="invoice-status-badge" class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $invoice->status === 'paid' ? 'bg-emerald-400/15 text-emerald-200' : ($invoice->status === 'partially_paid' ? 'bg-cyan-300/15 text-cyan-200' : 'bg-amber-300/15 text-amber-200') }}">
                                {{ str($invoice->status)->replace('_', ' ')->title() }}
                            </span>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-stone-950/60 p-4">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Total</p>
                            <p class="mt-2 text-2xl font-semibold">${{ number_format((float) $invoice->total_amount, 2) }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-stone-950/60 p-4">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Paid</p>
                            <p id="invoice-amount-paid" class="mt-2 text-2xl font-semibold text-emerald-200">${{ number_format((float) $invoice->amount_paid, 2) }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-stone-950/60 p-4">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Balance Due</p>
                            <p id="invoice-balance-due" class="mt-2 text-2xl font-semibold text-amber-200">${{ number_format((float) $invoice->total_amount - (float) $invoice->amount_paid, 2) }}</p>
                        </div>
                    </div>
                </aside>
            </section>
        </main>
        @if (request('payment') === 'success')
            <script>
                (() => {
                    const toast = document.getElementById('invoice-payment-toast');
                    const statusUrl = new URL(@json(route('invoices.status', $invoice)), window.location.origin);
                    const sessionId = new URL(window.location.href).searchParams.get('session_id');
                    const installmentId = Number(@json(request('installment')));
                    const invoiceStatusBadge = document.getElementById('invoice-status-badge');
                    const amountPaid = document.getElementById('invoice-amount-paid');
                    const balanceDue = document.getElementById('invoice-balance-due');

                    if (!statusUrl || !installmentId || !invoiceStatusBadge || !amountPaid || !balanceDue) {
                        return;
                    }

                    if (sessionId) {
                        statusUrl.searchParams.set('session_id', sessionId);
                        statusUrl.searchParams.set('installment', String(installmentId));
                    }

                    const badgeClass = (status) => {
                        if (status === 'paid') {
                            return 'mt-2 inline-flex rounded-full px-3 py-1 text-xs font-medium bg-emerald-400/15 text-emerald-200';
                        }

                        if (status === 'partially_paid') {
                            return 'mt-2 inline-flex rounded-full px-3 py-1 text-xs font-medium bg-cyan-300/15 text-cyan-200';
                        }

                        return 'mt-2 inline-flex rounded-full px-3 py-1 text-xs font-medium bg-amber-300/15 text-amber-200';
                    };

                    const installmentBadgeClass = (status) => (
                        status === 'paid'
                            ? 'mt-2 inline-flex rounded-full px-3 py-1 text-xs font-medium bg-emerald-400/15 text-emerald-200'
                            : 'mt-2 inline-flex rounded-full px-3 py-1 text-xs font-medium bg-amber-300/15 text-amber-200'
                    );

                    let attempts = 0;
                    const maxAttempts = 8;
                    let completedRefresh = false;

                    const refreshStatus = async () => {
                        attempts += 1;

                        try {
                            const response = await fetch(statusUrl.toString(), {
                                headers: {
                                    Accept: 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                credentials: 'same-origin',
                            });

                            if (!response.ok) {
                                throw new Error('Unable to refresh invoice status.');
                            }

                            const payload = await response.json();
                            const installment = (payload.installments || []).find((entry) => Number(entry.id) === installmentId);
                            const allInstallmentsPaid = Array.isArray(payload.installments) && payload.installments.length > 0
                                ? payload.installments.every((entry) => entry.status === 'paid')
                                : false;

                            invoiceStatusBadge.textContent = String(payload.invoice.status || '').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
                            invoiceStatusBadge.className = badgeClass(payload.invoice.status);
                            amountPaid.textContent = `$${payload.invoice.amount_paid}`;
                            balanceDue.textContent = `$${payload.invoice.balance_due}`;

                            if (payload.invoice.status === 'paid' || allInstallmentsPaid) {
                                completedRefresh = true;
                                toast.textContent = 'Payment received. Your invoice status is now Paid.';

                                const nextUrl = new URL(window.location.href);
                                nextUrl.searchParams.delete('payment');
                                nextUrl.searchParams.delete('installment');
                                nextUrl.searchParams.delete('session_id');
                                const cleanUrl = nextUrl.toString();
                                window.history.replaceState({}, '', cleanUrl);

                                window.setTimeout(() => {
                                    window.location.replace(cleanUrl);
                                }, 1200);

                                return;
                            }

                            if (installment) {
                                const installmentStatus = document.querySelector(`[data-installment-status="${installmentId}"]`);
                                const installmentPaidAt = document.querySelector(`[data-installment-paid-at="${installmentId}"]`);
                                const installmentPayForm = document.querySelector(`[data-installment-pay-form="${installmentId}"]`);

                                if (installmentStatus) {
                                    installmentStatus.textContent = String(installment.status || '').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
                                    installmentStatus.className = installmentBadgeClass(installment.status);
                                }

                                if (installment.status === 'paid') {
                                    if (installmentPaidAt) {
                                        installmentPaidAt.textContent = installment.paid_at_label ? `Paid ${installment.paid_at_label}` : 'Paid';
                                        installmentPaidAt.classList.remove('hidden');
                                    }

                                    installmentPayForm?.remove();
                                    if (payload.invoice.status !== 'paid') {
                                        toast.textContent = 'Payment received. Your installment schedule is now up to date.';
                                        return;
                                    }
                                }
                            }
                        } catch {
                            // Keep polling briefly in case the webhook completes on the next attempt.
                        }

                        if (attempts < maxAttempts) {
                            window.setTimeout(refreshStatus, 1500);
                        } else if (toast && !completedRefresh) {
                            toast.textContent = 'Payment was sent to Stripe. Refresh this page in a moment if the status is still pending.';
                        }
                    };

                    refreshStatus();
                })();
            </script>
        @endif
    </body>
</html>
