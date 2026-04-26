<script setup>
import { computed, ref, watch } from 'vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';
import { firstError, hasFieldErrors, isBlank, mergeFieldErrors, requiredMessage, validEmail } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const vendorList = ref([...(props.data.vendors ?? [])]);
const vendors = computed(() => vendorList.value);
const search = ref('');
const requestId = ref(0);
const loading = ref(false);
const {
    saving,
    fieldErrors,
    submitForm,
    deleteRecord,
} = useWorkspaceCrud();
const clientErrors = ref({});
const editingVendorId = ref(null);
const form = ref({
    name: '',
    company_name: '',
    address: '',
    mobile_number: '',
    email: '',
    is_active: true,
    services_offered_text: '',
});

const validationErrors = computed(() => mergeFieldErrors(clientErrors.value, fieldErrors.value));

const startVendorCreate = () => {
    editingVendorId.value = null;
    clientErrors.value = {};
    form.value = {
        name: '',
        company_name: '',
        address: '',
        mobile_number: '',
        email: '',
        is_active: true,
        services_offered_text: '',
    };
};

const startVendorEdit = (vendor) => {
    editingVendorId.value = vendor.id;
    clientErrors.value = {};
    form.value = {
        name: vendor.name ?? '',
        company_name: vendor.company_name ?? '',
        address: vendor.address ?? '',
        mobile_number: vendor.mobile_number ?? '',
        email: vendor.email ?? '',
        is_active: Boolean(vendor.is_active),
        services_offered_text: Array.isArray(vendor.services_offered) ? vendor.services_offered.join(', ') : '',
    };
};

const fetchVendors = async () => {
    const currentRequest = ++requestId.value;
    loading.value = true;

    try {
        const params = new URLSearchParams({
            search: search.value.trim(),
        });

        const response = await fetch(`${props.data.routes.vendors}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok || currentRequest !== requestId.value) {
            return;
        }

        const payload = await response.json();
        vendorList.value = [...(payload.records ?? [])];
    } finally {
        if (currentRequest === requestId.value) {
            loading.value = false;
        }
    }
};

watch(search, () => {
    fetchVendors();
});

const saveVendor = async () => {
    const errors = {};

    if (isBlank(form.value.name)) {
        errors.name = requiredMessage('Contact name');
    }

    if (isBlank(form.value.services_offered_text)) {
        errors.services_offered = 'Add at least one service offered.';
    }

    if (!isBlank(form.value.email) && !validEmail(form.value.email)) {
        errors.email = 'Enter a valid vendor email.';
    }

    clientErrors.value = errors;

    if (hasFieldErrors(errors)) {
        return;
    }

    const editingVendor = vendors.value.find((entry) => entry.id === editingVendorId.value);

    try {
        const record = await submitForm({
            url: editingVendor?.update_url ?? props.data.routes.store,
            method: editingVendor ? 'put' : 'post',
            data: {
                name: form.value.name,
                company_name: form.value.company_name,
                address: form.value.address,
                mobile_number: form.value.mobile_number,
                email: form.value.email,
                is_active: form.value.is_active,
                services_offered: String(form.value.services_offered_text ?? '')
                    .split(',')
                    .map((value) => value.trim())
                    .filter(Boolean),
            },
        });

        const index = vendorList.value.findIndex((entry) => entry.id === record.id);
        vendorList.value = index >= 0
            ? vendorList.value.map((entry) => entry.id === record.id ? record : entry)
            : [...vendorList.value, record].sort((left, right) => {
                const companyCompare = String(left.company_name ?? '').localeCompare(String(right.company_name ?? ''));

                return companyCompare !== 0 ? companyCompare : String(left.name ?? '').localeCompare(String(right.name ?? ''));
            });

        startVendorCreate();
    } catch {}
};

const removeVendor = async (vendor) => {
    if (!vendor?.delete_url) {
        return;
    }

    try {
        await deleteRecord({ url: vendor.delete_url });
        vendorList.value = vendorList.value.filter((entry) => entry.id !== vendor.id);

        if (editingVendorId.value === vendor.id) {
            startVendorCreate();
        }
    } catch {}
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-cyan-200">Vendors Workspace</p>
        <h2 class="text-sm font-bold italic text-white">Manage workspace vendors</h2>
        <p class="text-sm text-stone-300">
            Maintain supplier and collaborator records for your workspace in one dedicated page.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-cyan-200">Vendors</p>
                <h3 class="mt-1 text-sm font-semibold italic">Create and maintain records</h3>
            </div>
            <span class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 text-xs text-stone-300">{{ vendors.length }}</span>
        </div>

        <div class="grid gap-4 xl:grid-cols-[360px_minmax(0,1fr)]">
            <section class="rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                <p class="text-[11px] uppercase tracking-[0.25em] text-stone-500">{{ editingVendorId ? 'Edit Vendor' : 'New Vendor' }}</p>

                <div class="mt-4 grid gap-3">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Contact Name</label>
                        <input v-model="form.name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(validationErrors, 'name') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(validationErrors, 'name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'name') }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Company Name</label>
                        <input v-model="form.company_name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50">
                    </div>
                    <div>
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
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Services Offered</label>
                        <input v-model="form.services_offered_text" type="text" placeholder="Photographer, DJ, Stylist" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(validationErrors, 'services_offered') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(validationErrors, 'services_offered')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'services_offered') }}</p>
                        <p v-else class="mt-1 text-xs text-stone-500">Separate multiple services with commas. These are stored as JSON.</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Active</label>
                        <label class="flex min-h-[42px] items-center gap-3 rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-stone-200">
                            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-slate-950 text-cyan-300">
                            <span>Vendor is active</span>
                        </label>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="button" class="rounded-xl bg-cyan-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving" @click="saveVendor">
                            {{ saving ? 'Saving...' : editingVendorId ? 'Save vendor' : 'Add vendor' }}
                        </button>
                        <button v-if="editingVendorId" type="button" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/5" @click="startVendorCreate">
                            Cancel
                        </button>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.25em] text-stone-500">Vendor List</p>
                        <p class="mt-1 text-xs text-stone-400">Contacts and services available for booking tasks and coordination.</p>
                    </div>
                    <input v-model="search" type="text" placeholder="Search vendors" class="w-full max-w-xs rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-cyan-300/50">
                </div>

                <div class="mt-4 overflow-hidden rounded-2xl border border-white/10">
                    <div class="grid grid-cols-[4rem_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,1fr)_10rem_minmax(0,1fr)_8rem_7rem_7rem] gap-3 bg-white/[0.03] px-3 py-2 text-[11px] uppercase tracking-[0.2em] text-stone-500">
                        <span>ID</span>
                        <span>Contact</span>
                        <span>Company</span>
                        <span>Services</span>
                        <span>Address</span>
                        <span>Mobile</span>
                        <span>Email</span>
                        <span>Active</span>
                        <span>Edit</span>
                        <span>Delete</span>
                    </div>
                    <div v-for="vendor in vendors" :key="vendor.id" class="grid grid-cols-[4rem_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,1fr)_10rem_minmax(0,1fr)_8rem_7rem_7rem] items-center gap-3 border-t border-white/10 px-3 py-2.5">
                        <p class="truncate text-sm text-stone-300">{{ vendor.id }}</p>
                        <p class="truncate text-sm text-white">{{ vendor.name }}</p>
                        <p class="truncate text-sm text-stone-300">{{ vendor.company_name || 'No company' }}</p>
                        <p class="truncate text-sm text-stone-300">{{ vendor.services_offered_label || 'No services' }}</p>
                        <p class="truncate text-sm text-stone-300">{{ vendor.address || 'No address' }}</p>
                        <p class="truncate text-sm text-stone-300">{{ vendor.mobile_number || 'No mobile' }}</p>
                        <p class="truncate text-sm text-stone-300">{{ vendor.email || 'No email' }}</p>
                        <span class="inline-flex h-7 w-fit items-center rounded-full border px-2.5 text-[11px] font-medium" :class="vendor.is_active ? 'border-emerald-300/20 bg-emerald-300/10 text-emerald-100' : 'border-white/10 bg-white/5 text-stone-300'">
                            {{ vendor.is_active ? 'Active' : 'Inactive' }}
                        </span>
                        <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/5" @click="startVendorEdit(vendor)">Edit</button>
                        <button type="button" class="rounded-lg border border-rose-400/30 px-3 py-1.5 text-xs font-semibold text-rose-100 transition hover:bg-rose-400/10" @click="removeVendor(vendor)">Delete</button>
                    </div>
                    <div v-if="loading" class="border-t border-white/10 px-3 py-3 text-sm text-stone-400">
                        Loading vendors...
                    </div>
                    <div v-else-if="!vendors.length" class="border-t border-white/10 px-3 py-3 text-sm text-stone-400">
                        No vendors added yet.
                    </div>
                </div>
            </section>
        </div>
    </section>
</template>
