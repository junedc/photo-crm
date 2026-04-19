<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const customerList = ref([...(props.data.customers ?? [])]);
const customers = computed(() => customerList.value);
const search = ref('');
const pagination = ref(props.data.pagination ?? { total: customers.value.length, has_more: false, next_page: null });
const loadingMore = ref(false);
let requestId = 0;

const fetchCustomers = async (page = 1, append = false) => {
    const currentRequestId = ++requestId;
    loadingMore.value = true;

    const params = new URLSearchParams({
        page: String(page),
        search: search.value.trim(),
    });

    try {
        const response = await fetch(`${props.data.routes.customers}?${params.toString()}`, {
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
        customerList.value = append ? [...customerList.value, ...(payload.records ?? [])] : [...(payload.records ?? [])];
        pagination.value = payload.pagination ?? pagination.value;
    } finally {
        if (currentRequestId === requestId) {
            loadingMore.value = false;
        }
    }
};

const loadMoreCustomers = () => pagination.value.next_page && fetchCustomers(pagination.value.next_page, true);

watch(search, () => fetchCustomers(1, false));
</script>

<template>
    <section class="rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-4 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-cyan-200">Customers Workspace</p>
        <h2 class="mt-2 text-xl font-semibold tracking-tight">Manage customer records from bookings</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-300">
            Maintain customer details for people who have booked with your workspace and keep their contact records up to date.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
        <div class="sticky top-0 z-10 -mx-3 -mt-3 mb-3 rounded-t-2xl border-b border-white/10 bg-[#132035] px-3 pb-3 pt-3">
            <div class="px-2">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Customer Records</p>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <h3 class="text-lg font-semibold">Customer list</h3>
                        <span class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 text-xs text-stone-300">{{ pagination.total ?? customers.length }}</span>
                    </div>
                    <a :href="data.routes.create" class="rounded-xl bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200">
                        Create customer
                    </a>
                </div>
            </div>
            <div class="mt-3">
                <input v-model="search" type="text" placeholder="Search customers" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-cyan-300/50">
            </div>
        </div>

        <div class="max-h-[70vh] overflow-y-auto">
            <div class="grid grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)_150px_110px] gap-2 px-3 text-[11px] uppercase tracking-[0.2em] text-stone-500">
                <span>Customer</span>
                <span>Contact</span>
                <span>Date of Birth</span>
                <span>Bookings</span>
            </div>

            <a
                v-for="entry in customers"
                :key="entry.id"
                :href="entry.show_url"
                class="grid w-full grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)_150px_110px] gap-3 border-b border-white/10 px-3 py-3 text-left transition hover:bg-white/[0.03]"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-white">{{ entry.full_name }}</p>
                    <p class="mt-1 truncate text-xs text-stone-400">{{ entry.address || 'No address on file' }}</p>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-stone-300">{{ entry.email }}</p>
                    <p class="mt-1 truncate text-xs text-stone-400">{{ entry.phone }}</p>
                </div>
                <span class="text-sm text-stone-300">{{ entry.date_of_birth_label || 'Not provided' }}</span>
                <span class="text-sm font-medium text-cyan-200">{{ entry.bookings_count }}</span>
            </a>

            <div v-if="!customers.length" class="mt-3 rounded-2xl border border-dashed border-white/15 bg-stone-950/40 px-4 py-5 text-sm text-stone-400">
                No customers match the current search.
            </div>

            <div v-else-if="pagination.has_more" class="flex justify-center px-3 py-4">
                <button type="button" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-cyan-300/40 hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loadingMore" @click="loadMoreCustomers">
                    {{ loadingMore ? 'Loading...' : 'Load more customers' }}
                </button>
            </div>
        </div>
    </section>
</template>
