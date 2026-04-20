<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';
import { autoAttachGoogleAddressInputs } from '../../googleAddressAutocomplete';
import { mergeFieldErrors } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const { saving, fieldErrors, submitForm } = useWorkspaceCrud();
const form = ref({
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    event_date: '',
    event_location: '',
    notes: '',
    status: 'draft',
});
const isEmailRequired = computed(() => !form.value.customer_phone?.trim());
const isPhoneRequired = computed(() => !form.value.customer_email?.trim());
const clientErrors = ref({});
const validationErrors = computed(() => mergeFieldErrors(clientErrors.value, fieldErrors.value));

const validateLeadForm = () => {
    const errors = {};

    if (!form.value.customer_name?.trim()) {
        errors.customer_name = 'Customer name is required.';
    }

    if (!form.value.customer_email?.trim() && !form.value.customer_phone?.trim()) {
        errors.customer_email = 'Enter an email or phone number.';
        errors.customer_phone = 'Enter a phone number or email.';
    }

    clientErrors.value = errors;

    return Object.keys(errors).length === 0;
};

const createLead = async () => {
    if (!validateLeadForm()) {
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

onMounted(() => {
    nextTick(() => autoAttachGoogleAddressInputs());
});
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-violet-200">Leads Workspace</p>
        <h2 class="text-sm font-bold italic text-white">Create lead</h2>
        <p class="text-sm text-stone-300">
            Add a lead manually when a customer reaches out by phone, email, or walk-in enquiry.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-violet-200">New Lead</p>
                <h3 class="mt-2 text-lg font-semibold">Create record</h3>
            </div>
            <a :href="data.routes.leads" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">
                Back to list
            </a>
        </div>

        <form class="space-y-4" novalidate @submit.prevent="createLead">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Customer Name <span class="text-rose-300">*</span></label>
                    <input v-model="form.customer_name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-violet-300/50" :class="validationErrors.customer_name ? 'border-rose-300/60' : ''">
                    <p v-if="validationErrors.customer_name" class="mt-1 text-xs font-medium text-rose-300">{{ validationErrors.customer_name }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Status</label>
                    <select v-model="form.status" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-violet-300/50">
                        <option v-for="status in data.leadStatuses" :key="status" :value="status">{{ status }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Email <span v-if="isEmailRequired" class="text-rose-300">*</span></label>
                    <input v-model="form.customer_email" type="email" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-violet-300/50" :class="validationErrors.customer_email ? 'border-rose-300/60' : ''">
                    <p v-if="validationErrors.customer_email" class="mt-1 text-xs font-medium text-rose-300">{{ validationErrors.customer_email }}</p>
                    <p v-else class="mt-1 text-xs text-stone-500">Required when phone is empty.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Phone <span v-if="isPhoneRequired" class="text-rose-300">*</span></label>
                    <input v-model="form.customer_phone" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-violet-300/50" :class="validationErrors.customer_phone ? 'border-rose-300/60' : ''">
                    <p v-if="validationErrors.customer_phone" class="mt-1 text-xs font-medium text-rose-300">{{ validationErrors.customer_phone }}</p>
                    <p v-else class="mt-1 text-xs text-stone-500">Required when email is empty.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Event Date</label>
                    <input v-model="form.event_date" type="date" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-violet-300/50" onkeydown="return false">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Event Location</label>
                    <input v-model="form.event_location" type="text" data-google-address="true" autocomplete="street-address" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-violet-300/50">
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Notes</label>
                    <textarea v-model="form.notes" rows="5" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-violet-300/50" />
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-xl bg-violet-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-violet-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Create lead' }}
                </button>
            </div>
        </form>
    </section>
</template>
