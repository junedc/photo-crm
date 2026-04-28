<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';
import { firstError, hasFieldErrors, isBlank, mergeFieldErrors, requiredMessage, validEmail } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const { saving, fieldErrors, submitForm } = useWorkspaceCrud();
const servicesDropdownOpen = ref(false);
const servicesDropdownRef = ref(null);
const clientErrors = ref({});
const serviceOfferingOptions = computed(() => props.data.serviceOfferingOptions ?? []);

const form = ref({
    name: '',
    company_name: '',
    address: '',
    mobile_number: '',
    email: '',
    is_active: true,
    services_offered: [],
});

const validationErrors = computed(() => mergeFieldErrors(clientErrors.value, fieldErrors.value));

const selectedServicesLabel = computed(() => {
    if (!form.value.services_offered?.length) {
        return 'Select services offered';
    }

    return form.value.services_offered.join(', ');
});

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

const createVendor = async () => {
    if (!validateVendorForm()) {
        return;
    }

    try {
        const record = await submitForm({
            url: props.data.routes.store,
            data: { ...form.value },
        });

        window.location.href = record.show_url;
    } catch {}
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-cyan-200">Vendors Workspace</p>
        <h2 class="text-sm font-bold italic text-white">Create vendor</h2>
        <p class="text-sm text-stone-300">
            Add a new vendor record with contact information and the services they offer to your workspace.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-cyan-200">New Vendor</p>
                <h3 class="mt-1 text-sm font-semibold italic">Create record</h3>
            </div>
            <a :href="data.routes.vendors" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">
                Back to list
            </a>
        </div>

        <form class="space-y-4" novalidate @submit.prevent="createVendor">
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
            <div class="flex justify-end">
                <button type="submit" class="rounded-xl bg-cyan-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Create vendor' }}
                </button>
            </div>
        </form>
    </section>
</template>
