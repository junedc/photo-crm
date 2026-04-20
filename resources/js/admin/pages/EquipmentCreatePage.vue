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
    category: '',
    serial_number: '',
    description: '',
    daily_rate: '',
    maintenance_status: props.data.maintenanceStatuses?.[0] ?? 'ready',
    last_maintained_at: '',
    maintenance_notes: '',
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

const statusLabel = (status) => status.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
const openDatePicker = (event) => {
    try {
        event.target?.showPicker?.();
    } catch {
        // Fall back to the browser's native date input behavior.
    }
};

const createEquipment = async () => {
    if (!validateEquipmentForm()) {
        return;
    }

    const formData = new FormData();

    ['name', 'category', 'serial_number', 'description', 'daily_rate', 'maintenance_status', 'last_maintained_at', 'maintenance_notes'].forEach((key) => {
        formData.append(key, form.value[key] ?? '');
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
        <p class="text-[11px] uppercase tracking-[0.35em] text-cyan-200">Equipment Workspace</p>
        <h2 class="text-sm font-bold italic text-white">Create photobooth equipment</h2>
        <p class="text-sm text-stone-300">
            Add a new equipment record on its own page, then you will be taken back to its detail view.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-cyan-200">New Equipment</p>
                <h3 class="mt-2 text-lg font-semibold">Create record</h3>
            </div>
            <a :href="data.routes.equipment" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">
                Back to list
            </a>
        </div>

        <form class="space-y-4" novalidate @submit.prevent="createEquipment">
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
                    <select v-model="form.maintenance_status" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50">
                        <option v-for="status in data.maintenanceStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
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
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Photo</label>
                    <input ref="createPhotoInput" type="file" accept="image/*" class="block w-full rounded-xl border border-dashed border-white/15 bg-slate-950/70 px-3 py-2.5 text-sm text-stone-300 file:mr-3 file:rounded-lg file:border-0 file:bg-cyan-300 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-stone-950">
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-xl bg-cyan-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Create equipment' }}
                </button>
            </div>
        </form>
    </section>
</template>
