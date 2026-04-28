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
const equipmentRecord = ref(props.data.equipmentRecord);
const clientErrors = ref({});
const showDeleteConfirm = ref(false);
const statusLabel = (status) => status.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
const fallbackStatusOptions = computed(() => (props.data.maintenanceStatuses ?? []).map((status) => ({
    id: status,
    label: statusLabel(status),
})));
const maintenanceStatusOptions = computed(() => props.data.maintenanceStatusOptions?.length
    ? props.data.maintenanceStatusOptions
    : fallbackStatusOptions.value);
const form = ref({
    name: props.data.equipmentRecord?.name ?? '',
    category: props.data.equipmentRecord?.category ?? '',
    serial_number: props.data.equipmentRecord?.serial_number ?? '',
    description: props.data.equipmentRecord?.description ?? '',
    daily_rate: props.data.equipmentRecord?.daily_rate ?? '',
    maintenance_status_id: props.data.equipmentRecord?.maintenance_status_id ? String(props.data.equipmentRecord.maintenance_status_id) : String(maintenanceStatusOptions.value?.[0]?.id ?? ''),
    last_maintained_at: props.data.equipmentRecord?.last_maintained_at ?? '',
    maintenance_notes: props.data.equipmentRecord?.maintenance_notes ?? '',
});
const validationErrors = computed(() => mergeFieldErrors(clientErrors.value, fieldErrors.value));

const validateEquipmentForm = () => {
    const errors = {};

    if (isBlank(form.value.name)) {
        errors.name = requiredMessage('Name');
    }

    if (isBlank(form.value.daily_rate)) {
        errors.daily_rate = requiredMessage('Daily rate');
    } else if (Number(form.value.daily_rate) < 0) {
        errors.daily_rate = 'Daily rate must be zero or greater.';
    }

    clientErrors.value = errors;

    return !hasFieldErrors(errors);
};

const openDatePicker = (event) => {
    try {
        event.target?.showPicker?.();
    } catch {
        // Fall back to the browser's native date input behavior.
    }
};

const updateEquipment = async () => {
    if (!validateEquipmentForm()) {
        return;
    }

    const formData = new FormData();
    formData.append('_method', 'PUT');

    ['name', 'category', 'serial_number', 'description', 'daily_rate', 'maintenance_status_id', 'last_maintained_at', 'maintenance_notes'].forEach((key) => {
        formData.append(key, form.value[key] ?? '');
    });

    const file = editPhotoInput.value?.files?.[0];
    if (file) {
        formData.append('photo', file);
    }

    try {
        const record = await submitForm({
            url: equipmentRecord.value.update_url,
            method: 'post',
            data: formData,
        });

        equipmentRecord.value = record;
        form.value = {
            name: record.name ?? '',
            category: record.category ?? '',
            serial_number: record.serial_number ?? '',
            description: record.description ?? '',
            daily_rate: record.daily_rate ?? '',
            maintenance_status_id: record.maintenance_status_id ? String(record.maintenance_status_id) : String(maintenanceStatusOptions.value?.[0]?.id ?? ''),
            last_maintained_at: record.last_maintained_at ?? '',
            maintenance_notes: record.maintenance_notes ?? '',
        };
        clientErrors.value = {};

        window.history.replaceState({}, '', record.show_url);

        if (editPhotoInput.value) {
            editPhotoInput.value.value = '';
        }
    } catch {}
};

const removeEquipment = async () => {
    if (!equipmentRecord.value?.delete_url) {
        return;
    }

    showDeleteConfirm.value = true;
};

const confirmRemoveEquipment = () => {
    deleteForm.value?.submit();
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-cyan-200">Equipment Workspace</p>
        <h2 class="text-sm font-bold italic text-white">{{ equipmentRecord.name }}</h2>
        <p class="text-sm text-stone-300">
            Review and update the full equipment record on its own page.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-cyan-200">Equipment Details</p>
                <h3 class="mt-1 text-sm font-semibold italic">{{ equipmentRecord.name }}</h3>
            </div>
            <div class="flex items-center gap-3">
                <span class="rounded-full px-3 py-1 text-xs font-medium" :class="equipmentRecord.maintenance_status === 'ready' ? 'bg-emerald-400/15 text-emerald-200' : equipmentRecord.maintenance_status === 'maintenance' ? 'bg-amber-300/15 text-amber-200' : 'bg-rose-400/15 text-rose-200'">
                    {{ equipmentRecord.maintenance_status_label ?? statusLabel(equipmentRecord.maintenance_status) }}
                </span>
                <button
                    type="button"
                    class="rounded-xl border border-rose-400/30 px-4 py-2 text-sm font-semibold text-rose-100 transition hover:bg-rose-400/10 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="removeEquipment"
                >
                    Delete equipment
                </button>
                <a :href="data.routes.equipment" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">
                    Back to list
                </a>
            </div>
        </div>

        <form ref="deleteForm" :action="equipmentRecord.delete_url" method="post" class="hidden">
            <input type="hidden" name="_token" :value="data.csrfToken">
            <input type="hidden" name="_method" value="DELETE">
        </form>

        <img v-if="equipmentRecord.photo_url" :src="equipmentRecord.photo_url" :alt="equipmentRecord.name" class="mb-4 h-48 w-full rounded-2xl object-cover">

        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Daily Rate</p>
                <p class="mt-2 text-xl font-semibold">${{ equipmentRecord.daily_rate }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Category</p>
                <p class="mt-2 text-base font-semibold">{{ equipmentRecord.category || 'Uncategorized' }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Assigned Package</p>
                <p class="mt-2 text-base font-semibold">{{ equipmentRecord.package_name || 'Unassigned' }}</p>
            </div>
        </div>

        <div class="mb-4 rounded-xl border border-white/10 bg-slate-950/50 p-3">
            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Notes</p>
            <p class="mt-3 text-sm leading-6 text-stone-300">{{ equipmentRecord.description || 'No description added yet.' }}</p>
            <p class="mt-3 text-sm leading-6 text-stone-300">{{ equipmentRecord.maintenance_notes || 'No maintenance notes added yet.' }}</p>
        </div>

        <form class="space-y-4" novalidate @submit.prevent="updateEquipment">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Name</label>
                    <input v-model="form.name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(validationErrors, 'name') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'name') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Category</label>
                    <input v-model="form.category" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Serial Number</label>
                    <input v-model="form.serial_number" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Daily Rate</label>
                    <input v-model="form.daily_rate" type="number" min="0" step="0.01" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(validationErrors, 'daily_rate') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'daily_rate')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'daily_rate') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Maintenance Status</label>
                    <select v-model="form.maintenance_status_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50">
                        <option v-for="status in maintenanceStatusOptions" :key="status.id" :value="String(status.id)">{{ status.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Last Maintained</label>
                    <input v-model="form.last_maintained_at" type="date" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50" @click="openDatePicker" @keydown.prevent>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Description</label>
                    <textarea v-model="form.description" rows="4" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50" />
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Maintenance Notes</label>
                    <textarea v-model="form.maintenance_notes" rows="4" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50" />
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Replace photo</label>
                    <input ref="editPhotoInput" type="file" accept="image/*" class="block w-full rounded-xl border border-dashed border-white/15 bg-slate-950/70 px-3 py-2.5 text-sm text-stone-300 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-xs file:font-semibold file:text-stone-950">
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-cyan-300/40 hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Update equipment' }}
                </button>
            </div>
        </form>
    </section>

    <ConfirmDialog
        :open="showDeleteConfirm"
        title="Delete equipment?"
        :message="`Are you sure you want to delete the record ${equipmentRecord.name}?`"
        confirm-label="Delete equipment"
        @cancel="showDeleteConfirm = false"
        @confirm="confirmRemoveEquipment"
    />
</template>
