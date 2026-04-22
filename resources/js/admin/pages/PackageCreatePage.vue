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

const createPhotoInput = ref(null);
const { saving, fieldErrors, submitForm } = useWorkspaceCrud();
const clientErrors = ref({});
const form = ref({
    name: '',
    description: '',
    base_price: '',
    is_active: true,
    equipment_ids: [],
    add_on_ids: [],
    hourly_prices: [],
});

const validationErrors = computed(() => mergeFieldErrors(clientErrors.value, fieldErrors.value));

const validatePackageForm = () => {
    const errors = {};

    if (isBlank(form.value.name)) {
        errors.name = requiredMessage('Package name');
    }

    if (isBlank(form.value.base_price)) {
        errors.base_price = requiredMessage('Base price');
    } else if (Number(form.value.base_price) < 0) {
        errors.base_price = 'Base price must be zero or greater.';
    }

    (form.value.hourly_prices ?? []).forEach((entry, index) => {
        if (!isBlank(entry.hours) && Number(entry.hours) < 0.25) {
            errors[`hourly_prices.${index}.hours`] = 'Hours must be at least 0.25.';
        }

        if (!isBlank(entry.price) && Number(entry.price) < 0) {
            errors[`hourly_prices.${index}.price`] = 'Price must be zero or greater.';
        }
    });

    clientErrors.value = errors;

    return !hasFieldErrors(errors);
};

const addHourlyPrice = () => {
    form.value.hourly_prices.push({
        hours: '',
        price: '',
    });
};

const removeHourlyPrice = (index) => {
    form.value.hourly_prices.splice(index, 1);
};

const createPackage = async () => {
    if (!validatePackageForm()) {
        return;
    }

    const formData = new FormData();
    formData.append('name', form.value.name ?? '');
    formData.append('description', form.value.description ?? '');
    formData.append('base_price', form.value.base_price ?? '');

    if (form.value.is_active) {
        formData.append('is_active', '1');
    }

    (form.value.equipment_ids ?? []).forEach((id) => {
        formData.append('equipment_ids[]', String(id));
    });

    (form.value.add_on_ids ?? []).forEach((id) => {
        formData.append('add_on_ids[]', String(id));
    });

    (form.value.hourly_prices ?? []).forEach((entry, index) => {
        formData.append(`hourly_prices[${index}][hours]`, entry.hours ?? '');
        formData.append(`hourly_prices[${index}][price]`, entry.price ?? '');
    });

    const file = createPhotoInput.value?.files?.[0];
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
        <p class="text-[11px] uppercase tracking-[0.35em] text-amber-200">Package Workspace</p>
        <h2 class="text-sm font-bold italic text-white">Create package</h2>
        <p class="text-sm text-stone-300">
            Add a new package on its own page, then you will be taken to its full package view.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-amber-200">New Package</p>
                <h3 class="mt-1 text-sm font-semibold italic">Create record</h3>
            </div>
            <a :href="data.routes.packages" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">
                Back to list
            </a>
        </div>

        <form class="space-y-4" novalidate @submit.prevent="createPackage">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Package name</label>
                    <input v-model="form.name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/50" :class="firstError(validationErrors, 'name') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'name') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Base price</label>
                    <input v-model="form.base_price" type="number" min="0" step="0.01" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/50" :class="firstError(validationErrors, 'base_price') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'base_price')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'base_price') }}</p>
                </div>
                <div class="sm:col-span-2 rounded-xl border border-white/10 bg-slate-950/50 p-3">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Hourly package pricing</p>
                            <p class="mt-1 text-sm text-stone-500">Add special package prices for specific booking hours. Base price is used when no hourly match exists.</p>
                        </div>
                        <button type="button" class="rounded-xl border border-amber-300/30 px-3 py-2 text-xs font-semibold text-amber-100 transition hover:bg-amber-300/10" @click="addHourlyPrice">
                            Add pricing row
                        </button>
                    </div>
                    <div v-if="form.hourly_prices.length" class="space-y-3">
                        <div v-for="(entry, index) in form.hourly_prices" :key="index" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                            <div>
                                <input v-model="entry.hours" type="number" min="0.25" step="0.25" placeholder="Hours" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/50" :class="firstError(validationErrors, `hourly_prices.${index}.hours`) ? 'border-rose-300/60' : ''">
                                <p v-if="firstError(validationErrors, `hourly_prices.${index}.hours`)" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, `hourly_prices.${index}.hours`) }}</p>
                            </div>
                            <div>
                                <input v-model="entry.price" type="number" min="0" step="0.01" placeholder="Price" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/50" :class="firstError(validationErrors, `hourly_prices.${index}.price`) ? 'border-rose-300/60' : ''">
                                <p v-if="firstError(validationErrors, `hourly_prices.${index}.price`)" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, `hourly_prices.${index}.price`) }}</p>
                            </div>
                            <button type="button" class="rounded-xl border border-rose-400/30 px-3 py-2 text-xs font-semibold text-rose-100 transition hover:bg-rose-400/10" @click="removeHourlyPrice(index)">
                                Remove
                            </button>
                        </div>
                    </div>
                    <p v-else class="text-sm text-stone-500">No hourly prices added yet.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Description</label>
                    <textarea v-model="form.description" rows="4" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/50" />
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Photo</label>
                    <input ref="createPhotoInput" type="file" accept="image/*" class="block w-full rounded-xl border border-dashed border-white/15 bg-slate-950/70 px-3 py-2.5 text-sm text-stone-300 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-300 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-stone-950">
                </div>
                <div class="sm:col-span-2">
                    <label class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2.5 text-sm text-stone-200">
                        <input v-model="form.is_active" value="1" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-stone-900 text-amber-300">
                        Active package
                    </label>
                </div>
                <div class="sm:col-span-2 rounded-xl border border-white/10 bg-slate-950/50 p-3">
                    <p class="mb-2 text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Assign equipment</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label v-for="asset in data.equipmentOptions" :key="asset.id" class="flex items-center gap-3 rounded-lg border border-white/10 px-3 py-2 text-sm text-stone-200">
                            <input v-model="form.equipment_ids" :value="asset.id" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-stone-900 text-amber-300">
                            <span>{{ asset.name }}<span class="text-stone-500"> {{ asset.category ? `· ${asset.category}` : '' }}</span></span>
                        </label>
                    </div>
                    <p v-if="!data.equipmentOptions?.length" class="text-sm text-stone-500">Add equipment records first to assign them here.</p>
                </div>
                <div class="sm:col-span-2 rounded-xl border border-white/10 bg-slate-950/50 p-3">
                    <p class="mb-2 text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Assign add-ons</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label v-for="addOn in data.addOnOptions" :key="addOn.id" class="flex items-center gap-3 rounded-lg border border-white/10 px-3 py-2 text-sm text-stone-200">
                            <input v-model="form.add_on_ids" :value="addOn.id" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-stone-900 text-amber-300">
                            <span>{{ addOn.name }}<span class="text-stone-500"> {{ addOn.product_code ? `· ${addOn.product_code}` : '' }}{{ addOn.duration ? ` · ${addOn.duration}` : '' }}</span></span>
                        </label>
                    </div>
                    <p v-if="!data.addOnOptions?.length" class="text-sm text-stone-500">Add add-on records first to assign them here.</p>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-xl bg-amber-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Create package' }}
                </button>
            </div>
        </form>
    </section>
</template>
