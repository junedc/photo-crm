<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const invoiceList = ref([...(props.data.invoices ?? [])]);
const invoices = computed(() => invoiceList.value);
const search = ref('');
const statusFilter = ref('all');
const pagination = ref(props.data.pagination ?? { total: invoices.value.length, has_more: false, next_page: null });
const loadingMore = ref(false);
let requestId = 0;

const fetchInvoices = async (page = 1, append = false) => {
    const currentRequestId = ++requestId;
    loadingMore.value = true;

    const params = new URLSearchParams({
        page: String(page),
        search: search.value.trim(),
        status: statusFilter.value,
    });

    try {
        const response = await fetch(`${props.data.routes.invoices}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok || currentRequestId !== requestId) {
            return;
        }

        const payload = await response.json();
        invoiceList.value = append ? [...invoiceList.value, ...(payload.records ?? [])] : [...(payload.records ?? [])];
        pagination.value = payload.pagination ?? pagination.value;
    } finally {
        if (currentRequestId === requestId) {
            loadingMore.value = false;
        }
    }
};

const statusLabel = (status) => (status || '').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const loadMoreInvoices = () => pagination.value.next_page && fetchInvoices(pagination.value.next_page, true);

watch([search, statusFilter], () => fetchInvoices(1, false));
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-emerald-200">Invoices Workspace</p>
        <h2 class="text-sm font-bold italic text-white">Track issued invoices and payment status</h2>
        <p class="text-sm text-stone-300">
            Review invoice totals, balance due, next due dates, and jump to the linked booking or customer invoice page when needed.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
        <div class="sticky top-0 z-10 -mx-3 -mt-3 mb-3 rounded-t-2xl border-b border-white/10 bg-[#132035] px-3 pb-3 pt-3">
            <div class="px-2">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Invoice Records</p>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <h3 class="text-lg font-semibold">Invoice list</h3>
                        <span class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 text-xs text-stone-300">{{ pagination.total ?? invoices.length }}</span>
                    </div>
                    <a :href="data.routes.bookings" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">
                        Open bookings
                    </a>
                </div>
            </div>
            <div class="mt-3 grid gap-2 md:grid-cols-[minmax(0,1fr)_220px]">
                <input v-model="search" type="text" placeholder="Search invoice, customer, or package" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-emerald-300/50">
                <select v-model="statusFilter" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-emerald-300/50">
                    <option v-for="status in data.invoiceStatuses" :key="status" :value="status">
                        {{ status === 'all' ? 'All statuses' : statusLabel(status) }}
                    </option>
                </select>
            </div>
            <div class="mt-3 grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_140px_140px_120px] gap-2 px-2 text-[11px] uppercase tracking-[0.2em] text-stone-500">
                <span>Invoice</span>
                <span>Customer</span>
                <span>Total</span>
                <span>Balance Due</span>
                <span>Status</span>
            </div>
        </div>

        <div class="max-h-[70vh] overflow-y-auto">
            <article
                v-for="entry in invoices"
                :key="entry.id"
                class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_140px_140px_120px] items-center gap-3 border-b border-white/10 px-3 py-3 transition hover:bg-white/[0.03]"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-white">{{ entry.invoice_number }}</p>
                    <div class="mt-1 flex flex-wrap gap-3 text-xs text-stone-400">
                        <span>{{ entry.package_name || 'No package' }}</span>
                        <span>{{ entry.event_date_label || 'No event date' }}</span>
                        <span>{{ entry.issued_at_label || 'Not issued yet' }}</span>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <a v-if="entry.booking_show_url" :href="entry.booking_show_url" class="text-xs font-medium text-cyan-200 transition hover:text-cyan-100">View booking</a>
                        <a :href="entry.public_url" target="_blank" rel="noreferrer" class="text-xs font-medium text-emerald-200 transition hover:text-emerald-100">Open invoice</a>
                    </div>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-stone-300">{{ entry.customer_name || 'No customer' }}</p>
                    <p class="mt-1 truncate text-xs text-stone-400">{{ entry.customer_email || 'No email' }}</p>
                    <p v-if="entry.next_due_label" class="mt-2 text-xs text-amber-200">Next due {{ entry.next_due_label }}</p>
                </div>
                <div class="text-sm font-medium text-white">${{ entry.total_amount }}</div>
                <div>
                    <p class="text-sm font-medium text-amber-200">${{ entry.balance_due }}</p>
                    <p class="mt-1 text-xs text-stone-400">Paid ${{ entry.amount_paid }}</p>
                </div>
                <span class="inline-flex h-8 items-center justify-center rounded-full px-3 text-center text-[11px] font-medium leading-none" :class="entry.status === 'paid' ? 'bg-emerald-400/15 text-emerald-200' : entry.status === 'partially_paid' ? 'bg-cyan-300/15 text-cyan-200' : entry.status === 'cancelled' ? 'bg-rose-400/15 text-rose-200' : 'bg-amber-300/15 text-amber-200'">
                    {{ entry.status_label }}
                </span>
            </article>

            <div v-if="!invoices.length" class="rounded-2xl border border-dashed border-white/15 bg-stone-950/40 px-4 py-5 text-sm text-stone-400">
                No invoices match the current filters.
            </div>

            <div v-else-if="pagination.has_more" class="flex justify-center px-3 py-4">
                <button type="button" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-emerald-300/40 hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loadingMore" @click="loadMoreInvoices">
                    {{ loadingMore ? 'Loading...' : 'Load more invoices' }}
                </button>
            </div>
        </div>
    </section>
</template>
