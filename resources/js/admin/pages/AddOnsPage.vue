<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const addOnList = ref([...(props.data.addons ?? [])]);
const addOns = computed(() => addOnList.value);
const search = ref('');

const filteredAddOns = computed(() =>
    addOns.value.filter((entry) =>
        [entry.product_code, entry.name, entry.description, entry.duration]
            .filter(Boolean)
            .some((value) => value.toLowerCase().includes(search.value.toLowerCase())),
    ),
);
</script>

<template>
    <section class="rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-4 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-emerald-200">Add-Ons Workspace</p>
        <h2 class="mt-2 text-xl font-semibold tracking-tight">Create and review package add-ons</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-300">
            Manage optional add-ons with their product code, pricing, image, and duration.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
        <div class="sticky top-0 z-10 -mx-3 -mt-3 mb-3 rounded-t-2xl border-b border-white/10 bg-[#132035] px-3 pb-3 pt-3">
            <div class="px-2">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Created Add-Ons</p>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <h3 class="text-lg font-semibold">Add-on list</h3>
                        <span class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 text-xs text-stone-300">{{ filteredAddOns.length }}</span>
                    </div>
                    <a :href="data.routes.create" class="rounded-xl bg-emerald-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-emerald-200">
                        Create add-on
                    </a>
                </div>
            </div>
            <div class="mt-3 grid gap-2">
                <input v-model="search" type="text" placeholder="Search add-ons" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-emerald-300/50">
            </div>
            <div class="mt-3 grid grid-cols-[140px_minmax(0,1fr)_auto] gap-2 px-2 text-[11px] uppercase tracking-[0.2em] text-stone-500">
                <span>Code</span>
                <span>Name</span>
                <span>Price</span>
            </div>
        </div>

        <div class="max-h-[70vh] overflow-y-auto">
            <a
                v-for="entry in filteredAddOns"
                :key="entry.id"
                :href="entry.show_url"
                class="grid w-full grid-cols-[140px_minmax(0,1fr)_auto] gap-3 border-b px-3 py-3 text-left transition hover:bg-white/[0.03]"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-stone-300">{{ entry.product_code }}</p>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-white">{{ entry.name }}</p>
                    <p class="mt-1 truncate text-xs text-stone-400">{{ entry.duration || 'No duration set' }}</p>
                </div>
                <span class="rounded-full bg-emerald-400/15 px-2.5 py-1 text-[11px] font-medium text-emerald-200">
                    ${{ entry.price }}
                </span>
            </a>

            <div v-if="!filteredAddOns.length" class="rounded-2xl border border-dashed border-white/15 bg-stone-950/40 px-4 py-5 text-sm text-stone-400">
                No add-ons match the current search.
            </div>
        </div>
    </section>
</template>
