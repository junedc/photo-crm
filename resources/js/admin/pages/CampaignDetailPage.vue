<script setup>
import axios from 'axios';
import { computed, ref } from 'vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { emitAdminToast, useWorkspaceCrud } from '../useWorkspaceCrud';
import { firstError, hasFieldErrors, isBlank, mergeFieldErrors, requiredMessage } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const { saving, fieldErrors, submitForm } = useWorkspaceCrud();
const sending = ref(false);
const deleteForm = ref(null);
const campaignRecord = ref(props.data.campaign);
const clientErrors = ref({});
const showSendConfirm = ref(false);
const showDeleteConfirm = ref(false);
const form = ref({
    template_id: campaignRecord.value?.template_id ?? '',
    subject: campaignRecord.value?.subject ?? '',
    preheader: campaignRecord.value?.preheader ?? '',
    headline: campaignRecord.value?.headline ?? '',
    body: campaignRecord.value?.body ?? '',
    button_text: campaignRecord.value?.button_text ?? '',
    button_url: campaignRecord.value?.button_url ?? '',
    group_ids: [...(campaignRecord.value?.group_ids ?? [])],
});

const templates = computed(() => props.data.templates ?? []);
const groups = computed(() => props.data.groups ?? []);
const selectedGroups = computed(() => groups.value.filter((group) => form.value.group_ids.includes(group.id)));
const selectedAudienceCount = computed(() => selectedGroups.value.reduce((total, group) => total + (group.customers_count ?? 0), 0));
const validationErrors = computed(() => mergeFieldErrors(clientErrors.value, fieldErrors.value));

const applyRecord = (record) => {
    campaignRecord.value = record;
    form.value = {
        template_id: record.template_id ?? '',
        subject: record.subject ?? '',
        preheader: record.preheader ?? '',
        headline: record.headline ?? '',
        body: record.body ?? '',
        button_text: record.button_text ?? '',
        button_url: record.button_url ?? '',
        group_ids: [...(record.group_ids ?? [])],
    };
    clientErrors.value = {};
};

const applyTemplate = () => {
    const template = templates.value.find((entry) => entry.id === Number(form.value.template_id));
    if (!template) {
        return;
    }

    form.value.subject = template.subject ?? '';
    form.value.preheader = template.preheader ?? '';
    form.value.headline = template.headline ?? '';
    form.value.body = template.html_body ?? '';
    form.value.button_text = template.button_text ?? '';
    form.value.button_url = template.button_url ?? '';
};

const updateCampaign = async () => {
    const errors = {};

    if (isBlank(form.value.template_id)) {
        errors.template_id = requiredMessage('Template');
    }

    if (isBlank(form.value.subject)) {
        errors.subject = requiredMessage('Email subject');
    }

    if (isBlank(form.value.body)) {
        errors.body = requiredMessage('Rich email body');
    }

    if (!isBlank(form.value.button_text) && isBlank(form.value.button_url)) {
        errors.button_url = 'Button URL is required when button text is entered.';
    }

    clientErrors.value = errors;

    if (hasFieldErrors(errors)) {
        return;
    }

    try {
        const record = await submitForm({
            url: campaignRecord.value.update_url,
            method: 'put',
            data: form.value,
        });

        applyRecord(record);
        window.history.replaceState({}, '', record.show_url);
    } catch {}
};

const sendCampaign = async () => {
    if (!form.value.group_ids.length) {
        emitAdminToast({ type: 'error', errors: ['Select at least one subscriber group.'] });
        return;
    }

    showSendConfirm.value = true;
};

const confirmSendCampaign = async () => {
    sending.value = true;

    try {
        const response = await axios.post(campaignRecord.value.send_url, {
            group_ids: form.value.group_ids,
        }, {
            headers: { Accept: 'application/json' },
        });

        applyRecord(response.data.record);
        emitAdminToast({ type: 'success', message: response.data.message ?? 'Campaign sent.' });
    } catch (error) {
        emitAdminToast({
            type: 'error',
            errors: Object.values(error.response?.data?.errors ?? { message: [error.response?.data?.message ?? 'Something went wrong while sending the campaign.'] }).flat(),
        });
    } finally {
        sending.value = false;
        showSendConfirm.value = false;
    }
};

const markBounced = async (recipient) => {
    try {
        const response = await axios.post(recipient.bounce_url, {}, {
            headers: { Accept: 'application/json' },
        });

        applyRecord(response.data.record);
        emitAdminToast({ type: 'success', message: response.data.message ?? 'Recipient marked as bounced.' });
    } catch (error) {
        emitAdminToast({
            type: 'error',
            errors: Object.values(error.response?.data?.errors ?? { message: [error.response?.data?.message ?? 'Could not mark recipient as bounced.'] }).flat(),
        });
    }
};

const removeCampaign = () => {
    showDeleteConfirm.value = true;
};

const confirmRemoveCampaign = () => {
    deleteForm.value?.submit();
};
</script>

<template>
    <section class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-pink-200">Campaign Studio</p>
        <h2 class="text-sm font-bold italic text-white">{{ campaignRecord.subject }}</h2>
        <p class="text-sm text-stone-300">
            Status: <span class="font-semibold" :class="campaignRecord.status === 'sent' ? 'text-emerald-200' : 'text-amber-200'">{{ campaignRecord.status_label }}</span>
            <span v-if="campaignRecord.sent_at_label"> · Sent {{ campaignRecord.sent_at_label }}</span>
        </p>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="rounded-xl bg-pink-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-pink-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="sending" @click="sendCampaign">
                {{ sending ? 'Sending...' : 'Send campaign' }}
            </button>
            <button type="button" class="rounded-xl border border-rose-400/30 px-4 py-2 text-sm font-semibold text-rose-100 transition hover:bg-rose-400/10" @click="removeCampaign">
                Delete
            </button>
            <a :href="data.routes.campaigns" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">Back</a>
        </div>
    </section>

    <form ref="deleteForm" :action="campaignRecord.delete_url" method="post" class="hidden">
        <input type="hidden" name="_token" :value="data.csrfToken">
        <input type="hidden" name="_method" value="DELETE">
    </form>

    <section class="grid gap-3 md:grid-cols-4">
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Sent</p>
            <p class="mt-2 text-2xl font-semibold">{{ campaignRecord.sent_count }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Opened</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-200">{{ campaignRecord.opened_count }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Bounced</p>
            <p class="mt-2 text-2xl font-semibold text-amber-200">{{ campaignRecord.bounced_count }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Unsubscribed</p>
            <p class="mt-2 text-2xl font-semibold text-rose-200">{{ campaignRecord.unsubscribed_count }}</p>
        </div>
    </section>

    <form class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_420px]" novalidate @submit.prevent="updateCampaign">
        <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Template</label>
                    <select v-model="form.template_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-pink-300/50" :class="firstError(validationErrors, 'template_id') ? 'border-rose-300/60' : ''" @change="applyTemplate">
                        <option value="">Choose a template</option>
                        <option v-for="template in templates" :key="template.id" :value="template.id">{{ template.name }}</option>
                    </select>
                    <p v-if="firstError(validationErrors, 'template_id')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'template_id') }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Email subject</label>
                    <input v-model="form.subject" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-pink-300/50" :class="firstError(validationErrors, 'subject') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'subject')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'subject') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Header headline</label>
                    <input v-model="form.headline" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-pink-300/50">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Preview text</label>
                    <input v-model="form.preheader" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-pink-300/50">
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Rich email body</label>
                    <div contenteditable="true" class="min-h-72 rounded-xl border border-white/10 bg-white px-4 py-3 text-sm leading-6 text-slate-900 outline-none focus:border-pink-300/70" :class="firstError(validationErrors, 'body') ? 'border-rose-300/60' : ''" @input="form.body = $event.target.innerHTML" v-html="form.body" />
                    <p v-if="firstError(validationErrors, 'body')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'body') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Button text</label>
                    <input v-model="form.button_text" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-pink-300/50">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Button URL</label>
                    <input v-model="form.button_url" type="url" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-pink-300/50" :class="firstError(validationErrors, 'button_url') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'button_url')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'button_url') }}</p>
                </div>
            </div>

            <div class="mt-5 flex justify-end">
                <button type="submit" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-pink-300/40 hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Update campaign' }}
                </button>
            </div>
        </section>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Audience</p>
                <h3 class="mt-2 text-lg font-semibold">{{ selectedAudienceCount }} possible recipients</h3>
                <div class="mt-3 max-h-60 space-y-2 overflow-y-auto pr-1">
                    <label v-for="group in groups" :key="group.id" class="flex items-start gap-3 rounded-xl border border-white/10 px-3 py-2.5 text-sm text-stone-200 transition hover:bg-white/[0.03]">
                        <input v-model="form.group_ids" :value="group.id" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-white/20 bg-slate-950 text-pink-300">
                        <span class="min-w-0">
                            <span class="block truncate font-medium text-white">{{ group.name }}</span>
                            <span class="block truncate text-xs text-stone-400">{{ group.customers_count }} users</span>
                        </span>
                    </label>
                </div>
            </section>

            <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Recipient Activity</p>
                <div class="mt-3 max-h-80 space-y-2 overflow-y-auto pr-1">
                    <div v-for="recipient in campaignRecord.recipients" :key="recipient.id" class="rounded-xl border border-white/10 px-3 py-2.5 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-white">{{ recipient.name }}</p>
                                <p class="truncate text-xs text-stone-400">{{ recipient.email }}</p>
                            </div>
                            <span class="rounded-lg bg-white/[0.05] px-2 py-1 text-xs capitalize text-stone-300">{{ recipient.status }}</span>
                        </div>
                        <p class="mt-2 text-xs text-stone-500">
                            Opened: {{ recipient.opened_at_label || 'No' }} · Bounced: {{ recipient.bounced_at_label || 'No' }} · Unsubscribed: {{ recipient.unsubscribed_at_label || 'No' }}
                        </p>
                        <button v-if="!recipient.bounced_at_label" type="button" class="mt-2 rounded-lg border border-amber-300/30 px-2.5 py-1 text-xs font-semibold text-amber-100 transition hover:bg-amber-300/10" @click="markBounced(recipient)">
                            Mark bounced
                        </button>
                    </div>
                    <p v-if="!campaignRecord.recipients?.length" class="rounded-xl border border-dashed border-white/15 px-3 py-4 text-sm text-stone-400">
                        Recipient tracking appears after the campaign is sent.
                    </p>
                </div>
            </section>
        </aside>
    </form>

    <ConfirmDialog
        :open="showSendConfirm"
        title="Send campaign?"
        :message="`Send &quot;${form.subject}&quot; to the selected subscriber groups?`"
        confirm-label="Send campaign"
        tone="info"
        :loading="sending"
        @cancel="showSendConfirm = false"
        @confirm="confirmSendCampaign"
    />

    <ConfirmDialog
        :open="showDeleteConfirm"
        title="Delete campaign?"
        :message="`Delete campaign &quot;${campaignRecord.subject}&quot;? This cannot be undone.`"
        confirm-label="Delete campaign"
        @cancel="showDeleteConfirm = false"
        @confirm="confirmRemoveCampaign"
    />
</template>
