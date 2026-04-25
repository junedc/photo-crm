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
const packageStatusOptions = computed(() => props.data.packageStatusOptions ?? []);

const makeFormState = (record) => ({
    name: record?.name ?? '',
    description: record?.description ?? '',
    base_price: record?.base_price ?? '',
    package_status_id: record?.status_id ? String(record.status_id) : String(props.data.packageStatusOptions?.[0]?.id ?? ''),
    equipment_ids: [...(record?.equipment_ids ?? [])],
    add_on_ids: [...(record?.add_on_ids ?? [])],
    hourly_prices: (record?.hourly_prices ?? []).map((entry) => ({
        hours: entry.hours ?? '',
        price: entry.price ?? '',
    })),
});

const form = ref(makeFormState(props.data.package));
const validationErrors = computed(() => mergeFieldErrors(clientErrors.value, fieldErrors.value));
const assignedItems = computed(() => [
    ...(packageRecord.value.equipment ?? []).map((asset) => ({
        key: `equipment-${asset.id}`,
        type: 'Equipment',
        name: asset.name,
        code: asset.category || 'Uncategorized',
        duration: '-',
        price: asset.daily_rate ? `$${asset.daily_rate}` : '-',
        description: 'Assigned equipment',
    })),
    ...(packageRecord.value.add_ons ?? []).map((addOn) => ({
        key: `addon-${addOn.id}`,
        type: 'Add-On',
        name: addOn.name,
        code: [addOn.product_code, addOn.addon_category].filter(Boolean).join(' / ') || 'Add-On',
        duration: addOn.duration || '-',
        price: addOn.price ? `$${addOn.price}` : '-',
        description: addOn.description || 'No description added yet.',
    })),
]);

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
    formData.append('package_status_id', form.value.package_status_id ?? '');

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

    <section class="rounded-xl border border-white/10 bg-white/[0.03] p-3">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[10px] uppercase tracking-[0.3em] text-amber-200">Package Details</p>
                <h3 class="mt-1 text-sm font-semibold italic">{{ packageRecord.name }}</h3>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="packageRecord.status === 'active' ? 'bg-emerald-400/15 text-emerald-200' : packageRecord.status === 'inactive' ? 'bg-stone-700/60 text-stone-300' : 'bg-amber-300/15 text-amber-200'">
                    {{ packageRecord.status_label }}
                </span>
                <button
                    type="button"
                    class="rounded-lg border border-amber-300/30 px-3 py-1.5 text-sm font-semibold text-amber-100 transition hover:bg-amber-300/10"
                    @click="beginEditing"
                >
                    Edit package
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-rose-400/30 px-3 py-1.5 text-sm font-semibold text-rose-100 transition hover:bg-rose-400/10 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="removePackage"
                >
                    Delete package
                </button>
                <a :href="data.routes.packages" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-white/5">
                    Back to list
                </a>
            </div>
        </div>

        <form ref="deleteForm" :action="packageRecord.delete_url" method="post" class="hidden">
            <input type="hidden" name="_token" :value="data.csrfToken">
            <input type="hidden" name="_method" value="DELETE">
        </form>

        <div v-if="packageRecord.photo_url" class="mb-3 overflow-hidden rounded-xl border border-white/10 bg-slate-950/60 p-2">
            <img :src="packageRecord.photo_url" :alt="packageRecord.name" class="h-40 w-full rounded-lg object-contain">
        </div>

        <div class="mb-3 grid gap-2 sm:grid-cols-2">
            <div class="rounded-lg border border-white/10 bg-slate-950/50 px-3 py-2">
                <p class="text-[10px] uppercase tracking-[0.25em] text-stone-500">Base Price</p>
                <p class="mt-1 text-base font-semibold">${{ packageRecord.base_price }}</p>
            </div>
            <div class="rounded-lg border border-white/10 bg-slate-950/50 px-3 py-2">
                <p class="text-[10px] uppercase tracking-[0.25em] text-stone-500">Created</p>
                <p class="mt-1 text-base font-semibold">{{ packageRecord.created_at }}</p>
            </div>
        </div>

        <div class="mb-3 overflow-hidden rounded-lg border border-white/10 bg-slate-950/50">
            <p class="border-b border-white/10 px-3 py-2 text-[10px] uppercase tracking-[0.25em] text-stone-500">Hourly Pricing</p>
            <div v-if="packageRecord.hourly_prices?.length" class="grid gap-px bg-white/10 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="(entry, index) in packageRecord.hourly_prices" :key="`${entry.hours}-${index}`" class="flex items-center justify-between gap-3 bg-slate-950/70 px-3 py-2">
                    <p class="text-sm font-semibold text-white">{{ entry.hours_label }}</p>
                    <p class="text-sm font-semibold text-amber-100">${{ entry.price }}</p>
                </div>
            </div>
            <p v-else class="px-3 py-2 text-sm text-stone-500">No hour-based pricing added yet. Base price is used for all durations.</p>
        </div>

        <div class="mb-3 rounded-lg border border-white/10 bg-slate-950/50 px-3 py-2">
            <p class="text-[10px] uppercase tracking-[0.25em] text-stone-500">Description</p>
            <p class="mt-1 text-sm leading-5 text-stone-300">{{ packageRecord.description || 'No description added yet.' }}</p>
        </div>

        <div class="mb-3 overflow-hidden rounded-lg border border-white/10 bg-slate-950/50">
            <div class="flex items-center justify-between gap-3 border-b border-white/10 px-3 py-2">
                <p class="text-[10px] uppercase tracking-[0.25em] text-stone-500">Assigned Items</p>
                <span class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] text-stone-300">{{ assignedItems.length }}</span>
            </div>
            <div v-if="assignedItems.length" class="overflow-x-auto">
                <div class="min-w-[760px]">
                    <div class="grid grid-cols-[8rem_minmax(0,1fr)_11rem_7rem_7rem_minmax(0,1.3fr)] gap-3 border-b border-white/10 px-3 py-1.5 text-[10px] uppercase tracking-[0.2em] text-stone-500">
                        <span>Type</span>
                        <span>Name</span>
                        <span>Code / Category</span>
                        <span>Duration</span>
                        <span>Price</span>
                        <span>Description</span>
                    </div>
                    <div
                        v-for="item in assignedItems"
                        :key="item.key"
                        class="grid grid-cols-[8rem_minmax(0,1fr)_11rem_7rem_7rem_minmax(0,1.3fr)] items-center gap-3 border-b border-white/10 px-3 py-2 last:border-b-0"
                    >
                        <span class="inline-flex w-fit rounded-full px-2.5 py-1 text-[11px] font-medium" :class="item.type === 'Equipment' ? 'bg-amber-300/15 text-amber-100' : 'bg-rose-300/15 text-rose-100'">
                            {{ item.type }}
                        </span>
                        <p class="truncate text-sm font-medium text-white">{{ item.name }}</p>
                        <p class="truncate text-sm text-stone-300">{{ item.code }}</p>
                        <p class="text-sm text-stone-300">{{ item.duration }}</p>
                        <p class="text-sm font-semibold text-amber-100">{{ item.price }}</p>
                        <p class="truncate text-sm text-stone-400">{{ item.description }}</p>
                    </div>
                </div>
            </div>
            <p v-else class="px-3 py-4 text-sm text-stone-500">No equipment or add-ons assigned yet.</p>
        </div>

        <transition name="modal">
            <div v-if="isEditing" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm" @click.self="cancelEditing">
                <section class="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-2xl border border-amber-300/20 bg-[#132035] shadow-2xl shadow-black/30">
                    <div class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-white/10 bg-[#132035] px-5 py-4 shadow-lg shadow-black/10">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.3em] text-amber-200">Edit Package</p>
                            <h4 class="mt-1 text-sm font-semibold italic">Update details</h4>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5"
                            @click="cancelEditing"
                        >
                            Close
                        </button>
                    </div>

                    <form class="space-y-4 p-5" novalidate @submit.prevent="updatePackage">
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
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Package Status</label>
                        <select v-model="form.package_status_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-amber-300/50">
                            <option v-for="status in packageStatusOptions" :key="status.id" :value="String(status.id)">{{ status.label }}</option>
                        </select>
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
                                <span>{{ addOn.name }}<span class="text-stone-500"> {{ addOn.product_code ? `· ${addOn.product_code}` : '' }}{{ addOn.addon_category ? ` · ${addOn.addon_category}` : '' }}{{ addOn.duration ? ` · ${addOn.duration}` : '' }}</span></span>
                            </label>
                        </div>
                        <p v-if="!data.addOnOptions?.length" class="text-sm text-stone-500">Add add-on records first to assign them here.</p>
                    </div>
                </div>
                <div class="sticky bottom-0 z-20 -mx-5 -mb-5 flex justify-end gap-3 border-t border-white/10 bg-[#132035] px-5 py-4 shadow-[0_-10px_24px_-18px_rgba(0,0,0,0.7)]">
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
            </div>
        </transition>
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
