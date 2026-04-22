<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const quoteList = ref([...(props.data.quotes ?? [])]);
const quotes = computed(() => quoteList.value);
const search = ref('');
const responseFilter = ref('all');

const filteredQuotes = computed(() =>
    quotes.value.filter((entry) => {
        const matchesSearch = [entry.customer_name, entry.customer_email, entry.package_name]
            .filter(Boolean)
            .some((value) => value.toLowerCase().includes(search.value.toLowerCase()));

        const matchesResponse = responseFilter.value === 'all' || entry.customer_response_status === responseFilter.value;

        return matchesSearch && matchesResponse;
    }),
);

const statusLabel = (status) => (status || '').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-sky-200">Quotes Workspace</p>
        <h2 class="text-sm font-bold italic text-white">Review quote requests and customer responses</h2>
        <p class="text-sm text-stone-300">
            Track sent quotes, monitor whether customers accepted or rejected them, and open the linked booking when you need the full details.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
        <div class="sticky top-0 z-10 -mx-3 -mt-3 mb-3 rounded-t-2xl border-b border-white/10 bg-[#132035] px-3 pb-3 pt-3">
            <div class="px-2">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Quote Requests</p>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <h3 class="text-sm font-semibold italic">Quote list</h3>
                        <span class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 text-xs text-stone-300">{{ filteredQuotes.length }}</span>
                    </div>
                    <a :href="data.routes.bookings" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">
                        Open bookings
                    </a>
                </div>
            </div>
            <div class="mt-3 grid gap-2 md:grid-cols-[minmax(0,1fr)_220px]">
                <input v-model="search" type="text" placeholder="Search customer or package" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-sky-300/50">
                <select v-model="responseFilter" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-sky-300/50">
                    <option v-for="status in data.quoteResponseStatuses" :key="status" :value="status">
                        {{ status === 'all' ? 'All responses' : statusLabel(status) }}
                    </option>
                </select>
            </div>
            <div class="mt-3 grid grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)_120px_140px] gap-2 px-2 text-[11px] uppercase tracking-[0.2em] text-stone-500">
                <span>Customer</span>
                <span>Quote</span>
                <span>Response</span>
                <span>Booking Status</span>
            </div>
        </div>

        <div class="max-h-[70vh] overflow-y-auto">
            <a
                v-for="entry in filteredQuotes"
                :key="entry.id"
                :href="entry.show_url"
                class="grid w-full grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)_120px_140px] items-center gap-3 border-b border-white/10 px-3 py-3 text-left transition hover:bg-white/[0.03]"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-white">{{ entry.customer_name }}</p>
                    <p class="mt-1 truncate text-xs text-stone-400">{{ entry.customer_email }}</p>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-stone-300">{{ entry.quote_number || 'Unnumbered quote' }}</p>
                    <p class="mt-1 truncate text-xs text-stone-400">{{ entry.package_name || 'No package' }}</p>
                    <p class="mt-1 truncate text-xs text-stone-400">{{ entry.event_date_label || 'No event date' }}</p>
                </div>
                <span class="inline-flex h-8 items-center justify-center rounded-full px-3 text-center text-[11px] font-medium leading-none" :class="entry.customer_response_status === 'accepted' ? 'bg-emerald-400/15 text-emerald-200' : entry.customer_response_status === 'rejected' ? 'bg-rose-400/15 text-rose-200' : 'bg-amber-300/15 text-amber-200'">
                    {{ entry.customer_response_label }}
                </span>
                <span class="inline-flex h-8 items-center justify-center rounded-full px-3 text-center text-[11px] font-medium leading-none" :class="entry.status === 'confirmed' ? 'bg-emerald-400/15 text-emerald-200' : entry.status === 'completed' ? 'bg-cyan-300/15 text-cyan-200' : entry.status === 'cancelled' ? 'bg-rose-400/15 text-rose-200' : 'bg-amber-300/15 text-amber-200'">
                    {{ statusLabel(entry.status) }}
                </span>
            </a>

            <div v-if="!filteredQuotes.length" class="rounded-2xl border border-dashed border-white/15 bg-stone-950/40 px-4 py-5 text-sm text-stone-400">
                No quotes match the current filters.
            </div>
        </div>
    </section>
</template>
