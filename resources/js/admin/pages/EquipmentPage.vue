<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const equipmentList = ref([...(props.data.equipment ?? [])]);
const equipment = computed(() => equipmentList.value);
const equipmentSearch = ref('');
const equipmentStatusFilter = ref('all');
const filteredEquipment = computed(() =>
    equipment.value.filter((entry) => {
        const matchesSearch = [entry.name, entry.category, entry.serial_number]
            .filter(Boolean)
            .some((value) => value.toLowerCase().includes(equipmentSearch.value.toLowerCase()));
        const matchesStatus = equipmentStatusFilter.value === 'all' || entry.maintenance_status === equipmentStatusFilter.value;

        return matchesSearch && matchesStatus;
    }),
);

const statusLabel = (status) => status.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-cyan-200">Equipment Workspace</p>
        <h2 class="text-sm font-bold italic text-white">Create and review photobooth equipment</h2>
        <p class="text-sm text-stone-300">
            Browse equipment records in a simple list view. Click a row to open its full page.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
        <div class="sticky top-0 z-10 -mx-3 -mt-3 mb-3 rounded-t-2xl border-b border-white/10 bg-[#132035] px-3 pb-3 pt-3">
            <div class="px-2">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Created Equipment</p>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <h3 class="text-lg font-semibold">Equipment list</h3>
                        <span class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 text-xs text-stone-300">{{ filteredEquipment.length }}</span>
                    </div>
                    <a :href="data.routes.create" class="rounded-xl bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200">
                        Create equipment
                    </a>
                </div>
            </div>
            <div class="mt-3 grid gap-2">
                <input v-model="equipmentSearch" type="text" placeholder="Search equipment" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-cyan-300/50">
                <div class="grid grid-cols-4 gap-2">
                    <button type="button" class="rounded-lg border px-2 py-1.5 text-xs font-medium transition" :class="equipmentStatusFilter === 'all' ? 'border-cyan-300/40 bg-cyan-300/10 text-white' : 'border-white/10 text-stone-300 hover:bg-white/5'" @click="equipmentStatusFilter = 'all'">All</button>
                    <button type="button" class="rounded-lg border px-2 py-1.5 text-xs font-medium transition" :class="equipmentStatusFilter === 'ready' ? 'border-emerald-300/40 bg-emerald-300/10 text-white' : 'border-white/10 text-stone-300 hover:bg-white/5'" @click="equipmentStatusFilter = 'ready'">Ready</button>
                    <button type="button" class="rounded-lg border px-2 py-1.5 text-xs font-medium transition" :class="equipmentStatusFilter === 'maintenance' ? 'border-amber-300/40 bg-amber-300/10 text-white' : 'border-white/10 text-stone-300 hover:bg-white/5'" @click="equipmentStatusFilter = 'maintenance'">Maint.</button>
                    <button type="button" class="rounded-lg border px-2 py-1.5 text-xs font-medium transition" :class="equipmentStatusFilter === 'retired' ? 'border-rose-300/40 bg-rose-300/10 text-white' : 'border-white/10 text-stone-300 hover:bg-white/5'" @click="equipmentStatusFilter = 'retired'">Retired</button>
                </div>
            </div>
            <div class="mt-3 grid grid-cols-[minmax(0,1fr)_auto] gap-2 px-2 text-[11px] uppercase tracking-[0.2em] text-stone-500">
                <span>Equipment</span>
                <span>Status</span>
            </div>
        </div>

        <div class="max-h-[70vh] overflow-y-auto">
            <a
                v-for="entry in filteredEquipment"
                :key="entry.id"
                :href="entry.show_url"
                class="grid w-full grid-cols-[minmax(0,1fr)_auto] items-center gap-3 border-b px-3 py-3 text-left transition hover:bg-white/[0.03]"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-white">{{ entry.name }}</p>
                    <p class="mt-1 truncate text-xs text-stone-400">{{ entry.category || 'Uncategorized' }} · ${{ entry.daily_rate }}/day</p>
                </div>
                <span class="inline-flex h-8 items-center justify-center rounded-full px-3 text-[11px] font-medium leading-none" :class="entry.maintenance_status === 'ready' ? 'bg-emerald-400/15 text-emerald-200' : entry.maintenance_status === 'maintenance' ? 'bg-amber-300/15 text-amber-200' : 'bg-rose-400/15 text-rose-200'">
                    {{ statusLabel(entry.maintenance_status) }}
                </span>
            </a>

            <div v-if="!filteredEquipment.length" class="rounded-2xl border border-dashed border-white/15 bg-stone-950/40 px-4 py-5 text-sm text-stone-400">
                No equipment records match the current filters.
            </div>
        </div>
    </section>
</template>
