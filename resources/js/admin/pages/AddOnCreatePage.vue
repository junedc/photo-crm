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

const photoInput = ref(null);
const { saving, fieldErrors, submitForm } = useWorkspaceCrud();
const clientErrors = ref({});
const form = ref({
    sku: '',
    name: '',
    description: '',
    unit_price: '',
    duration: '',
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

const createAddOn = async () => {
    if (!validateAddOnForm()) {
        return;
    }

    const formData = new FormData();

    ['sku', 'name', 'description', 'unit_price', 'duration'].forEach((key) => {
        formData.append(key, form.value[key] ?? '');
    });

    const file = photoInput.value?.files?.[0];
    if (file) {
        formData.append('photo', file);
    }

    try {
        const record = await submitForm({
            url: props.data.routes.store,
            data: formData,
        });

        window.setTimeout(() => {
            window.location.href = record.show_url;
        }, 300);
    } catch {}
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-emerald-200">Add-Ons Workspace</p>
        <h2 class="text-sm font-bold italic text-white">Create add-on</h2>
        <p class="text-sm text-stone-300">
            Add a new package add-on with a product code, image, price, and duration.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-emerald-200">New Add-On</p>
                <h3 class="mt-2 text-lg font-semibold">Create record</h3>
            </div>
            <a :href="data.routes.addons" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">
                Back to list
            </a>
        </div>

        <form class="space-y-4" novalidate @submit.prevent="createAddOn">
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
                    <input v-model="form.duration" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50" placeholder="e.g. 2 hours">
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Description</label>
                    <textarea v-model="form.description" rows="4" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50" />
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Image</label>
                    <input ref="photoInput" type="file" accept="image/*" class="block w-full rounded-xl border border-dashed border-white/15 bg-slate-950/70 px-3 py-2.5 text-sm text-stone-300 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-300 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-stone-950">
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-xl bg-emerald-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-emerald-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Create add-on' }}
                </button>
            </div>
        </form>
    </section>
</template>
