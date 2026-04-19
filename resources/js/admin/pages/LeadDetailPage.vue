<script setup>
import axios from 'axios';
import { computed, nextTick, onMounted, ref } from 'vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { emitAdminToast, useWorkspaceCrud } from '../useWorkspaceCrud';
import { autoAttachGoogleAddressInputs } from '../../googleAddressAutocomplete';
import { mergeFieldErrors } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const { saving, fieldErrors, submitForm } = useWorkspaceCrud();
const deleting = ref(false);
const leadRecord = ref(props.data.lead);
const showDeleteConfirm = ref(false);
const form = ref({
    customer_name: props.data.lead?.customer_name ?? '',
    customer_email: props.data.lead?.customer_email ?? '',
    customer_phone: props.data.lead?.customer_phone ?? '',
    event_date: props.data.lead?.event_date ?? '',
    event_location: props.data.lead?.event_location ?? '',
    notes: props.data.lead?.notes ?? '',
    status: props.data.lead?.status ?? 'draft',
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

const updateLead = async () => {
    if (!validateLeadForm()) {
        return;
    }

    try {
        const record = await submitForm({
            url: leadRecord.value.update_url,
            method: 'put',
            data: { ...form.value },
        });

        leadRecord.value = record;
        form.value = {
            customer_name: record.customer_name ?? '',
            customer_email: record.customer_email ?? '',
            customer_phone: record.customer_phone ?? '',
            event_date: record.event_date ?? '',
                event_location: record.event_location ?? '',
                notes: record.notes ?? '',
                status: record.status ?? 'draft',
        };
        clientErrors.value = {};

        window.history.replaceState({}, '', record.show_url);
    } catch {}
};

const deleteLead = async () => {
    showDeleteConfirm.value = true;
};

const confirmDeleteLead = async () => {
    deleting.value = true;

    try {
        await axios.delete(leadRecord.value.delete_url, {
            headers: {
                Accept: 'application/json',
            },
        });

        window.location.href = props.data.routes.leads;
    } catch {
        emitAdminToast({
            type: 'error',
            errors: ['Unable to delete this lead right now.'],
        });
    } finally {
        deleting.value = false;
        showDeleteConfirm.value = false;
    }
};

onMounted(() => {
    nextTick(() => autoAttachGoogleAddressInputs());
});
</script>

<template>
    <section class="rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-4 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-violet-200">Leads Workspace</p>
        <h2 class="mt-2 text-xl font-semibold tracking-tight">{{ leadRecord.customer_name }}</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-300">
            Review contact details, update follow-up status, and convert enquiry notes into a maintained sales record.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-violet-200">Lead Details</p>
                <h3 class="mt-2 text-lg font-semibold">{{ leadRecord.customer_name }}</h3>
            </div>
            <div class="flex items-center gap-2">
                <a :href="data.routes.leads" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">
                    Back to list
                </a>
                <button type="button" class="rounded-xl border border-rose-400/30 px-4 py-2 text-sm font-semibold text-rose-100 transition hover:bg-rose-400/10 disabled:cursor-not-allowed disabled:opacity-60" :disabled="deleting" @click="deleteLead">
                    {{ deleting ? 'Deleting...' : 'Delete lead' }}
                </button>
            </div>
        </div>

        <div class="mb-4 grid gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Status</p>
                <p class="mt-2 text-base font-semibold capitalize">{{ leadRecord.status }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Event Date</p>
                <p class="mt-2 text-base font-semibold">{{ leadRecord.event_date_label || 'Not set' }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Last Activity</p>
                <p class="mt-2 text-base font-semibold">{{ leadRecord.last_activity_label || 'Recently added' }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Booking Link</p>
                <a v-if="leadRecord.booking_show_url" :href="leadRecord.booking_show_url" class="mt-2 block text-sm font-semibold text-violet-200 transition hover:text-violet-100">
                    View booking
                </a>
                <p v-else class="mt-2 text-base font-semibold text-stone-400">Not booked yet</p>
            </div>
        </div>

        <div class="mb-4 rounded-xl border border-white/10 bg-slate-950/50 p-4">
            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Lead Snapshot</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Email</p>
                    <p class="mt-2 text-sm text-stone-200">{{ leadRecord.customer_email || 'No email provided' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Phone</p>
                    <p class="mt-2 text-sm text-stone-200">{{ leadRecord.customer_phone || 'No phone provided' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Event Location</p>
                    <p class="mt-2 text-sm text-stone-200">{{ leadRecord.event_location || 'No location provided' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Package</p>
                    <p class="mt-2 text-sm text-stone-200">{{ leadRecord.booking_package_name || 'No linked booking package' }}</p>
                </div>
            </div>
            <div class="mt-4">
                <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Notes</p>
                <p class="mt-2 text-sm leading-6 text-stone-300">{{ leadRecord.notes || 'No notes added yet.' }}</p>
            </div>
        </div>

        <form class="space-y-4" novalidate @submit.prevent="updateLead">
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
                <button type="submit" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-violet-300/40 hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Update lead' }}
                </button>
            </div>
        </form>
    </section>

    <ConfirmDialog
        :open="showDeleteConfirm"
        title="Delete lead?"
        :message="`Delete lead for ${leadRecord.customer_name}? This cannot be undone.`"
        confirm-label="Delete lead"
        :loading="deleting"
        @cancel="showDeleteConfirm = false"
        @confirm="confirmDeleteLead"
    />
</template>
