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

const editPhotoInput = ref(null);
const deleteForm = ref(null);
const { saving, fieldErrors, submitForm } = useWorkspaceCrud();
const packageRecord = ref(props.data.package);
const isEditing = ref(false);
const clientErrors = ref({});
const showDeleteConfirm = ref(false);

const makeFormState = (record) => ({
    name: record?.name ?? '',
    description: record?.description ?? '',
    base_price: record?.base_price ?? '',
    is_active: Boolean(record?.is_active),
    equipment_ids: [...(record?.equipment_ids ?? [])],
    add_on_ids: [...(record?.add_on_ids ?? [])],
    hourly_prices: (record?.hourly_prices ?? []).map((entry) => ({
        hours: entry.hours ?? '',
        price: entry.price ?? '',
    })),
});

const form = ref(makeFormState(props.data.package));
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

const beginEditing = () => {
    form.value = makeFormState(packageRecord.value);
    clientErrors.value = {};
    isEditing.value = true;
};

const cancelEditing = () => {
    form.value = makeFormState(packageRecord.value);
    clientErrors.value = {};

    if (editPhotoInput.value) {
        editPhotoInput.value.value = '';
    }

    isEditing.value = false;
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

const updatePackage = async () => {
    if (!validatePackageForm()) {
        return;
    }

    const formData = new FormData();
    formData.append('_method', 'PUT');
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

    const file = editPhotoInput.value?.files?.[0];
    if (file) {
        formData.append('photo', file);
    }

    try {
        const record = await submitForm({
            url: packageRecord.value.update_url,
            method: 'post',
            data: formData,
        });

        packageRecord.value = record;
        form.value = makeFormState(record);
        clientErrors.value = {};
        window.history.replaceState({}, '', record.show_url);

        if (editPhotoInput.value) {
            editPhotoInput.value.value = '';
        }

        isEditing.value = false;
    } catch {}
};

const removePackage = async () => {
    if (!packageRecord.value?.delete_url) {
        return;
    }

    showDeleteConfirm.value = true;
};

const confirmRemovePackage = () => {
    deleteForm.value?.submit();
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-amber-200">Package Workspace</p>
        <h2 class="text-sm font-bold italic text-white">{{ packageRecord.name }}</h2>
        <p class="text-sm text-stone-300">
            Review package details first, then switch into edit mode only when you want to update the record.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-amber-200">Package Details</p>
                <h3 class="mt-2 text-lg font-semibold">{{ packageRecord.name }}</h3>
            </div>
            <div class="flex items-center gap-3">
                <span class="rounded-full px-3 py-1 text-xs font-medium" :class="packageRecord.is_active ? 'bg-emerald-400/15 text-emerald-200' : 'bg-stone-700/60 text-stone-300'">
                    {{ packageRecord.is_active ? 'Active' : 'Inactive' }}
                </span>
                <button
                    type="button"
                    class="rounded-xl border border-amber-300/30 px-4 py-2 text-sm font-semibold text-amber-100 transition hover:bg-amber-300/10"
                    @click="beginEditing"
                >
                    Edit package
                </button>
                <button
                    type="button"
                    class="rounded-xl border border-rose-400/30 px-4 py-2 text-sm font-semibold text-rose-100 transition hover:bg-rose-400/10 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="removePackage"
                >
                    Delete package
                </button>
                <a :href="data.routes.packages" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">
                    Back to list
                </a>
            </div>
        </div>

        <form ref="deleteForm" :action="packageRecord.delete_url" method="post" class="hidden">
            <input type="hidden" name="_token" :value="data.csrfToken">
            <input type="hidden" name="_method" value="DELETE">
        </form>

        <div v-if="packageRecord.photo_url" class="mb-4 overflow-hidden rounded-2xl border border-white/10 bg-slate-950/60 p-4">
            <img :src="packageRecord.photo_url" :alt="packageRecord.name" class="h-64 w-full rounded-xl object-contain">
        </div>

        <div class="mb-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Base Price</p>
                <p class="mt-2 text-xl font-semibold">${{ packageRecord.base_price }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Created</p>
                <p class="mt-2 text-base font-semibold">{{ packageRecord.created_at }}</p>
            </div>
        </div>

        <div class="mb-4 rounded-xl border border-white/10 bg-slate-950/50 p-3">
            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Hourly Pricing</p>
            <div v-if="packageRecord.hourly_prices?.length" class="mt-3 grid gap-3 sm:grid-cols-2">
                <div v-for="(entry, index) in packageRecord.hourly_prices" :key="`${entry.hours}-${index}`" class="rounded-xl border border-white/10 bg-white/[0.03] p-3">
                    <p class="text-sm font-semibold text-white">{{ entry.hours_label }}</p>
                    <p class="mt-2 text-sm text-amber-100">${{ entry.price }}</p>
                </div>
            </div>
            <p v-else class="mt-3 text-sm text-stone-500">No hour-based pricing added yet. Base price is used for all durations.</p>
        </div>

        <div class="mb-4 rounded-xl border border-white/10 bg-slate-950/50 p-3">
            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Description</p>
            <p class="mt-3 text-sm leading-6 text-stone-300">{{ packageRecord.description || 'No description added yet.' }}</p>
        </div>

        <div class="mb-4 rounded-xl border border-white/10 bg-slate-950/50 p-3">
            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Assigned Equipment</p>
            <div v-if="packageRecord.equipment?.length" class="mt-3 grid gap-3 lg:grid-cols-2">
                <article v-for="asset in packageRecord.equipment" :key="asset.id" class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03] shadow-lg shadow-black/10">
                    <div v-if="asset.photo_url" class="bg-gradient-to-br from-slate-950 via-slate-900 to-amber-950/30 p-3">
                        <div class="flex h-32 items-center justify-center overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04] p-2 shadow-inner shadow-black/30">
                            <img :src="asset.photo_url" :alt="asset.name" class="max-h-full max-w-full object-contain drop-shadow-2xl">
                        </div>
                    </div>
                    <div v-else class="flex h-32 items-center justify-center bg-gradient-to-br from-slate-950 via-slate-900 to-amber-950/30 p-3">
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-2xl text-stone-500">P</div>
                    </div>
                    <div class="p-3">
                        <p class="text-base font-medium text-white">{{ asset.name }}</p>
                        <p class="mt-1 text-xs text-stone-400">{{ asset.category || 'Uncategorized' }}</p>
                    </div>
                </article>
            </div>
            <p v-else class="mt-3 text-sm text-stone-500">No equipment assigned yet.</p>
        </div>

        <div class="mb-4 rounded-xl border border-white/10 bg-slate-950/50 p-3">
            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Assigned Add-Ons</p>
            <div v-if="packageRecord.add_ons?.length" class="mt-3 grid gap-3 lg:grid-cols-2">
                <article v-for="addOn in packageRecord.add_ons" :key="addOn.id" class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03] shadow-lg shadow-black/10">
                    <div v-if="addOn.photo_url" class="bg-gradient-to-br from-slate-950 via-slate-900 to-rose-950/30 p-3">
                        <div class="flex h-32 items-center justify-center overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04] p-2 shadow-inner shadow-black/30">
                            <img :src="addOn.photo_url" :alt="addOn.name" class="max-h-full max-w-full object-contain drop-shadow-2xl">
                        </div>
                    </div>
                    <div v-else class="flex h-32 items-center justify-center bg-gradient-to-br from-slate-950 via-slate-900 to-rose-950/30 p-3">
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-2xl text-stone-500">A</div>
                    </div>
                    <div class="p-3">
                        <p class="text-base font-medium text-white">{{ addOn.name }}</p>
                        <p class="mt-1 text-xs text-stone-400">{{ addOn.product_code || 'Add-On' }}<span v-if="addOn.duration"> Â· {{ addOn.duration }}</span></p>
                        <p class="mt-2 text-xs text-stone-300">${{ addOn.price }}</p>
                        <p class="mt-3 text-sm leading-6 text-stone-300">{{ addOn.description || 'No description added yet.' }}</p>
                    </div>
                </article>
            </div>
            <p v-else class="mt-3 text-sm text-stone-500">No add-ons assigned yet.</p>
        </div>

        <section v-if="isEditing" class="mt-6 rounded-2xl border border-amber-300/20 bg-amber-300/5 p-5">
            <div class="mb-5 flex items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.3em] text-amber-200">Edit Package</p>
                    <h4 class="mt-2 text-lg font-semibold">Update details</h4>
                </div>
                <button
                    type="button"
                    class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5"
                    @click="cancelEditing"
                >
                    Cancel
                </button>
            </div>

            <form class="space-y-4" novalidate @submit.prevent="updatePackage">
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
                                <p class="mt-1 text-sm text-stone-500">Match specific booking hours to specific package prices.</p>
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
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Replace photo</label>
                        <input ref="editPhotoInput" type="file" accept="image/*" class="block w-full rounded-xl border border-dashed border-white/15 bg-slate-950/70 px-3 py-2.5 text-sm text-stone-300 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-xs file:font-semibold file:text-stone-950">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2.5 text-sm text-stone-200">
                            <input v-model="form.is_active" value="1" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-stone-900 text-amber-300">
                            Active package
                        </label>
                    </div>
                    <div class="sm:col-span-2 rounded-xl border border-white/10 bg-slate-950/50 p-3">
                        <p class="mb-2 text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Assigned equipment</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label v-for="asset in data.equipmentOptions" :key="asset.id" class="flex items-center gap-3 rounded-lg border border-white/10 px-3 py-2 text-sm text-stone-200">
                                <input v-model="form.equipment_ids" :value="asset.id" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-stone-900 text-amber-300">
                                <span>{{ asset.name }}<span class="text-stone-500"> {{ asset.category ? `Â· ${asset.category}` : '' }}</span></span>
                            </label>
                        </div>
                        <p v-if="!data.equipmentOptions?.length" class="text-sm text-stone-500">Add equipment records first to assign them here.</p>
                    </div>
                    <div class="sm:col-span-2 rounded-xl border border-white/10 bg-slate-950/50 p-3">
                        <p class="mb-2 text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Assigned add-ons</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label v-for="addOn in data.addOnOptions" :key="addOn.id" class="flex items-center gap-3 rounded-lg border border-white/10 px-3 py-2 text-sm text-stone-200">
                                <input v-model="form.add_on_ids" :value="addOn.id" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-stone-900 text-amber-300">
                                <span>{{ addOn.name }}<span class="text-stone-500"> {{ addOn.product_code ? `Â· ${addOn.product_code}` : '' }}{{ addOn.duration ? ` Â· ${addOn.duration}` : '' }}</span></span>
                            </label>
                        </div>
                        <p v-if="!data.addOnOptions?.length" class="text-sm text-stone-500">Add add-on records first to assign them here.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/5"
                        @click="cancelEditing"
                    >
                        Cancel
                    </button>
                    <button type="submit" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-amber-300/40 hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                        {{ saving ? 'Saving...' : 'Update package' }}
                    </button>
                </div>
            </form>
        </section>
    </section>

    <ConfirmDialog
        :open="showDeleteConfirm"
        title="Delete package?"
        :message="`Delete package &quot;${packageRecord.name}&quot;? This cannot be undone.`"
        confirm-label="Delete package"
        @cancel="showDeleteConfirm = false"
        @confirm="confirmRemovePackage"
    />
</template>
