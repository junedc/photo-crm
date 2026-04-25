<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';
import { autoAttachGoogleAddressInputs } from '../../googleAddressAutocomplete';
import { firstError, hasFieldErrors, isBlank, mergeFieldErrors, requiredMessage, validEmail } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const activeTab = ref('workspace');
const workspacePhotoInput = ref(null);
const fontUploadInput = ref(null);
const tenantRecord = ref(props.data.tenant ?? {});
const tenantFonts = ref([...(props.data.tenant?.fonts ?? [])]);
const subscriptions = ref(props.data.subscriptions ?? []);
const csrfToken = window.adminProps?.csrfToken ?? '';
const userRecord = ref(props.data.user ?? {});
const workspaceErrors = ref({});
const accountErrors = ref({});
const maintenanceRecords = ref({
    invoice: [...(props.data.maintenance?.invoice ?? [])],
    invoice_installment: [...(props.data.maintenance?.invoice_installment ?? [])],
    task: [...(props.data.maintenance?.task ?? [])],
    booking: [...(props.data.maintenance?.booking ?? [])],
    quote_response: [...(props.data.maintenance?.quote_response ?? [])],
    package: [...(props.data.maintenance?.package ?? [])],
    equipment: [...(props.data.maintenance?.equipment ?? [])],
    inventory_item_category: [...(props.data.maintenance?.inventory_item_category ?? [])],
    campaign: [...(props.data.maintenance?.campaign ?? [])],
    support: [...(props.data.maintenance?.support ?? [])],
    referral: [...(props.data.maintenance?.referral ?? [])],
    email_tracking: [...(props.data.maintenance?.email_tracking ?? [])],
});
const maintenanceDrafts = ref({
    invoice: '',
    invoice_installment: '',
    task: '',
    booking: '',
    quote_response: '',
    package: '',
    equipment: '',
    inventory_item_category: '',
    campaign: '',
    support: '',
    referral: '',
    email_tracking: '',
});
const maintenanceEditing = ref({
    invoice: null,
    invoice_installment: null,
    task: null,
    booking: null,
    quote_response: null,
    package: null,
    equipment: null,
    inventory_item_category: null,
    campaign: null,
    support: null,
    referral: null,
    email_tracking: null,
});
const {
    saving: workspaceSaving,
    fieldErrors: workspaceServerErrors,
    submitForm: submitWorkspaceForm,
} = useWorkspaceCrud();
const {
    saving: accountSaving,
    fieldErrors: accountServerErrors,
    submitForm: submitAccountForm,
} = useWorkspaceCrud();
const {
    saving: fontSaving,
    fieldErrors: fontServerErrors,
    submitForm: submitFontForm,
    deleteRecord: deleteFontRecord,
} = useWorkspaceCrud();
const {
    saving: maintenanceSaving,
    fieldErrors: maintenanceServerErrors,
    submitForm: submitMaintenanceForm,
    deleteRecord: deleteMaintenanceRecord,
} = useWorkspaceCrud();

const workspaceForm = ref({
    name: tenantRecord.value.name ?? '',
    contact_email: tenantRecord.value.contact_email ?? '',
    contact_phone: tenantRecord.value.contact_phone ?? '',
    address: tenantRecord.value.address ?? '',
    theme: tenantRecord.value.theme ?? 'dark',
    timezone: tenantRecord.value.timezone ?? 'UTC',
    subscription_id: tenantRecord.value.subscription_id ?? '',
    invoice_deposit_percentage: tenantRecord.value.invoice_deposit_percentage ?? '30.00',
    travel_free_kilometers: tenantRecord.value.travel_free_kilometers ?? '0.00',
    travel_fee_per_kilometer: tenantRecord.value.travel_fee_per_kilometer ?? '0.00',
    packages_api_key: tenantRecord.value.packages_api_key ?? '',
    stripe_secret: tenantRecord.value.stripe_secret ?? '',
    stripe_webhook_secret: tenantRecord.value.stripe_webhook_secret ?? '',
    stripe_currency: tenantRecord.value.stripe_currency ?? 'aud',
    quote_prefix: tenantRecord.value.quote_prefix ?? 'QT',
    invoice_prefix: tenantRecord.value.invoice_prefix ?? 'INV',
    customer_package_discount_percentage: tenantRecord.value.customer_package_discount_percentage ?? '0.00',
});

const accountForm = ref({
    name: userRecord.value.name ?? '',
    email: userRecord.value.email ?? '',
});

const fontForm = ref({
    family: '',
    weight: '400',
    style: 'normal',
});
const fontErrors = ref({});
const maintenanceSections = computed(() => [
    { key: 'invoice', label: props.data.maintenanceLabels?.invoice ?? 'Invoice Status' },
    { key: 'invoice_installment', label: props.data.maintenanceLabels?.invoice_installment ?? 'Invoice Installment Status' },
    { key: 'task', label: props.data.maintenanceLabels?.task ?? 'Task Status' },
    { key: 'booking', label: props.data.maintenanceLabels?.booking ?? 'Booking Status' },
    { key: 'quote_response', label: props.data.maintenanceLabels?.quote_response ?? 'Quote Response Status' },
    { key: 'package', label: props.data.maintenanceLabels?.package ?? 'Package Status' },
    { key: 'equipment', label: props.data.maintenanceLabels?.equipment ?? 'Equipment Status' },
    { key: 'inventory_item_category', label: props.data.maintenanceLabels?.inventory_item_category ?? 'Inventory Item Category' },
    { key: 'campaign', label: props.data.maintenanceLabels?.campaign ?? 'Campaign Status' },
    { key: 'support', label: props.data.maintenanceLabels?.support ?? 'Support Status' },
    { key: 'referral', label: props.data.maintenanceLabels?.referral ?? 'Referral Status' },
    { key: 'email_tracking', label: props.data.maintenanceLabels?.email_tracking ?? 'Email Tracking Status' },
]);

const workspaceValidationErrors = computed(() => mergeFieldErrors(workspaceErrors.value, workspaceServerErrors.value));
const accountValidationErrors = computed(() => mergeFieldErrors(accountErrors.value, accountServerErrors.value));
const fontValidationErrors = computed(() => mergeFieldErrors(fontErrors.value, fontServerErrors.value));
const maintenanceValidationErrors = computed(() => maintenanceServerErrors.value ?? {});
const prettifyStatus = (value) => (value || '').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
const sectionHasEditableStatuses = (key) => (maintenanceRecords.value[key] ?? []).some((record) => !record.system);
const fontVariantLabel = (font) => {
    if (Number(font?.weight) >= 700 && font?.style === 'italic') {
        return 'Bold Italic';
    }

    if (Number(font?.weight) >= 700) {
        return 'Bold';
    }

    if (font?.style === 'italic') {
        return 'Italic';
    }

    return 'Regular';
};

const saveWorkspace = async () => {
    const errors = {};

    if (isBlank(workspaceForm.value.name)) {
        errors.name = requiredMessage('Business name');
    }

    if (!validEmail(workspaceForm.value.contact_email)) {
        errors.contact_email = 'Enter a valid contact email.';
    }

    if (!isBlank(workspaceForm.value.stripe_currency) && String(workspaceForm.value.stripe_currency).trim().length !== 3) {
        errors.stripe_currency = 'Stripe currency must be a 3-letter code.';
    }

    workspaceErrors.value = errors;

    if (hasFieldErrors(errors)) {
        return;
    }

    const formData = new FormData();
    formData.append('name', workspaceForm.value.name ?? '');
    formData.append('contact_email', workspaceForm.value.contact_email ?? '');
    formData.append('contact_phone', workspaceForm.value.contact_phone ?? '');
    formData.append('address', workspaceForm.value.address ?? '');
    formData.append('theme', workspaceForm.value.theme ?? 'dark');
    formData.append('timezone', workspaceForm.value.timezone ?? 'UTC');
    formData.append('subscription_id', workspaceForm.value.subscription_id ?? '');
    formData.append('invoice_deposit_percentage', workspaceForm.value.invoice_deposit_percentage ?? '');
    formData.append('travel_free_kilometers', workspaceForm.value.travel_free_kilometers ?? '');
    formData.append('travel_fee_per_kilometer', workspaceForm.value.travel_fee_per_kilometer ?? '');
    formData.append('packages_api_key', workspaceForm.value.packages_api_key ?? '');
    formData.append('stripe_secret', workspaceForm.value.stripe_secret ?? '');
    formData.append('stripe_webhook_secret', workspaceForm.value.stripe_webhook_secret ?? '');
    formData.append('stripe_currency', workspaceForm.value.stripe_currency ?? '');
    formData.append('quote_prefix', workspaceForm.value.quote_prefix ?? '');
    formData.append('invoice_prefix', workspaceForm.value.invoice_prefix ?? '');
    formData.append('customer_package_discount_percentage', workspaceForm.value.customer_package_discount_percentage ?? '');

    const file = workspacePhotoInput.value?.files?.[0];
    if (file) {
        formData.append('logo', file);
    }

    try {
        const record = await submitWorkspaceForm({
            url: props.data.routes.workspaceUpdate,
            data: formData,
        });

        tenantRecord.value = record;
        workspaceForm.value = {
            name: record.name ?? '',
            contact_email: record.contact_email ?? '',
            contact_phone: record.contact_phone ?? '',
            address: record.address ?? '',
            theme: record.theme ?? 'dark',
            timezone: record.timezone ?? 'UTC',
            subscription_id: record.subscription_id ?? '',
            invoice_deposit_percentage: record.invoice_deposit_percentage ?? '30.00',
            travel_free_kilometers: record.travel_free_kilometers ?? '0.00',
            travel_fee_per_kilometer: record.travel_fee_per_kilometer ?? '0.00',
            packages_api_key: record.packages_api_key ?? '',
            stripe_secret: record.stripe_secret ?? '',
            stripe_webhook_secret: record.stripe_webhook_secret ?? '',
            stripe_currency: record.stripe_currency ?? 'aud',
            quote_prefix: record.quote_prefix ?? 'QT',
            invoice_prefix: record.invoice_prefix ?? 'INV',
            customer_package_discount_percentage: record.customer_package_discount_percentage ?? '0.00',
        };
        if (workspacePhotoInput.value) {
            workspacePhotoInput.value.value = '';
        }
        tenantFonts.value = [...(record.fonts ?? tenantFonts.value)];
        workspaceErrors.value = {};
    } catch {}
};

const saveAccount = async () => {
    const errors = {};

    if (isBlank(accountForm.value.name)) {
        errors.name = requiredMessage('Admin name');
    }

    if (isBlank(accountForm.value.email)) {
        errors.email = requiredMessage('Admin email');
    } else if (!validEmail(accountForm.value.email)) {
        errors.email = 'Enter a valid admin email.';
    }

    accountErrors.value = errors;

    if (hasFieldErrors(errors)) {
        return;
    }

    try {
        const record = await submitAccountForm({
            url: props.data.routes.accountUpdate,
            data: { ...accountForm.value },
        });

        userRecord.value = record;
        accountForm.value = {
            name: record.name ?? '',
            email: record.email ?? '',
        };
        accountErrors.value = {};
    } catch {}
};

const saveFont = async () => {
    const errors = {};
    const file = fontUploadInput.value?.files?.[0];

    if (isBlank(fontForm.value.family)) {
        errors.family = requiredMessage('Font family');
    }

    if (!file) {
        errors.file = 'Choose a font file.';
    }

    fontErrors.value = errors;

    if (hasFieldErrors(errors)) {
        return;
    }

    const formData = new FormData();
    formData.append('family', fontForm.value.family ?? '');
    formData.append('weight', fontForm.value.weight ?? '400');
    formData.append('style', fontForm.value.style ?? 'normal');
    formData.append('file', file);

    try {
        const record = await submitFontForm({
            url: props.data.routes.fontStore,
            data: formData,
        });

        const index = tenantFonts.value.findIndex((entry) => entry.id === record.id);
        tenantFonts.value = index >= 0
            ? tenantFonts.value.map((entry) => entry.id === record.id ? record : entry)
            : [...tenantFonts.value, record].sort((left, right) => {
                const familyCompare = String(left.family ?? '').localeCompare(String(right.family ?? ''));

                if (familyCompare !== 0) {
                    return familyCompare;
                }

                if (Number(left.weight ?? 400) !== Number(right.weight ?? 400)) {
                    return Number(left.weight ?? 400) - Number(right.weight ?? 400);
                }

                return String(left.style ?? '').localeCompare(String(right.style ?? ''));
            });

        tenantRecord.value = {
            ...tenantRecord.value,
            fonts: tenantFonts.value,
        };
        fontForm.value = {
            family: '',
            weight: '400',
            style: 'normal',
        };
        fontErrors.value = {};

        if (fontUploadInput.value) {
            fontUploadInput.value.value = '';
        }
    } catch {}
};

const removeFont = async (font) => {
    if (!font?.delete_url) {
        return;
    }

    try {
        await deleteFontRecord({ url: font.delete_url });
        tenantFonts.value = tenantFonts.value.filter((entry) => entry.id !== font.id);
        tenantRecord.value = {
            ...tenantRecord.value,
            fonts: tenantFonts.value,
        };
    } catch {}
};

const beginEditStatus = (scope, record) => {
    maintenanceEditing.value[scope] = record.id;
    maintenanceDrafts.value[scope] = record.name ?? '';
};

const cancelEditStatus = (scope) => {
    maintenanceEditing.value[scope] = null;
    maintenanceDrafts.value[scope] = '';
};

const saveStatus = async (scope) => {
    const name = String(maintenanceDrafts.value[scope] ?? '').trim();

    if (name === '') {
        return;
    }

    const editingId = maintenanceEditing.value[scope];
    const isTask = scope === 'task';
    const isInventoryItemCategory = scope === 'inventory_item_category';
    const existing = maintenanceRecords.value[scope].find((entry) => entry.id === editingId);

    try {
        const record = await submitMaintenanceForm({
            url: editingId
                ? existing?.update_url
                : (isInventoryItemCategory
                    ? props.data.routes.inventoryItemCategoryStore
                    : (isTask ? props.data.routes.maintenanceTaskStore : props.data.routes.maintenanceStore)),
            method: editingId ? 'put' : 'post',
            data: editingId ? { name } : (isInventoryItemCategory ? { name } : (isTask ? { name } : { scope, name })),
        });

        const nextRecord = {
            ...record,
            update_url: record.update_url ?? existing?.update_url ?? null,
            delete_url: record.delete_url ?? existing?.delete_url ?? null,
        };
        const index = maintenanceRecords.value[scope].findIndex((entry) => entry.id === nextRecord.id);
        maintenanceRecords.value[scope] = index >= 0
            ? maintenanceRecords.value[scope].map((entry) => (entry.id === nextRecord.id ? nextRecord : entry))
            : [...maintenanceRecords.value[scope], nextRecord].sort((left, right) => left.name.localeCompare(right.name));
        cancelEditStatus(scope);
    } catch {}
};

const removeStatus = async (scope, record) => {
    if (!record.delete_url) {
        return;
    }

    try {
        await deleteMaintenanceRecord({ url: record.delete_url });
        maintenanceRecords.value[scope] = maintenanceRecords.value[scope].filter((entry) => entry.id !== record.id);
        if (maintenanceEditing.value[scope] === record.id) {
            cancelEditStatus(scope);
        }
    } catch {}
};

onMounted(() => {
    nextTick(() => autoAttachGoogleAddressInputs());
});
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-slate-300">Settings</p>
        <h2 class="text-sm font-bold italic text-white">Workspace, account, and maintenance settings</h2>
        <p class="text-sm text-stone-300">
            Manage business setup, admin access, and tenant status lists from one place.
        </p>
    </section>

    <section class="space-y-4">
        <div class="flex items-center gap-2 rounded-2xl border border-white/10 bg-white/[0.03] p-1">
            <button type="button" class="rounded-xl px-4 py-2 text-sm font-semibold transition" :class="activeTab === 'workspace' ? 'bg-slate-200 text-slate-950' : 'text-white hover:bg-white/5'" @click="activeTab = 'workspace'">Workspace</button>
            <button type="button" class="rounded-xl px-4 py-2 text-sm font-semibold transition" :class="activeTab === 'account' ? 'bg-slate-200 text-slate-950' : 'text-white hover:bg-white/5'" @click="activeTab = 'account'">Account</button>
            <button type="button" class="rounded-xl px-4 py-2 text-sm font-semibold transition" :class="activeTab === 'maintenance' ? 'bg-slate-200 text-slate-950' : 'text-white hover:bg-white/5'" @click="activeTab = 'maintenance'">Maintenance</button>
        </div>

        <div v-if="activeTab === 'workspace'" class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.3em] text-slate-300">Workspace</p>
                    <h3 class="mt-1 text-sm font-semibold italic">Workspace settings</h3>
                </div>
            </div>

            <form class="space-y-4" novalidate @submit.prevent="saveWorkspace">
                <div class="rounded-2xl border border-white/10 bg-slate-950/50 p-4">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Current Logo</p>
                    <div class="mt-3 flex items-center gap-4">
                        <img v-if="tenantRecord.logo_url" :src="tenantRecord.logo_url" :alt="tenantRecord.name" class="h-20 w-20 rounded-2xl object-cover">
                        <div v-else class="flex h-20 w-20 items-center justify-center rounded-2xl border border-dashed border-white/15 bg-slate-900 text-2xl font-semibold text-stone-500">
                            {{ (tenantRecord.name || 'M').slice(0, 1) }}
                        </div>
                        <p class="text-sm text-stone-400">Upload a square or landscape logo for your workspace branding.</p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Business Name</label>
                        <input v-model="workspaceForm.name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50" :class="firstError(workspaceValidationErrors, 'name') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(workspaceValidationErrors, 'name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(workspaceValidationErrors, 'name') }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Contact Email</label>
                        <input v-model="workspaceForm.contact_email" type="email" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50" :class="firstError(workspaceValidationErrors, 'contact_email') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(workspaceValidationErrors, 'contact_email')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(workspaceValidationErrors, 'contact_email') }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Contact Phone</label>
                        <input v-model="workspaceForm.contact_phone" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Logo</label>
                        <input ref="workspacePhotoInput" type="file" accept="image/*" class="block w-full rounded-xl border border-dashed border-white/15 bg-slate-950/70 px-3 py-2.5 text-sm text-stone-300 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-200 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-950">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Address</label>
                        <input v-model="workspaceForm.address" type="text" data-google-address="true" autocomplete="street-address" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Theme</label>
                        <select v-model="workspaceForm.theme" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                            <option value="dark">Dark theme</option>
                            <option value="light">Paper light theme</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Timezone</label>
                        <select v-model="workspaceForm.timezone" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50" :class="firstError(workspaceValidationErrors, 'timezone') ? 'border-rose-300/60' : ''">
                            <option v-for="timezone in (props.data.timezoneOptions ?? [])" :key="timezone.value" :value="timezone.value">{{ timezone.label }}</option>
                        </select>
                        <p v-if="firstError(workspaceValidationErrors, 'timezone')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(workspaceValidationErrors, 'timezone') }}</p>
                    </div>
                    <div class="sm:col-span-2 rounded-2xl border border-sky-300/20 bg-sky-300/10 p-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="flex-1">
                                <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-sky-200">Subscription</label>
                                <select v-model="workspaceForm.subscription_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300/50">
                                    <option value="">Choose a subscription</option>
                                    <option v-if="tenantRecord.subscription?.billing_period === 'free_for_life'" :value="tenantRecord.subscription_id" disabled>
                                        Free for life - assigned by platform admin
                                    </option>
                                    <option v-for="subscription in subscriptions" :key="subscription.id" :value="subscription.id">
                                        {{ subscription.name }} - {{ subscription.currency }} {{ subscription.price }} - {{ subscription.validity_label }}
                                    </option>
                                </select>
                            </div>
                            <form v-if="tenantRecord.subscription" method="POST" :action="props.data.routes.subscriptionPay" class="lg:pt-6">
                                <input type="hidden" name="_token" :value="csrfToken">
                                <button type="submit" class="w-full rounded-xl bg-sky-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-sky-200 lg:w-auto">
                                    Pay subscription
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-white/10 bg-slate-950/50 p-4">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Workspace Settings</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Invoice Deposit Percentage</label>
                            <input v-model="workspaceForm.invoice_deposit_percentage" type="number" min="0" max="100" step="0.01" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Free Travel Kilometers</label>
                            <input v-model="workspaceForm.travel_free_kilometers" type="number" min="0" step="0.01" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Travel Fee Per Kilometer</label>
                            <input v-model="workspaceForm.travel_fee_per_kilometer" type="number" min="0" step="0.01" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Customer Package Discount</label>
                            <input v-model="workspaceForm.customer_package_discount_percentage" type="number" min="0" max="100" step="0.01" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Packages API Key</label>
                            <input v-model="workspaceForm.packages_api_key" type="text" autocomplete="off" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Stripe Currency</label>
                            <input v-model="workspaceForm.stripe_currency" type="text" maxlength="3" autocomplete="off" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm uppercase text-white outline-none transition focus:border-slate-300/50" :class="firstError(workspaceValidationErrors, 'stripe_currency') ? 'border-rose-300/60' : ''">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Quote Number Prefix</label>
                            <input v-model="workspaceForm.quote_prefix" type="text" maxlength="20" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Invoice Number Prefix</label>
                            <input v-model="workspaceForm.invoice_prefix" type="text" maxlength="20" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Stripe Secret Key</label>
                            <input v-model="workspaceForm.stripe_secret" type="password" autocomplete="new-password" placeholder="sk_live_..." class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Stripe Webhook Secret</label>
                            <input v-model="workspaceForm.stripe_webhook_secret" type="password" autocomplete="new-password" placeholder="whsec_..." class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                        </div>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="rounded-xl bg-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-60" :disabled="workspaceSaving">
                        {{ workspaceSaving ? 'Saving...' : 'Save workspace settings' }}
                    </button>
                </div>
            </form>

            <section class="rounded-2xl border border-white/10 bg-slate-950/50 p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Brand Fonts</p>
                        <h4 class="mt-1 text-sm font-semibold text-white">Client portal design fonts</h4>
                        <p class="mt-1 text-xs leading-5 text-stone-400">Only tenant admins can upload fonts here. Clients will only see these approved families in the design editor.</p>
                    </div>
                    <span class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 text-xs text-stone-300">{{ tenantFonts.length }}</span>
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_10rem_10rem_minmax(0,1fr)_auto]">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Font Family</label>
                        <input v-model="fontForm.family" type="text" placeholder="MemoShot Serif" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50" :class="firstError(fontValidationErrors, 'family') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(fontValidationErrors, 'family')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(fontValidationErrors, 'family') }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Weight</label>
                        <select v-model="fontForm.weight" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                            <option value="400">Regular</option>
                            <option value="700">Bold</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Style</label>
                        <select v-model="fontForm.style" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                            <option value="normal">Normal</option>
                            <option value="italic">Italic</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Font File</label>
                        <input ref="fontUploadInput" type="file" accept=".woff2,.woff,.ttf,.otf,font/woff2,font/woff,font/ttf,font/otf" class="block w-full rounded-xl border border-dashed border-white/15 bg-slate-950/70 px-3 py-2.5 text-sm text-stone-300 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-200 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-950" :class="firstError(fontValidationErrors, 'file') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(fontValidationErrors, 'file')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(fontValidationErrors, 'file') }}</p>
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="w-full rounded-xl bg-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-60" :disabled="fontSaving" @click="saveFont">
                            {{ fontSaving ? 'Uploading...' : 'Upload Font' }}
                        </button>
                    </div>
                </div>

                <p class="mt-3 text-xs text-stone-500">Accepted formats: `woff2`, `woff`, `ttf`, `otf`. Upload separate files for regular, bold, and italic variants if you want those buttons to render with the real font files.</p>

                <div class="mt-4 overflow-hidden rounded-2xl border border-white/10">
                    <div class="grid grid-cols-[minmax(0,1.1fr)_9rem_8rem_minmax(0,1fr)_7rem] gap-3 bg-white/[0.03] px-3 py-2 text-[11px] uppercase tracking-[0.2em] text-stone-500">
                        <span>Family</span>
                        <span>Variant</span>
                        <span>Format</span>
                        <span>File</span>
                        <span>Delete</span>
                    </div>
                    <div v-for="font in tenantFonts" :key="font.id" class="grid grid-cols-[minmax(0,1.1fr)_9rem_8rem_minmax(0,1fr)_7rem] items-center gap-3 border-t border-white/10 px-3 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-white" :style="{ fontFamily: `'${font.family}', sans-serif` }">{{ font.family }}</p>
                        </div>
                        <p class="text-sm text-stone-300">{{ fontVariantLabel(font) }}</p>
                        <p class="text-sm uppercase text-stone-400">{{ font.extension }}</p>
                        <p class="truncate text-sm text-stone-300">{{ font.file_name }}</p>
                        <button type="button" class="rounded-lg border border-rose-400/30 px-3 py-1.5 text-xs font-semibold text-rose-100 transition hover:bg-rose-400/10" @click="removeFont(font)">Delete</button>
                    </div>
                    <div v-if="!tenantFonts.length" class="border-t border-white/10 px-3 py-3 text-sm text-stone-400">
                        No brand fonts uploaded yet.
                    </div>
                </div>
            </section>
        </div>

        <div v-if="activeTab === 'account'" class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-slate-300">Account</p>
                <h3 class="mt-1 text-sm font-semibold italic">Login and profile</h3>
            </div>

            <form class="space-y-4" novalidate @submit.prevent="saveAccount">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Admin Name</label>
                        <input v-model="accountForm.name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50" :class="firstError(accountValidationErrors, 'name') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(accountValidationErrors, 'name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(accountValidationErrors, 'name') }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Admin Email</label>
                        <input v-model="accountForm.email" type="email" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50" :class="firstError(accountValidationErrors, 'email') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(accountValidationErrors, 'email')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(accountValidationErrors, 'email') }}</p>
                    </div>
                    <div class="sm:col-span-2 rounded-2xl border border-white/10 bg-slate-950/50 p-4">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Passwordless Login</p>
                        <p class="mt-1 text-xs text-stone-400">Admins sign in with a one-time code sent to this email address.</p>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-slate-300/40 hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-60" :disabled="accountSaving">
                        {{ accountSaving ? 'Saving...' : 'Save account settings' }}
                    </button>
                </div>
            </form>
        </div>

        <div v-if="activeTab === 'maintenance'" class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-slate-300">Maintenance</p>
                <h3 class="mt-1 text-sm font-semibold italic">Manage tenant status lists</h3>
            </div>

            <section v-for="section in maintenanceSections" :key="section.key" class="rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.25em] text-stone-500">{{ section.label }}</p>
                        <p class="mt-1 text-xs text-stone-400">Add, rename, and remove values used by this workspace.</p>
                    </div>
                    <span class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 text-xs text-stone-300">{{ maintenanceRecords[section.key]?.length ?? 0 }}</span>
                </div>

                <div class="mt-4 flex gap-3">
                    <input v-model="maintenanceDrafts[section.key]" type="text" :placeholder="`Add ${section.label.toLowerCase()}`" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                    <button type="button" class="rounded-xl bg-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-60" :disabled="maintenanceSaving || isBlank(maintenanceDrafts[section.key])" @click="saveStatus(section.key)">
                        {{ maintenanceEditing[section.key] ? 'Save' : 'Add' }}
                    </button>
                    <button v-if="maintenanceEditing[section.key]" type="button" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/5" @click="cancelEditStatus(section.key)">
                        Cancel
                    </button>
                </div>
                <p v-if="firstError(maintenanceValidationErrors, 'name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(maintenanceValidationErrors, 'name') }}</p>

                <div class="mt-4 overflow-hidden rounded-2xl border border-white/10">
                    <div class="grid grid-cols-[5rem_minmax(0,1fr)_8rem_8rem] gap-3 bg-white/[0.03] px-3 py-2 text-[11px] uppercase tracking-[0.2em] text-stone-500">
                        <span>ID</span>
                        <span>Name</span>
                        <span>Edit</span>
                        <span>Delete</span>
                    </div>
                    <div v-for="record in maintenanceRecords[section.key]" :key="`${section.key}-${record.id ?? record.name}`" class="grid grid-cols-[5rem_minmax(0,1fr)_8rem_8rem] items-center gap-3 border-t border-white/10 px-3 py-2.5">
                        <p class="text-sm text-stone-300">{{ record.id ?? '-' }}</p>
                        <div class="min-w-0">
                            <p class="truncate text-sm text-white">{{ prettifyStatus(record.name) }}</p>
                            <p v-if="record.system" class="mt-1 text-[11px] uppercase tracking-[0.2em] text-cyan-200">System</p>
                        </div>
                        <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!record.id || record.system" @click="beginEditStatus(section.key, record)">Edit</button>
                        <button type="button" class="rounded-lg border border-rose-400/30 px-3 py-1.5 text-xs font-semibold text-rose-100 transition hover:bg-rose-400/10 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!record.id || record.system" @click="removeStatus(section.key, record)">Delete</button>
                    </div>
                    <div v-if="!(maintenanceRecords[section.key]?.length)" class="border-t border-white/10 px-3 py-3 text-sm text-stone-400">
                        No values added yet.
                    </div>
                </div>
                <p v-if="!sectionHasEditableStatuses(section.key) && maintenanceRecords[section.key]?.length" class="mt-3 text-xs text-stone-500">System statuses are protected and cannot be renamed or deleted here.</p>
            </section>

        </div>
    </section>
</template>
