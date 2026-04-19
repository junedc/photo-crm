<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const leadList = ref([...(props.data.leads ?? [])]);
const leads = computed(() => leadList.value);
const search = ref('');
const selectedStatus = ref('all');
const pagination = ref(props.data.pagination ?? { total: leads.value.length, has_more: false, next_page: null });
const loadingMore = ref(false);
let requestId = 0;

const fetchLeads = async (page = 1, append = false) => {
    const currentRequestId = ++requestId;
    loadingMore.value = true;

    const params = new URLSearchParams({
        page: String(page),
        search: search.value.trim(),
        status: selectedStatus.value,
    });

    try {
        const response = await fetch(`${props.data.routes.leads}?${params.toString()}`, {
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
        leadList.value = append ? [...leadList.value, ...(payload.records ?? [])] : [...(payload.records ?? [])];
        pagination.value = payload.pagination ?? pagination.value;
    } finally {
        if (currentRequestId === requestId) {
            loadingMore.value = false;
        }
    }
};

const loadMoreLeads = () => pagination.value.next_page && fetchLeads(pagination.value.next_page, true);

watch([search, selectedStatus], () => fetchLeads(1, false));
</script>

<template>
    <section class="rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-4 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-violet-200">Leads Workspace</p>
        <h2 class="mt-2 text-xl font-semibold tracking-tight">Track booking prospects and follow-ups</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-300">
            Review autosaved booking prospects, add manual leads, and maintain the sales pipeline for each tenant.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
        <div class="sticky top-0 z-10 -mx-3 -mt-3 mb-3 rounded-t-2xl border-b border-white/10 bg-[#132035] px-3 pb-3 pt-3">
            <div class="px-2">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Lead Records</p>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <h3 class="text-lg font-semibold">Lead list</h3>
                        <span class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 text-xs text-stone-300">{{ pagination.total ?? leads.length }}</span>
                    </div>
                    <a :href="data.routes.create" class="rounded-xl bg-violet-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-violet-200">
                        Create lead
                    </a>
                </div>
            </div>
            <div class="mt-3 grid gap-2 md:grid-cols-[minmax(0,1fr)_220px]">
                <input v-model="search" type="text" placeholder="Search leads" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-violet-300/50">
                <select v-model="selectedStatus" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-violet-300/50">
                    <option value="all">All statuses</option>
                    <option v-for="status in data.leadStatuses" :key="status" :value="status">{{ status }}</option>
                </select>
            </div>
            <div class="mt-3 grid grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_120px_140px] gap-2 px-2 text-[11px] uppercase tracking-[0.2em] text-stone-500">
                <span>Lead</span>
                <span>Contact</span>
                <span>Status</span>
                <span>Activity</span>
            </div>
        </div>

        <div class="max-h-[70vh] overflow-y-auto">
            <a
                v-for="entry in leads"
                :key="entry.id"
                :href="entry.show_url"
                class="grid w-full grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_120px_140px] gap-3 border-b px-3 py-3 text-left transition hover:bg-white/[0.03]"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-white">{{ entry.customer_name }}</p>
                    <p class="mt-1 truncate text-xs text-stone-400">{{ entry.event_location || 'No event location yet' }}</p>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-stone-300">{{ entry.customer_email || 'No email' }}</p>
                    <p class="mt-1 truncate text-xs text-stone-400">{{ entry.customer_phone || 'No phone' }}</p>
                </div>
                <span class="rounded-full bg-violet-400/15 px-2.5 py-1 text-center text-[11px] font-medium capitalize text-violet-200">
                    {{ entry.status }}
                </span>
                <span class="truncate text-xs text-stone-400">
                    {{ entry.last_activity_label || entry.created_at || 'Recently added' }}
                </span>
            </a>

            <div v-if="!leads.length" class="rounded-2xl border border-dashed border-white/15 bg-stone-950/40 px-4 py-5 text-sm text-stone-400">
                No leads match the current filters.
            </div>

            <div v-else-if="pagination.has_more" class="flex justify-center px-3 py-4">
                <button type="button" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-violet-300/40 hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loadingMore" @click="loadMoreLeads">
                    {{ loadingMore ? 'Loading...' : 'Load more leads' }}
                </button>
            </div>
        </div>
    </section>
</template>
