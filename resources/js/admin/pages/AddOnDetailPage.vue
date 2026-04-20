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

const photoInput = ref(null);
const deleteForm = ref(null);
const { saving, fieldErrors, submitForm } = useWorkspaceCrud();
const addonRecord = ref(props.data.addon);
const clientErrors = ref({});
const showDeleteConfirm = ref(false);
const form = ref({
    sku: props.data.addon?.product_code ?? '',
    name: props.data.addon?.name ?? '',
    description: props.data.addon?.description ?? '',
    unit_price: props.data.addon?.price ?? '',
    duration: props.data.addon?.duration ?? '',
});
const validationErrors = computed(() => mergeFieldErrors(clientErrors.value, fieldErrors.value));

const validateAddOnForm = () => {
    const errors = {};

    if (isBlank(form.value.sku)) {
        errors.sku = requiredMessage('Product code');
    }

    if (isBlank(form.value.name)) {
        errors.name = requiredMessage('Name');
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

    ['sku', 'name', 'description', 'unit_price', 'duration'].forEach((key) => {
        formData.append(key, form.value[key] ?? '');
    });

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
            description: record.description ?? '',
            unit_price: record.price ?? '',
            duration: record.duration ?? '',
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
                <h3 class="mt-2 text-lg font-semibold">{{ addonRecord.name }}</h3>
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

        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Product Code</p>
                <p class="mt-2 text-base font-semibold">{{ addonRecord.product_code }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Price</p>
                <p class="mt-2 text-xl font-semibold">${{ addonRecord.price }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Duration</p>
                <p class="mt-2 text-base font-semibold">{{ addonRecord.duration || 'Not set' }}</p>
            </div>
        </div>

        <div class="mb-4 rounded-xl border border-white/10 bg-slate-950/50 p-3">
            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Description</p>
            <p class="mt-3 text-sm leading-6 text-stone-300">{{ addonRecord.description || 'No description added yet.' }}</p>
        </div>

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
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Price</label>
                    <input v-model="form.unit_price" type="number" min="0" step="0.01" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50" :class="firstError(validationErrors, 'unit_price') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'unit_price')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'unit_price') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Duration</label>
                    <input v-model="form.duration" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50">
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Description</label>
                    <textarea v-model="form.description" rows="4" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50" />
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Replace image</label>
                    <input ref="photoInput" type="file" accept="image/*" class="block w-full rounded-xl border border-dashed border-white/15 bg-slate-950/70 px-3 py-2.5 text-sm text-stone-300 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-xs file:font-semibold file:text-stone-950">
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
