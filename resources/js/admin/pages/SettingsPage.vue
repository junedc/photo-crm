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

const workspacePhotoInput = ref(null);
const tenantRecord = ref(props.data.tenant ?? {});
const subscriptions = ref(props.data.subscriptions ?? []);
const csrfToken = window.adminProps?.csrfToken ?? '';
const userRecord = ref(props.data.user ?? {});
const workspaceErrors = ref({});
const accountErrors = ref({});
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

const workspaceForm = ref({
    name: tenantRecord.value.name ?? '',
    contact_email: tenantRecord.value.contact_email ?? '',
    contact_phone: tenantRecord.value.contact_phone ?? '',
    address: tenantRecord.value.address ?? '',
    theme: tenantRecord.value.theme ?? 'dark',
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
const workspaceValidationErrors = computed(() => mergeFieldErrors(workspaceErrors.value, workspaceServerErrors.value));
const accountValidationErrors = computed(() => mergeFieldErrors(accountErrors.value, accountServerErrors.value));

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

onMounted(() => {
    nextTick(() => autoAttachGoogleAddressInputs());
});
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-slate-300">Settings</p>
        <h2 class="text-sm font-bold italic text-white">Workspace and account settings</h2>
        <p class="text-sm text-stone-300">
            Update your business logo, contact details, workspace settings, and admin profile from one place.
        </p>
    </section>

    <section class="space-y-6">
        <div class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.3em] text-slate-300">Workspace</p>
                    <h3 class="mt-2 text-lg font-semibold">Workspace settings</h3>
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
                        <p class="mt-1 text-xs text-stone-500">Controls the workspace admin and public customer pages for this tenant.</p>
                    </div>
                    <div class="sm:col-span-2 rounded-2xl border border-sky-300/20 bg-sky-300/10 p-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="flex-1">
                                <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-sky-200">Subscription</label>
                                <select v-model="workspaceForm.subscription_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300/50">
                                    <option value="">Choose a subscription</option>
                                    <option v-if="tenantRecord.subscription?.billing_period === 'free_for_life'" :value="tenantRecord.subscription_id" disabled>
                                        Free for life · assigned by platform admin
                                    </option>
                                    <option v-for="subscription in subscriptions" :key="subscription.id" :value="subscription.id">
                                        {{ subscription.name }} · {{ subscription.currency }} {{ subscription.price }} · {{ subscription.validity_label }}
                                    </option>
                                </select>
                                <p v-if="tenantRecord.subscription && tenantRecord.subscription_enabled" class="mt-2 text-xs text-sky-100">
                                    Current subscription: {{ tenantRecord.subscription.name }} at {{ tenantRecord.subscription.currency }} {{ tenantRecord.subscription.price }}.
                                </p>
                                <p v-if="!tenantRecord.subscription_enabled" class="mt-2 text-xs text-rose-200">
                                    This workspace is disabled by platform admin. Pay the selected subscription or contact support to reactivate access.
                                </p>
                            </div>
                            <form v-if="tenantRecord.subscription" method="POST" :action="props.data.routes.subscriptionPay" class="lg:pt-6">
                                <input type="hidden" name="_token" :value="csrfToken">
                                <button type="submit" class="w-full rounded-xl bg-sky-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-sky-200 lg:w-auto">
                                    Pay subscription
                                </button>
                            </form>
                        </div>

                        <div v-if="tenantRecord.subscription_charges?.length" class="mt-4 overflow-hidden rounded-2xl border border-white/10">
                            <table class="min-w-full divide-y divide-white/10 text-sm">
                                <thead class="bg-slate-950/50 text-left text-[11px] uppercase tracking-[0.2em] text-slate-400">
                                    <tr>
                                        <th class="px-3 py-2">Period</th>
                                        <th class="px-3 py-2">Amount</th>
                                        <th class="px-3 py-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/10 bg-slate-950/30">
                                    <tr v-for="charge in tenantRecord.subscription_charges" :key="charge.id">
                                        <td class="px-3 py-2 text-stone-300">
                                            {{ charge.period_starts_at }}<span v-if="charge.period_ends_at"> - {{ charge.period_ends_at }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-stone-300">{{ charge.currency }} {{ charge.amount }}</td>
                                        <td class="px-3 py-2">
                                            <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="charge.status === 'paid' || charge.status === 'waived' ? 'bg-emerald-300/15 text-emerald-200' : 'bg-amber-300/15 text-amber-100'">
                                                {{ charge.status }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-white/10 bg-slate-950/50 p-4">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Workspace Settings</p>
                    <p class="mt-2 text-sm text-stone-400">Control booking deposits, travel pricing, and public package API access from one table.</p>

                    <div class="mt-4 overflow-hidden rounded-2xl border border-white/10">
                        <table class="min-w-full table-fixed divide-y divide-white/10 text-sm">
                            <thead class="bg-white/[0.03] text-left text-[11px] uppercase tracking-[0.25em] text-stone-500">
                                <tr>
                                    <th class="w-[60%] px-4 py-3 font-medium">Setting</th>
                                    <th class="w-[40%] px-4 py-3 font-medium">Value</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10 bg-slate-950/40">
                                <tr>
                                    <td class="px-4 py-3 align-top">
                                        <p class="font-medium text-white">Invoice Deposit Percentage</p>
                                        <p class="mt-1 text-xs text-stone-500">Default percentage used for booking deposits and installment invoices.</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <input v-model="workspaceForm.invoice_deposit_percentage" type="number" min="0" max="100" step="0.01" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                                            <span class="text-stone-400">%</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 align-top">
                                        <p class="font-medium text-white">Free Travel Kilometers</p>
                                        <p class="mt-1 text-xs text-stone-500">One-way kilometers included for free. The allowance is doubled for round-trip travel.</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <input v-model="workspaceForm.travel_free_kilometers" type="number" min="0" step="0.01" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                                            <span class="text-stone-400">km</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 align-top">
                                        <p class="font-medium text-white">Travel Fee Per Kilometer</p>
                                        <p class="mt-1 text-xs text-stone-500">Applied to chargeable round-trip kilometers after the free allowance.</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="text-stone-400">$</span>
                                            <input v-model="workspaceForm.travel_fee_per_kilometer" type="number" min="0" step="0.01" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                                            <span class="text-stone-400">/ km</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 align-top">
                                        <p class="font-medium text-white">Customer Package Discount</p>
                                        <p class="mt-1 text-xs text-stone-500">Applies a discount to all package prices on the customer booking page. Add-ons and travel are not discounted.</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <input v-model="workspaceForm.customer_package_discount_percentage" type="number" min="0" max="100" step="0.01" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                                            <span class="text-stone-400">%</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 align-top">
                                        <p class="font-medium text-white">Packages API Key</p>
                                        <p class="mt-1 text-xs text-stone-500">Shared secret used by the MemoShot marketing site when it requests this workspace's package catalog.</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input v-model="workspaceForm.packages_api_key" type="text" autocomplete="off" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 align-top">
                                        <p class="font-medium text-white">Stripe Secret Key</p>
                                        <p class="mt-1 text-xs text-stone-500">Tenant-specific Stripe secret used to create checkout sessions. Leave blank to keep the saved key.</p>
                                        <p v-if="tenantRecord.stripe_secret_configured" class="mt-1 text-xs text-emerald-300">A Stripe secret key is saved for this workspace.</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input v-model="workspaceForm.stripe_secret" type="password" autocomplete="new-password" placeholder="sk_live_..." class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 align-top">
                                        <p class="font-medium text-white">Tenant Stripe Webhook URL</p>
                                        <p class="mt-1 text-xs text-stone-500">Use this URL in your Stripe Dashboard webhook endpoint.</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="rounded-2xl border border-cyan-300/20 bg-cyan-300/10 p-4">
                                            <p class="break-all font-mono text-sm text-cyan-100">{{ props.data.routes.tenantStripeWebhook }}</p>
                                            <p class="mt-2 text-xs font-medium text-cyan-50">Required event: checkout.session.completed</p>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 align-top">
                                        <p class="font-medium text-white">Stripe Webhook Secret</p>
                                        <p class="mt-1 text-xs text-stone-500">Tenant-specific webhook signing secret for Stripe payment confirmation. Leave blank to keep the saved secret.</p>
                                        <p v-if="tenantRecord.stripe_webhook_secret_configured" class="mt-1 text-xs text-emerald-300">A Stripe webhook secret is saved for this workspace.</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input v-model="workspaceForm.stripe_webhook_secret" type="password" autocomplete="new-password" placeholder="whsec_..." class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 align-top">
                                        <p class="font-medium text-white">Stripe Currency</p>
                                        <p class="mt-1 text-xs text-stone-500">Three-letter currency code used for this workspace's checkout sessions.</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input v-model="workspaceForm.stripe_currency" type="text" maxlength="3" autocomplete="off" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm uppercase text-white outline-none transition focus:border-slate-300/50" :class="firstError(workspaceValidationErrors, 'stripe_currency') ? 'border-rose-300/60' : ''">
                                        <p v-if="firstError(workspaceValidationErrors, 'stripe_currency')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(workspaceValidationErrors, 'stripe_currency') }}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 align-top">
                                        <p class="font-medium text-white">Quote Number Prefix</p>
                                        <p class="mt-1 text-xs text-stone-500">Prefix used when new quotes are created for this workspace.</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input v-model="workspaceForm.quote_prefix" type="text" maxlength="20" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 align-top">
                                        <p class="font-medium text-white">Invoice Number Prefix</p>
                                        <p class="mt-1 text-xs text-stone-500">Prefix used when new invoices are created for this workspace.</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input v-model="workspaceForm.invoice_prefix" type="text" maxlength="20" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-slate-300/50">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="rounded-xl bg-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-60" :disabled="workspaceSaving">
                        {{ workspaceSaving ? 'Saving...' : 'Save workspace settings' }}
                    </button>
                </div>
            </form>
        </div>

        <div class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.3em] text-slate-300">Account</p>
                    <h3 class="mt-2 text-lg font-semibold">Login and profile</h3>
                </div>
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
                        <p class="mt-2 text-sm text-stone-400">Admins sign in with a one-time code sent to this email address.</p>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-slate-300/40 hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-60" :disabled="accountSaving">
                        {{ accountSaving ? 'Saving...' : 'Save account settings' }}
                    </button>
                </div>
            </form>
        </div>
    </section>
</template>
