<script setup>
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { emitAdminToast, useWorkspaceCrud } from '../useWorkspaceCrud';
import { firstError, hasFieldErrors, isBlank, mergeFieldErrors, requiredMessage, validEmail } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const vendorRecord = ref(props.data.vendor);
const editing = ref(false);
const deleting = ref(false);
const servicesDropdownOpen = ref(false);
const servicesDropdownRef = ref(null);
const clientErrors = ref({});
const showDeleteConfirm = ref(false);
const serviceOfferingOptions = computed(() => props.data.serviceOfferingOptions ?? []);
const { saving, fieldErrors, submitForm } = useWorkspaceCrud();

const form = ref({
    name: props.data.vendor?.name ?? '',
    company_name: props.data.vendor?.company_name ?? '',
    address: props.data.vendor?.address ?? '',
    mobile_number: props.data.vendor?.mobile_number ?? '',
    email: props.data.vendor?.email ?? '',
    is_active: Boolean(props.data.vendor?.is_active),
    services_offered: Array.isArray(props.data.vendor?.services_offered) ? [...props.data.vendor.services_offered] : [],
});

const validationErrors = computed(() => mergeFieldErrors(clientErrors.value, fieldErrors.value));

const selectedServicesLabel = computed(() => {
    if (!form.value.services_offered?.length) {
        return 'Select services offered';
    }

    return form.value.services_offered.join(', ');
});

const resetFormFromRecord = () => {
    form.value = {
        name: vendorRecord.value?.name ?? '',
        company_name: vendorRecord.value?.company_name ?? '',
        address: vendorRecord.value?.address ?? '',
        mobile_number: vendorRecord.value?.mobile_number ?? '',
        email: vendorRecord.value?.email ?? '',
        is_active: Boolean(vendorRecord.value?.is_active),
        services_offered: Array.isArray(vendorRecord.value?.services_offered) ? [...vendorRecord.value.services_offered] : [],
    };
    servicesDropdownOpen.value = false;
    clientErrors.value = {};
};

const toggleServiceSelection = (serviceName) => {
    const selected = new Set(form.value.services_offered ?? []);

    if (selected.has(serviceName)) {
        selected.delete(serviceName);
    } else {
        selected.add(serviceName);
    }

    form.value.services_offered = serviceOfferingOptions.value
        .map((option) => option.name)
        .filter((name) => selected.has(name));
};

const handleClickOutsideServices = (event) => {
    if (!servicesDropdownOpen.value || !servicesDropdownRef.value) {
        return;
    }

    if (!servicesDropdownRef.value.contains(event.target)) {
        servicesDropdownOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('pointerdown', handleClickOutsideServices);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handleClickOutsideServices);
});

const validateVendorForm = () => {
    const errors = {};

    if (isBlank(form.value.name)) {
        errors.name = requiredMessage('Contact name');
    }

    if (!form.value.services_offered?.length) {
        errors.services_offered = 'Add at least one service offered.';
    }

    if (!isBlank(form.value.email) && !validEmail(form.value.email)) {
        errors.email = 'Enter a valid vendor email.';
    }

    clientErrors.value = errors;

    return !hasFieldErrors(errors);
};

const updateVendor = async () => {
    if (!validateVendorForm()) {
        return;
    }

    try {
        const record = await submitForm({
            url: vendorRecord.value.update_url,
            method: 'put',
            data: { ...form.value },
        });

        vendorRecord.value = record;
        resetFormFromRecord();
        editing.value = false;
        window.history.replaceState({}, '', record.show_url);
    } catch {}
};

const deleteVendor = async () => {
    deleting.value = true;

    try {
        await axios.delete(vendorRecord.value.delete_url, {
            headers: {
                Accept: 'application/json',
            },
        });

        window.location.href = props.data.routes.vendors;
    } catch {
        emitAdminToast({
            type: 'error',
            errors: ['Unable to delete this vendor right now.'],
        });
    } finally {
        deleting.value = false;
    }
};

const askDeleteVendor = () => {
    showDeleteConfirm.value = true;
};

const cancelDeleteVendor = () => {
    showDeleteConfirm.value = false;
};

const toggleEdit = () => {
    editing.value = true;
    resetFormFromRecord();
};

const cancelEdit = () => {
    editing.value = false;
    resetFormFromRecord();
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-cyan-200">Vendors Workspace</p>
        <h2 class="text-sm font-bold italic text-white">{{ vendorRecord.company_name || vendorRecord.name }}</h2>
        <p class="text-sm text-stone-300">
            Review the vendor details, update services offered, and keep supplier contact information tidy.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-cyan-200">Vendor Details</p>
                <h3 class="mt-1 text-sm font-semibold italic">{{ vendorRecord.company_name || vendorRecord.name }}</h3>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a :href="data.routes.vendors" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">
                    Back to list
                </a>
                <button
                    v-if="!editing"
                    type="button"
                    class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5"
                    @click="toggleEdit"
                >
                    Edit vendor
                </button>
                <button type="button" class="rounded-xl border border-rose-400/30 px-4 py-2 text-sm font-semibold text-rose-100 transition hover:bg-rose-400/10 disabled:cursor-not-allowed disabled:opacity-60" :disabled="deleting" @click="askDeleteVendor">
                    {{ deleting ? 'Deleting...' : 'Delete vendor' }}
                </button>
            </div>
        </div>

        <div v-if="!editing" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl border border-white/10 bg-slate-950/50 px-3 py-2.5">
                <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Contact Name</p>
                <p class="mt-1 text-sm text-white">{{ vendorRecord.name || 'Not set' }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/50 px-3 py-2.5">
                <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Company Name</p>
                <p class="mt-1 text-sm text-white">{{ vendorRecord.company_name || 'Not set' }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/50 px-3 py-2.5">
                <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Active</p>
                <p class="mt-1 text-sm text-white">{{ vendorRecord.is_active ? 'Yes' : 'No' }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/50 px-3 py-2.5">
                <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Mobile #</p>
                <p class="mt-1 text-sm text-white">{{ vendorRecord.mobile_number || 'Not set' }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/50 px-3 py-2.5 md:col-span-2">
                <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Email Address</p>
                <p class="mt-1 text-sm text-white">{{ vendorRecord.email || 'Not set' }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/50 px-3 py-2.5 md:col-span-2 xl:col-span-3">
                <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Address</p>
                <p class="mt-1 text-sm text-white">{{ vendorRecord.address || 'Not set' }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/50 px-3 py-2.5 md:col-span-2 xl:col-span-3">
                <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Services Offered</p>
                <p class="mt-1 text-sm text-white">{{ vendorRecord.services_offered_label || 'No services selected' }}</p>
            </div>
        </div>

        <form v-else class="space-y-4" novalidate @submit.prevent="updateVendor">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Contact Name</label>
                    <input v-model="form.name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(validationErrors, 'name') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'name') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Company Name</label>
                    <input v-model="form.company_name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50">
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Address</label>
                    <input v-model="form.address" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Mobile #</label>
                    <input v-model="form.mobile_number" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Email Address</label>
                    <input v-model="form.email" type="email" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(validationErrors, 'email') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'email')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'email') }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Services Offered</label>
                    <div ref="servicesDropdownRef" class="relative">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-xl border bg-slate-950/70 px-3 py-2.5 text-left text-sm text-white outline-none transition focus:border-cyan-300/50"
                            :class="firstError(validationErrors, 'services_offered') ? 'border-rose-300/60' : 'border-white/10'"
                            @click="servicesDropdownOpen = !servicesDropdownOpen"
                        >
                            <span class="truncate pr-3" :class="form.services_offered.length ? 'text-white' : 'text-stone-400'">{{ selectedServicesLabel }}</span>
                            <span class="text-xs text-stone-400">{{ servicesDropdownOpen ? '▲' : '▼' }}</span>
                        </button>
                        <div v-if="servicesDropdownOpen" class="mt-2 rounded-xl border border-white/10 bg-slate-950 p-3 shadow-xl shadow-black/20">
                            <div v-if="serviceOfferingOptions.length" class="max-h-56 space-y-2 overflow-auto">
                                <label
                                    v-for="option in serviceOfferingOptions"
                                    :key="option.id"
                                    class="flex items-center gap-3 rounded-lg border border-white/5 px-2.5 py-2 text-sm text-stone-200 transition hover:bg-white/5"
                                >
                                    <input
                                        :checked="form.services_offered.includes(option.name)"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-white/20 bg-slate-950 text-cyan-300"
                                        @change="toggleServiceSelection(option.name)"
                                    >
                                    <span>{{ option.name }}</span>
                                </label>
                            </div>
                            <p v-else class="text-xs text-stone-400">Add service options in Settings &gt; Maintenance first.</p>
                        </div>
                    </div>
                    <p v-if="firstError(validationErrors, 'services_offered')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'services_offered') }}</p>
                    <p v-else class="mt-1 text-xs text-stone-500">Pick one or more saved service options. These are stored as JSON.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Active</label>
                    <label class="flex min-h-[42px] items-center gap-3 rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-stone-200">
                        <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-slate-950 text-cyan-300">
                        <span>Vendor is active</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/5" @click="cancelEdit">
                    Cancel
                </button>
                <button type="submit" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-cyan-300/40 hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Save vendor' }}
                </button>
            </div>
        </form>
    </section>

    <ConfirmDialog
        :open="showDeleteConfirm"
        title="Delete vendor?"
        :message="`Are you sure you want to delete the record ${vendorRecord.company_name || vendorRecord.name}?`"
        confirm-label="Delete vendor"
        :loading="deleting"
        @cancel="cancelDeleteVendor"
        @confirm="deleteVendor"
    />
</template>
