<script setup>
import { computed, ref } from 'vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';
import { firstError, hasFieldErrors, isBlank, mergeFieldErrors, requiredMessage } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const { saving, fieldErrors, submitForm } = useWorkspaceCrud();
const clientErrors = ref({});
const form = ref({
    code: '',
    name: '',
    starts_at: '',
    ends_at: '',
    discount_type: 'percentage',
    discount_value: '',
    package_ids: [],
});
const validationErrors = computed(() => mergeFieldErrors(clientErrors.value, fieldErrors.value));

const validateDiscountForm = () => {
    const errors = {};

    ['code', 'name', 'starts_at', 'ends_at', 'discount_type', 'discount_value'].forEach((field) => {
        if (isBlank(form.value[field])) {
            const labels = {
                code: 'Code',
                name: 'Name',
                starts_at: 'Start date',
                ends_at: 'End date',
                discount_type: 'Discount type',
                discount_value: form.value.discount_type === 'percentage' ? 'Discount percentage' : 'Specific amount',
            };
            errors[field] = requiredMessage(labels[field]);
        }
    });

    if (!errors.discount_value && Number(form.value.discount_value) < 0) {
        errors.discount_value = 'Discount value must be zero or greater.';
    }

    if (!errors.starts_at && !errors.ends_at && form.value.ends_at < form.value.starts_at) {
        errors.ends_at = 'End date must be on or after the start date.';
    }

    clientErrors.value = errors;

    return !hasFieldErrors(errors);
};

const submitDiscount = async () => {
    if (!validateDiscountForm()) {
        return;
    }

    try {
        const record = await submitForm({
            url: props.data.routes.store,
            data: {
                ...form.value,
            },
        });

        window.setTimeout(() => {
            window.location.href = record.show_url;
        }, 300);
    } catch {}
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-violet-200">Discounts Workspace</p>
        <h2 class="text-sm font-bold italic text-white">Create discount</h2>
        <p class="text-sm text-stone-300">
            Build a discount code, choose whether it is percentage-based or a fixed amount, and assign it to packages.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-violet-200">New Discount</p>
                <h3 class="mt-2 text-lg font-semibold">Create record</h3>
            </div>
            <a :href="data.routes.discounts" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">
                Back to list
            </a>
        </div>

        <form class="space-y-5" novalidate @submit.prevent="submitDiscount">
            <div class="grid gap-4 lg:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Code</label>
                    <input v-model="form.code" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-violet-300/50" :class="firstError(validationErrors, 'code') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'code')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'code') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Name</label>
                    <input v-model="form.name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-violet-300/50" :class="firstError(validationErrors, 'name') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'name') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Start Date</label>
                    <input v-model="form.starts_at" type="date" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-violet-300/50" :class="firstError(validationErrors, 'starts_at') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'starts_at')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'starts_at') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">End Date</label>
                    <input v-model="form.ends_at" type="date" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-violet-300/50" :class="firstError(validationErrors, 'ends_at') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'ends_at')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'ends_at') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Discount Type</label>
                    <select v-model="form.discount_type" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-violet-300/50" :class="firstError(validationErrors, 'discount_type') ? 'border-rose-300/60' : ''">
                        <option v-for="(label, key) in data.discountTypes" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">
                        {{ form.discount_type === 'percentage' ? 'Discount Percentage' : 'Specific Amount' }}
                    </label>
                    <input v-model="form.discount_value" type="number" min="0" step="0.01" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-violet-300/50" :class="firstError(validationErrors, 'discount_value') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'discount_value')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'discount_value') }}</p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                    <div class="mb-3">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Packages</p>
                        <h4 class="mt-2 text-base font-semibold">Apply to packages</h4>
                    </div>
                    <div class="max-h-64 space-y-2 overflow-y-auto">
                        <label v-for="item in data.packageOptions" :key="item.id" class="flex items-start gap-3 rounded-xl border border-white/10 px-3 py-2.5 text-sm text-stone-200 transition hover:bg-white/[0.03]">
                            <input v-model="form.package_ids" :value="item.id" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-white/20 bg-slate-950/70 text-violet-300 focus:ring-violet-300/40">
                            <span>{{ item.name }}</span>
                        </label>
                        <p v-if="!data.packageOptions.length" class="text-sm text-stone-400">No packages available yet.</p>
                    </div>
                </div>

            </div>

            <div class="flex justify-end">
                <button type="submit" class="rounded-xl bg-violet-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-violet-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Create discount' }}
                </button>
            </div>
        </form>
    </section>
</template>
