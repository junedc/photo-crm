<script setup>
import axios from 'axios';
import { computed, ref } from 'vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { emitAdminToast, useWorkspaceCrud } from '../useWorkspaceCrud';
import { firstError, hasFieldErrors, isBlank, mergeFieldErrors, requiredMessage, validEmail } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const { saving, fieldErrors, submitForm } = useWorkspaceCrud();
const deleting = ref(false);
const customerRecord = ref(props.data.customer);
const clientErrors = ref({});
const showDeleteConfirm = ref(false);
const form = ref({
    full_name: props.data.customer?.full_name ?? '',
    email: props.data.customer?.email ?? '',
    date_of_birth: props.data.customer?.date_of_birth ?? '',
    address: props.data.customer?.address ?? '',
    phone: props.data.customer?.phone ?? '',
});

const validationErrors = computed(() => mergeFieldErrors(clientErrors.value, fieldErrors.value));

const validateCustomerForm = () => {
    const errors = {};

    if (isBlank(form.value.full_name)) {
        errors.full_name = requiredMessage('Full name');
    }

    if (isBlank(form.value.email)) {
        errors.email = requiredMessage('Email address');
    } else if (!validEmail(form.value.email)) {
        errors.email = 'Enter a valid email address.';
    }

    if (isBlank(form.value.phone)) {
        errors.phone = requiredMessage('Phone');
    }

    clientErrors.value = errors;

    return !hasFieldErrors(errors);
};

const updateCustomer = async () => {
    if (!validateCustomerForm()) {
        return;
    }

    try {
        const record = await submitForm({
            url: customerRecord.value.update_url,
            method: 'put',
            data: { ...form.value },
        });

        customerRecord.value = record;
        form.value = {
            full_name: record.full_name ?? '',
            email: record.email ?? '',
            date_of_birth: record.date_of_birth ?? '',
            address: record.address ?? '',
            phone: record.phone ?? '',
        };
        clientErrors.value = {};

        window.history.replaceState({}, '', record.show_url);
    } catch {}
};

const deleteCustomer = async () => {
    showDeleteConfirm.value = true;
};

const confirmDeleteCustomer = async () => {
    deleting.value = true;

    try {
        await axios.delete(customerRecord.value.delete_url, {
            headers: {
                Accept: 'application/json',
            },
        });

        window.location.href = props.data.routes.customers;
    } catch {
        emitAdminToast({
            type: 'error',
            errors: ['Unable to delete this customer right now.'],
        });
    } finally {
        deleting.value = false;
        showDeleteConfirm.value = false;
    }
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-cyan-200">Customers Workspace</p>
        <h2 class="text-sm font-bold italic text-white">{{ customerRecord.full_name }}</h2>
        <p class="text-sm text-stone-300">
            Review the customer profile, keep contact details accurate, and maintain the booking history count for this person.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-cyan-200">Customer Details</p>
                <h3 class="mt-2 text-lg font-semibold">{{ customerRecord.full_name }}</h3>
            </div>
            <div class="flex items-center gap-2">
                <a :href="data.routes.customers" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">
                    Back to list
                </a>
                <button type="button" class="rounded-xl border border-rose-400/30 px-4 py-2 text-sm font-semibold text-rose-100 transition hover:bg-rose-400/10 disabled:cursor-not-allowed disabled:opacity-60" :disabled="deleting" @click="deleteCustomer">
                    {{ deleting ? 'Deleting...' : 'Delete customer' }}
                </button>
            </div>
        </div>

        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Bookings</p>
                <p class="mt-2 text-base font-semibold">{{ customerRecord.bookings_count }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Date of Birth</p>
                <p class="mt-2 text-base font-semibold">{{ customerRecord.date_of_birth_label || 'Not provided' }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Created</p>
                <p class="mt-2 text-base font-semibold">{{ customerRecord.created_at || 'Recently added' }}</p>
            </div>
        </div>

        <div class="mb-4 rounded-xl border border-white/10 bg-slate-950/50 p-4">
            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Customer Snapshot</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Email</p>
                    <p class="mt-2 text-sm text-stone-200">{{ customerRecord.email }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Phone</p>
                    <p class="mt-2 text-sm text-stone-200">{{ customerRecord.phone }}</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Address</p>
                    <p class="mt-2 text-sm text-stone-200">{{ customerRecord.address || 'No address provided' }}</p>
                </div>
            </div>
        </div>

        <form class="space-y-4" novalidate @submit.prevent="updateCustomer">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Full Name</label>
                    <input v-model="form.full_name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(validationErrors, 'full_name') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'full_name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'full_name') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Email Address</label>
                    <input v-model="form.email" type="email" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(validationErrors, 'email') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'email')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'email') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Phone</label>
                    <input v-model="form.phone" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(validationErrors, 'phone') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'phone')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'phone') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Date of Birth</label>
                    <input v-model="form.date_of_birth" type="date" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50" onkeydown="return false">
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Address</label>
                    <textarea v-model="form.address" rows="4" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50" />
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-cyan-300/40 hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Update customer' }}
                </button>
            </div>
        </form>
    </section>

    <ConfirmDialog
        :open="showDeleteConfirm"
        title="Delete customer?"
        :message="`Delete customer ${customerRecord.full_name}? This cannot be undone.`"
        confirm-label="Delete customer"
        :loading="deleting"
        @cancel="showDeleteConfirm = false"
        @confirm="confirmDeleteCustomer"
    />
</template>
