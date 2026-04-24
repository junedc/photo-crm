<script setup>
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';
import { firstError, hasFieldErrors, isBlank, mergeFieldErrors, requiredMessage } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const photoInput = ref(null);
const deleteForm = ref(null);
const { saving, fieldErrors, submitForm } = useWorkspaceCrud();
const addonRecord = ref(props.data.addon);
const clientErrors = ref({});
const showDeleteConfirm = ref(false);
const form = ref({
    sku: props.data.addon?.product_code ?? '',
    name: props.data.addon?.name ?? '',
    addon_category: props.data.addon?.addon_category ?? '',
    is_publicly_displayed: Boolean(props.data.addon?.is_publicly_displayed),
    description: props.data.addon?.description ?? '',
    unit_price: props.data.addon?.price ?? '',
    duration: props.data.addon?.duration ?? '',
    due_days_before_event: props.data.addon?.due_days_before_event ?? '',
});
const validationErrors = computed(() => mergeFieldErrors(clientErrors.value, fieldErrors.value));
const addOnCategories = computed(() => props.data.addOnCategories ?? []);
const isActionCategory = computed(() => form.value.addon_category === 'Action');

watch(() => form.value.addon_category, (value) => {
    if (value !== 'Action') {
        form.value.due_days_before_event = '';
    }
});

const validateAddOnForm = () => {
    const errors = {};

    if (isBlank(form.value.sku)) {
        errors.sku = requiredMessage('Product code');
    }

    if (isBlank(form.value.name)) {
        errors.name = requiredMessage('Name');
    }

    if (form.value.addon_category && !addOnCategories.value.includes(form.value.addon_category)) {
        errors.addon_category = 'Choose Action or Items.';
    }

    if (isBlank(form.value.unit_price)) {
        errors.unit_price = requiredMessage('Price');
    } else if (Number(form.value.unit_price) < 0) {
        errors.unit_price = 'Price must be zero or greater.';
    }

    clientErrors.value = errors;

    return !hasFieldErrors(errors);
};

const updateAddOn = async () => {
    if (!validateAddOnForm()) {
        return;
    }

    const formData = new FormData();
    formData.append('_method', 'PUT');

    ['sku', 'name', 'addon_category', 'description', 'unit_price', 'duration', 'due_days_before_event'].forEach((key) => {
        formData.append(key, form.value[key] ?? '');
    });
    formData.append('is_publicly_displayed', form.value.is_publicly_displayed ? '1' : '0');

    const file = photoInput.value?.files?.[0];
    if (file) {
        formData.append('photo', file);
    }

    try {
        const record = await submitForm({
            url: addonRecord.value.update_url,
            method: 'post',
            data: formData,
        });

        addonRecord.value = record;
        form.value = {
            sku: record.product_code ?? '',
            name: record.name ?? '',
            addon_category: record.addon_category ?? '',
            is_publicly_displayed: Boolean(record.is_publicly_displayed),
            description: record.description ?? '',
            unit_price: record.price ?? '',
            duration: record.duration ?? '',
            due_days_before_event: record.due_days_before_event ?? '',
        };
        clientErrors.value = {};

        window.history.replaceState({}, '', record.show_url);

        if (photoInput.value) {
            photoInput.value.value = '';
        }
    } catch {}
};

const removeAddOn = async () => {
    if (!addonRecord.value?.delete_url) {
        return;
    }

    showDeleteConfirm.value = true;
};

const confirmRemoveAddOn = () => {
    deleteForm.value?.submit();
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-emerald-200">Add-Ons Workspace</p>
        <h2 class="text-sm font-bold italic text-white">{{ addonRecord.name }}</h2>
        <p class="text-sm text-stone-300">
            Review and update the full add-on record on its own page.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-emerald-200">Add-On Details</p>
                <h3 class="mt-1 text-sm font-semibold italic">{{ addonRecord.name }}</h3>
            </div>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="rounded-xl border border-rose-400/30 px-4 py-2 text-sm font-semibold text-rose-100 transition hover:bg-rose-400/10 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="removeAddOn"
                >
                    Delete add-on
                </button>
                <a :href="data.routes.addons" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">
                    Back to list
                </a>
            </div>
        </div>

        <form ref="deleteForm" :action="addonRecord.delete_url" method="post" class="hidden">
            <input type="hidden" name="_token" :value="data.csrfToken">
            <input type="hidden" name="_method" value="DELETE">
        </form>

        <img v-if="addonRecord.photo_url" :src="addonRecord.photo_url" :alt="addonRecord.name" class="mb-4 h-48 w-full rounded-2xl object-cover">

        <form class="space-y-4" novalidate @submit.prevent="updateAddOn">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Product Code</label>
                    <input v-model="form.sku" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50" :class="firstError(validationErrors, 'sku') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'sku')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'sku') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Name</label>
                    <input v-model="form.name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50" :class="firstError(validationErrors, 'name') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'name') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Add-on Category</label>
                    <select
                        v-model="form.addon_category"
                        class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50"
                        :class="firstError(validationErrors, 'addon_category') ? 'border-rose-300/60' : ''"
                    >
                        <option value="">Select category</option>
                        <option v-for="category in addOnCategories" :key="category" :value="category">{{ category }}</option>
                    </select>
                    <p v-if="firstError(validationErrors, 'addon_category')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'addon_category') }}</p>
                    <p v-else class="mt-1 text-xs text-stone-500">Used for customer-facing grouping in the booking wizard.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Price</label>
                    <input v-model="form.unit_price" type="number" min="0" step="0.01" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50" :class="firstError(validationErrors, 'unit_price') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'unit_price')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'unit_price') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Duration</label>
                    <input v-model="form.duration" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50">
                </div>
                <div v-if="isActionCategory">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Due Date Before the Event</label>
                    <input v-model="form.due_days_before_event" type="number" min="0" step="1" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50" placeholder="Number of days">
                    <p class="mt-1 text-xs text-stone-500">Creates a booking task due this many days before the event date.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Public Display</label>
                    <label class="flex min-h-[42px] items-center gap-3 rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-stone-200">
                        <input v-model="form.is_publicly_displayed" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-slate-950 text-emerald-300 focus:ring-emerald-300">
                        <span>Show in booking create</span>
                    </label>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Description</label>
                    <textarea v-model="form.description" rows="4" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50" />
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Replace image</label>
                    <input ref="photoInput" type="file" accept="image/*" class="block w-full rounded-xl border border-dashed border-white/15 bg-slate-950/70 px-3 py-2.5 text-sm text-stone-300 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-xs file:font-semibold file:text-stone-950" :class="firstError(validationErrors, 'photo') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'photo')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'photo') }}</p>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-emerald-300/40 hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Update add-on' }}
                </button>
            </div>
        </form>
    </section>

    <ConfirmDialog
        :open="showDeleteConfirm"
        title="Delete add-on?"
        :message="`Delete add-on &quot;${addonRecord.name}&quot;? This cannot be undone.`"
        confirm-label="Delete add-on"
        @cancel="showDeleteConfirm = false"
        @confirm="confirmRemoveAddOn"
    />
</template>
