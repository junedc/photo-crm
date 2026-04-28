<script setup>
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const bookingList = ref([...(props.data.bookings ?? [])]);
const { deleting, deleteRecord } = useWorkspaceCrud();
const bookings = computed(() => bookingList.value);
const bookingSearch = ref('');
const bookingStatusFilter = ref('all');
const bookingKindFilter = ref('all');
const bookingDateFrom = ref('');
const bookingDateTo = ref('');
const pagination = ref(props.data.pagination ?? { total: bookings.value.length, has_more: false, next_page: null });
const loadingMore = ref(false);
const bookingToDelete = ref(null);
const blockedDeleteMessage = ref('');
const showDeleteConfirm = ref(false);
const showBlockedDeleteDialog = ref(false);
let requestId = 0;

const bookingKinds = computed(() => props.data.bookingKinds ?? []);
const bookingStatuses = computed(() => props.data.bookingStatuses ?? []);

const bookingKindLabelMap = {
    customer: 'Customer Booking',
    market_stall: 'Market Stall',
    sponsored: 'Sponsored',
};

const bookingKindLabel = (kind) => bookingKindLabelMap[kind] ?? 'Customer Booking';
const statusLabel = (status) => (status || '').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const fetchBookings = async (page = 1, append = false) => {
    const currentRequestId = ++requestId;
    loadingMore.value = true;

    const params = new URLSearchParams({
        page: String(page),
        search: bookingSearch.value.trim(),
        status: bookingStatusFilter.value,
        booking_kind: bookingKindFilter.value,
        event_date_from: bookingDateFrom.value,
        event_date_to: bookingDateTo.value,
    });

    try {
        const response = await fetch(`${props.data.routes.bookings}?${params.toString()}`, {
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
        bookingList.value = append ? [...bookingList.value, ...(payload.records ?? [])] : [...(payload.records ?? [])];
        pagination.value = payload.pagination ?? pagination.value;
    } finally {
        if (currentRequestId === requestId) {
            loadingMore.value = false;
        }
    }
};

watch([bookingSearch, bookingStatusFilter, bookingKindFilter, bookingDateFrom, bookingDateTo], () => {
    fetchBookings(1, false);
});

const loadMoreBookings = () => pagination.value.next_page && fetchBookings(pagination.value.next_page, true);

const openBooking = (entry) => {
    if (!entry?.show_url) {
        return;
    }

    window.location.href = entry.show_url;
};

const askDeleteBooking = (entry) => {
    if (entry.delete_blocked) {
        blockedDeleteMessage.value = entry.delete_blocked_message || 'This booking cannot be deleted.';
        showBlockedDeleteDialog.value = true;
        return;
    }

    bookingToDelete.value = entry;
    showDeleteConfirm.value = true;
};

const cancelDeleteBooking = () => {
    bookingToDelete.value = null;
    showDeleteConfirm.value = false;
};

const closeBlockedDeleteDialog = () => {
    blockedDeleteMessage.value = '';
    showBlockedDeleteDialog.value = false;
};

const confirmDeleteBooking = async () => {
    if (!bookingToDelete.value?.delete_url) {
        return;
    }

    const id = bookingToDelete.value.id;
    await deleteRecord({ url: bookingToDelete.value.delete_url });
    bookingList.value = bookingList.value.filter((entry) => entry.id !== id);
    pagination.value = {
        ...pagination.value,
        total: Math.max(Number(pagination.value.total ?? bookingList.value.length + 1) - 1, 0),
    };
    cancelDeleteBooking();
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-rose-200">Bookings Workspace</p>
        <h2 class="text-sm font-bold italic text-white">Review customer booking requests</h2>
        <p class="text-sm text-stone-300">
            Open a booking for details, or create a new booking from its own page.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
        <div class="sticky top-0 z-10 -mx-3 -mt-3 mb-3 rounded-t-2xl border-b border-white/10 bg-[#132035] px-3 pb-3 pt-3">
            <div class="px-2">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Incoming Bookings</p>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <h3 class="text-sm font-semibold italic">Booking list</h3>
                        <span class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 text-xs text-stone-300">{{ pagination.total ?? bookings.length }}</span>
                    </div>
                    <a :href="data.routes.create" class="rounded-xl bg-rose-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-rose-200">
                        Create booking
                    </a>
                </div>
            </div>
            <div class="mt-3 grid gap-2 lg:grid-cols-[minmax(0,1.5fr)_180px_180px_150px_150px]">
                <label class="min-w-0">
                    <span class="mb-1 block text-[10px] font-medium uppercase tracking-[0.18em] text-stone-500">Search</span>
                    <input v-model="bookingSearch" type="text" placeholder="Name, quote, email, package" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-rose-300/50">
                </label>
                <label>
                    <span class="mb-1 block text-[10px] font-medium uppercase tracking-[0.18em] text-stone-500">Status</span>
                    <select v-model="bookingStatusFilter" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-rose-300/50">
                            <option value="all">All statuses</option>
                            <option v-for="status in bookingStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                    </select>
                </label>
                <label>
                    <span class="mb-1 block text-[10px] font-medium uppercase tracking-[0.18em] text-stone-500">Booking Type</span>
                    <select v-model="bookingKindFilter" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-cyan-300/50">
                            <option value="all">All types</option>
                            <option v-for="kind in bookingKinds" :key="kind" :value="kind">{{ bookingKindLabel(kind) }}</option>
                    </select>
                </label>
                <label>
                    <span class="mb-1 block text-[10px] font-medium uppercase tracking-[0.18em] text-stone-500">Event Date From</span>
                    <input v-model="bookingDateFrom" type="date" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-rose-300/50">
                </label>
                <label>
                    <span class="mb-1 block text-[10px] font-medium uppercase tracking-[0.18em] text-stone-500">To</span>
                    <input v-model="bookingDateTo" type="date" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-rose-300/50">
                </label>
            </div>
            <div class="mt-3 hidden grid-cols-[minmax(0,1.2fr)_9rem_8rem_9rem_9rem_7rem_8rem] gap-3 px-2 text-[11px] uppercase tracking-[0.2em] text-stone-500 lg:grid">
                <span>Booking</span>
                <span>Type</span>
                <span>Date</span>
                <span>Package</span>
                <span>Total</span>
                <span>Status</span>
                <span>Actions</span>
            </div>
        </div>

        <div class="max-h-[70vh] overflow-y-auto">
            <div
                v-for="entry in bookings"
                :key="entry.id"
                class="grid w-full cursor-pointer gap-3 border-b border-white/10 px-3 py-3 text-left transition hover:bg-white/[0.03] lg:grid-cols-[minmax(0,1.2fr)_9rem_8rem_9rem_9rem_7rem_8rem] lg:items-center"
                role="link"
                tabindex="0"
                @click="openBooking(entry)"
                @keydown.enter.prevent="openBooking(entry)"
                @keydown.space.prevent="openBooking(entry)"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-white">{{ entry.display_name || entry.customer_name }}</p>
                    <p class="mt-1 truncate text-xs text-stone-400">{{ entry.quote_number || 'No quote number' }}</p>
                </div>
                <div>
                    <p class="mb-1 text-[10px] uppercase tracking-[0.2em] text-stone-500 lg:hidden">Type</p>
                    <span class="inline-flex rounded-full border border-cyan-300/20 bg-cyan-300/10 px-2.5 py-1 text-[11px] font-medium text-cyan-100">{{ entry.booking_kind_label }}</span>
                </div>
                <div>
                    <p class="mb-1 text-[10px] uppercase tracking-[0.2em] text-stone-500 lg:hidden">Date</p>
                    <p class="text-sm text-stone-200">{{ entry.event_date_label || 'Not set' }}</p>
                    <p class="mt-1 text-xs text-stone-500">{{ entry.start_time_label || '' }}</p>
                </div>
                <div class="min-w-0">
                    <p class="mb-1 text-[10px] uppercase tracking-[0.2em] text-stone-500 lg:hidden">Package</p>
                    <p class="truncate text-sm text-stone-200">{{ entry.package_name || 'No package' }}</p>
                </div>
                <div>
                    <p class="mb-1 text-[10px] uppercase tracking-[0.2em] text-stone-500 lg:hidden">Total</p>
                    <p class="text-sm font-semibold text-cyan-100">${{ entry.booking_total }}</p>
                </div>
                <div>
                    <p class="mb-1 text-[10px] uppercase tracking-[0.2em] text-stone-500 lg:hidden">Status</p>
                    <span class="inline-flex h-8 items-center justify-center rounded-full px-3 text-[11px] font-medium leading-none" :class="entry.status === 'confirmed' ? 'bg-emerald-400/15 text-emerald-200' : entry.status === 'pending' ? 'bg-amber-300/15 text-amber-200' : entry.status === 'completed' ? 'bg-cyan-300/15 text-cyan-200' : 'bg-rose-400/15 text-rose-200'">
                        {{ statusLabel(entry.status) }}
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <p class="mb-1 w-full text-[10px] uppercase tracking-[0.2em] text-stone-500 lg:hidden">Actions</p>
                    <button type="button" class="rounded-lg border border-rose-300/20 px-2.5 py-1 text-xs font-medium text-rose-200 transition hover:bg-rose-300/10" @click.stop="askDeleteBooking(entry)">
                        Delete
                    </button>
                </div>
            </div>

            <div v-if="!bookings.length" class="rounded-2xl border border-dashed border-white/15 bg-stone-950/40 px-4 py-5 text-sm text-stone-400">
                No bookings match the current filters.
            </div>

            <div v-else-if="pagination.has_more" class="flex justify-center px-3 py-4">
                <button type="button" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-rose-300/40 hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loadingMore" @click="loadMoreBookings">
                    {{ loadingMore ? 'Loading...' : 'Load more bookings' }}
                </button>
            </div>
        </div>
    </section>

    <ConfirmDialog
        :open="showDeleteConfirm"
        title="Delete booking?"
        :message="`Are you sure you want to delete the record ${bookingToDelete?.quote_number || bookingToDelete?.display_name || 'this booking'}?`"
        confirm-label="Delete booking"
        :loading="deleting"
        @cancel="cancelDeleteBooking"
        @confirm="confirmDeleteBooking"
    />

    <ConfirmDialog
        :open="showBlockedDeleteDialog"
        title="Booking cannot be deleted"
        :message="blockedDeleteMessage"
        confirm-label="Close"
        tone="info"
        :hide-cancel="true"
        @cancel="closeBlockedDeleteDialog"
        @confirm="closeBlockedDeleteDialog"
    />
</template>
