<script setup>
import { computed, ref } from 'vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';
import { firstError, hasFieldErrors, isBlank, mergeFieldErrors, requiredMessage, validEmail } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const { saving, fieldErrors, submitForm } = useWorkspaceCrud();
const clientErrors = ref({});
const form = ref({
    full_name: '',
    email: '',
    date_of_birth: '',
    address: '',
    phone: '',
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

const createCustomer = async () => {
    if (!validateCustomerForm()) {
        return;
    }

    try {
        const record = await submitForm({
            url: props.data.routes.store,
            data: { ...form.value },
        });

        window.setTimeout(() => {
            window.location.href = record.show_url;
        }, 300);
    } catch {}
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-cyan-200">Customers Workspace</p>
        <h2 class="text-sm font-bold italic text-white">Create customer</h2>
        <p class="text-sm text-stone-300">
            Add a customer record manually when you need to capture booking contacts outside the public form.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-cyan-200">New Customer</p>
                <h3 class="mt-1 text-sm font-semibold italic">Create record</h3>
            </div>
            <a :href="data.routes.customers" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">
                Back to list
            </a>
        </div>

        <form class="space-y-4" novalidate @submit.prevent="createCustomer">
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
                <button type="submit" class="rounded-xl bg-cyan-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Create customer' }}
                </button>
            </div>
        </form>
    </section>
</template>
