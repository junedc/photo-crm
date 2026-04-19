<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const discounts = ref([...(props.data.discounts ?? [])]);
const search = ref('');
const selectedType = ref('all');

const filteredDiscounts = computed(() =>
    discounts.value.filter((discount) => {
        const matchesSearch = [discount.code, discount.name]
            .filter(Boolean)
            .some((value) => value.toLowerCase().includes(search.value.toLowerCase()));

        const matchesType = selectedType.value === 'all' || discount.discount_type === selectedType.value;

        return matchesSearch && matchesType;
    }),
);

const valueLabel = (discount) => {
    if (discount.discount_type === 'percentage') {
        return `${discount.discount_value}%`;
    }

    return `$${discount.discount_value}`;
};

const assignedPackages = (discount) => discount.packages ?? [];
</script>

<template>
    <section class="rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-4 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-violet-200">Discounts Workspace</p>
        <h2 class="mt-2 text-xl font-semibold tracking-tight">Manage customer discount campaigns</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-300">
            Create discount codes, control date ranges, and choose which packages each offer applies to.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
        <div class="sticky top-0 z-10 -mx-3 -mt-3 mb-3 rounded-t-2xl border-b border-white/10 bg-[#132035] px-3 pb-3 pt-3">
            <div class="px-2">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Discount Records</p>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <h3 class="text-lg font-semibold">Discount list</h3>
                        <span class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 text-xs text-stone-300">{{ filteredDiscounts.length }}</span>
                    </div>
                    <a :href="data.routes.create" class="rounded-xl bg-violet-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-violet-200">
                        Create discount
                    </a>
                </div>
            </div>
            <div class="mt-3 grid gap-2 md:grid-cols-[minmax(0,1fr)_220px]">
                <input v-model="search" type="text" placeholder="Search code or name" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-violet-300/50">
                <select v-model="selectedType" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-violet-300/50">
                    <option value="all">All discount types</option>
                    <option v-for="(label, key) in data.discountTypes" :key="key" :value="key">{{ label }}</option>
                </select>
            </div>
            <div class="mt-3 grid grid-cols-[160px_minmax(0,1.1fr)_180px_140px_220px] gap-2 px-2 text-[11px] uppercase tracking-[0.2em] text-stone-500">
                <span>Code</span>
                <span>Discount</span>
                <span>Date Range</span>
                <span>Value</span>
                <span>Applies To</span>
            </div>
        </div>

        <div class="max-h-[70vh] overflow-y-auto">
            <a
                v-for="discount in filteredDiscounts"
                :key="discount.id"
                :href="discount.show_url"
                class="grid w-full grid-cols-[160px_minmax(0,1.1fr)_180px_140px_220px] gap-3 border-b px-3 py-3 text-left transition hover:bg-white/[0.03]"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-violet-100">{{ discount.code }}</p>
                    <p class="mt-1 truncate text-xs text-stone-400">{{ discount.discount_type_label }}</p>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-white">{{ discount.name }}</p>
                    <p class="mt-1 truncate text-xs text-stone-400">
                        {{ assignedPackages(discount).length }} package{{ assignedPackages(discount).length === 1 ? '' : 's' }}
                    </p>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-stone-300">{{ discount.starts_at_label }}</p>
                    <p class="mt-1 truncate text-xs text-stone-400">to {{ discount.ends_at_label }}</p>
                </div>
                <div class="min-w-0">
                    <span class="rounded-full bg-violet-400/15 px-2.5 py-1 text-center text-[11px] font-medium text-violet-200">
                        {{ valueLabel(discount) }}
                    </span>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm text-stone-300">
                        {{ assignedPackages(discount)[0]?.name || 'No packages selected' }}
                    </p>
                    <p class="mt-1 truncate text-xs text-stone-400">
                        {{ assignedPackages(discount).length > 1 ? `${assignedPackages(discount).length - 1} more package(s)` : 'Single assignment' }}
                    </p>
                </div>
            </a>

            <div v-if="!filteredDiscounts.length" class="rounded-2xl border border-dashed border-white/15 bg-stone-950/40 px-4 py-5 text-sm text-stone-400">
                No discounts match the current filters.
            </div>
        </div>
    </section>
</template>
