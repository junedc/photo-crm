<script setup>
import { computed, ref } from 'vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';
import { firstError, hasFieldErrors, isBlank, mergeFieldErrors, requiredMessage } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const { saving, fieldErrors, submitForm } = useWorkspaceCrud();
const deleteForm = ref(null);
const discountRecord = ref(props.data.discount);
const clientErrors = ref({});
const showDeleteConfirm = ref(false);
const isEditing = ref(false);
const form = ref({
    code: props.data.discount?.code ?? '',
    name: props.data.discount?.name ?? '',
    starts_at: props.data.discount?.starts_at ?? '',
    ends_at: props.data.discount?.ends_at ?? '',
    discount_type: props.data.discount?.discount_type ?? 'percentage',
    discount_value: props.data.discount?.discount_value ?? '',
    package_ids: [...(props.data.discount?.package_ids ?? [])],
});

const discountValueLabel = computed(() => (
    discountRecord.value.discount_type === 'percentage'
        ? `${discountRecord.value.discount_value}%`
        : `$${discountRecord.value.discount_value}`
));
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

const updateDiscount = async () => {
    if (!validateDiscountForm()) {
        return;
    }

    try {
        const record = await submitForm({
            url: discountRecord.value.update_url,
            method: 'put',
            data: {
                ...form.value,
            },
        });

        discountRecord.value = record;
        form.value = {
            code: record.code ?? '',
            name: record.name ?? '',
            starts_at: record.starts_at ?? '',
            ends_at: record.ends_at ?? '',
            discount_type: record.discount_type ?? 'percentage',
            discount_value: record.discount_value ?? '',
            package_ids: [...(record.package_ids ?? [])],
        };
        clientErrors.value = {};
        isEditing.value = false;

        window.history.replaceState({}, '', record.show_url);
    } catch {}
};

const startEditing = () => {
    clientErrors.value = {};
    form.value = {
        code: discountRecord.value?.code ?? '',
        name: discountRecord.value?.name ?? '',
        starts_at: discountRecord.value?.starts_at ?? '',
        ends_at: discountRecord.value?.ends_at ?? '',
        discount_type: discountRecord.value?.discount_type ?? 'percentage',
        discount_value: discountRecord.value?.discount_value ?? '',
        package_ids: [...(discountRecord.value?.package_ids ?? [])],
    };
    isEditing.value = true;
};

const cancelEditing = () => {
    clientErrors.value = {};
    isEditing.value = false;
};

const removeDiscount = async () => {
    if (!discountRecord.value?.delete_url) {
        return;
    }

    showDeleteConfirm.value = true;
};

const confirmRemoveDiscount = () => {
    deleteForm.value?.submit();
};

const assignedPackages = computed(() => discountRecord.value?.packages ?? []);
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-violet-200">Discounts Workspace</p>
        <h2 class="text-sm font-bold italic text-white">{{ discountRecord.name }}</h2>
        <p class="text-sm text-stone-300">
            Review the discount campaign details, then update the date range or item assignments when needed.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-violet-200">Discount Details</p>
                <h3 class="mt-1 text-sm font-semibold italic">{{ discountRecord.code }}</h3>
            </div>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="rounded-xl border border-violet-300/30 px-4 py-2 text-sm font-semibold text-violet-100 transition hover:bg-violet-400/10"
                    @click="startEditing"
                >
                    Edit discount
                </button>
                <button
                    type="button"
                    class="rounded-xl border border-rose-400/30 px-4 py-2 text-sm font-semibold text-rose-100 transition hover:bg-rose-400/10"
                    @click="removeDiscount"
                >
                    Delete discount
                </button>
                <a :href="data.routes.discounts" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">
                    Back to list
                </a>
            </div>
        </div>

        <form ref="deleteForm" :action="discountRecord.delete_url" method="post" class="hidden">
            <input type="hidden" name="_token" :value="data.csrfToken">
            <input type="hidden" name="_method" value="DELETE">
        </form>

        <div v-if="!isEditing" class="mb-4 grid gap-3 lg:grid-cols-4">
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Code</p>
                <p class="mt-2 text-base font-semibold">{{ discountRecord.code }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Value</p>
                <p class="mt-2 text-base font-semibold">{{ discountValueLabel }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Start Date</p>
                <p class="mt-2 text-base font-semibold">{{ discountRecord.starts_at_label }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">End Date</p>
                <p class="mt-2 text-base font-semibold">{{ discountRecord.ends_at_label }}</p>
            </div>
        </div>

        <div v-if="!isEditing" class="mb-4 grid gap-4">
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-4">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Assigned Packages</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span v-for="item in assignedPackages" :key="`package-${item.id}`" class="rounded-full border border-violet-300/30 bg-violet-400/10 px-3 py-1 text-xs text-violet-100">
                        {{ item.name }}
                    </span>
                    <span v-if="!assignedPackages.length" class="text-sm text-stone-400">No packages selected.</span>
                </div>
            </div>
        </div>

        <section v-if="!isEditing" class="rounded-2xl border border-white/10 bg-slate-950/40 p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Discount Overview</p>
                    <h4 class="mt-1 text-sm font-semibold italic">Read-only details</h4>
                </div>
                <button type="button" class="rounded-xl bg-violet-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-violet-200" @click="startEditing">
                    Edit Discount
                </button>
            </div>

            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Name</p>
                    <p class="mt-2 text-sm font-semibold text-white">{{ discountRecord.name }}</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Discount Type</p>
                    <p class="mt-2 text-sm font-semibold text-white">{{ discountRecord.discount_type_label }}</p>
                </div>
            </div>
        </section>

        <form v-if="isEditing" class="space-y-5 rounded-2xl border border-white/10 bg-slate-950/40 p-4" novalidate @submit.prevent="updateDiscount">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Edit Discount</p>
                    <h4 class="mt-1 text-sm font-semibold italic">Update discount details and package assignments</h4>
                </div>
                <button type="button" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5" @click="cancelEditing">
                    Cancel
                </button>
            </div>

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
                        <h4 class="mt-1 text-sm font-semibold italic">Apply to packages</h4>
                    </div>
                    <div class="max-h-64 space-y-2 overflow-y-auto">
                        <label v-for="item in data.packageOptions" :key="item.id" class="flex items-start gap-3 rounded-xl border border-white/10 px-3 py-2.5 text-sm text-stone-200 transition hover:bg-white/[0.03]">
                            <input v-model="form.package_ids" :value="item.id" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-white/20 bg-slate-950/70 text-violet-300 focus:ring-violet-300/40">
                            <span>{{ item.name }}</span>
                        </label>
                    </div>
                </div>

            </div>

            <div class="flex justify-end">
                <button type="submit" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-violet-300/40 hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Update discount' }}
                </button>
            </div>
        </form>
    </section>

    <ConfirmDialog
        :open="showDeleteConfirm"
        title="Delete discount?"
        :message="`Delete discount &quot;${discountRecord.code}&quot;? This cannot be undone.`"
        confirm-label="Delete discount"
        @cancel="showDeleteConfirm = false"
        @confirm="confirmRemoveDiscount"
    />
</template>
