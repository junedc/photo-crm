<script setup>
import axios from 'axios';
import { computed, nextTick, ref } from 'vue';
import { emitAdminToast } from '../useWorkspaceCrud';
import { firstError, hasFieldErrors, isBlank, requiredMessage } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const campaigns = ref([...(props.data.campaigns ?? [])]);
const templates = ref([...(props.data.templates ?? [])]);
const groups = ref([...(props.data.groups ?? [])]);
const recipientOptions = computed(() => props.data.recipientOptions ?? { customers: [], leads: [] });
const activeGroupId = ref(groups.value[0]?.id ?? null);
const activeTab = ref('campaigns');
const search = ref('');
const selectedStatus = ref('all');
const savingTemplate = ref(false);
const savingGroup = ref(false);
const templateErrors = ref({});
const groupErrors = ref({});
const importingGroupId = ref(null);
const attachingGroupId = ref(null);
const showGroupModal = ref(false);
const templateEditor = ref(null);
const defaultTemplateBody = '<p>Hello {{ first_name }},</p><p>Share your campaign message here.</p>';
const templateForm = ref({
    name: '',
    subject: '',
    preheader: '',
    headline: '',
    html_body: defaultTemplateBody,
    button_text: '',
    button_url: '',
});
const groupForm = ref({
    name: '',
    description: '',
});
const importFiles = ref({});
const recipientSearches = ref({});
const recipientSelections = ref({});
const recipientTypeFilters = ref({});

const filteredCampaigns = computed(() =>
    campaigns.value.filter((campaign) => {
        const matchesSearch = [campaign.subject, campaign.headline, campaign.template_name]
            .filter(Boolean)
            .some((value) => value.toLowerCase().includes(search.value.toLowerCase()));
        const matchesStatus = selectedStatus.value === 'all' || campaign.status === selectedStatus.value;

        return matchesSearch && matchesStatus;
    }),
);

const selectedGroup = computed(() => groups.value.find((group) => group.id === activeGroupId.value) ?? groups.value[0] ?? null);

const openTab = (tab) => {
    activeTab.value = tab;

    if (tab === 'templates') {
        nextTick(syncTemplateEditor);
    }
};

const syncTemplateEditor = () => {
    if (templateEditor.value && templateEditor.value.innerHTML !== templateForm.value.html_body) {
        templateEditor.value.innerHTML = templateForm.value.html_body;
    }
};

const resetTemplateForm = () => {
    templateForm.value = {
        name: '',
        subject: '',
        preheader: '',
        headline: '',
        html_body: defaultTemplateBody,
        button_text: '',
        button_url: '',
    };
    templateErrors.value = {};
    nextTick(syncTemplateEditor);
};

const resetGroupForm = () => {
    groupForm.value = {
        name: '',
        description: '',
    };
    groupErrors.value = {};
};

const openGroupModal = () => {
    resetGroupForm();
    showGroupModal.value = true;
};

const closeGroupModal = () => {
    if (savingGroup.value) {
        return;
    }

    showGroupModal.value = false;
    resetGroupForm();
};

const updateTemplateBody = (event) => {
    templateForm.value.html_body = event.target.innerHTML;
};

const runTemplateCommand = (command, value = null) => {
    templateEditor.value?.focus();
    document.execCommand(command, false, value);
    templateForm.value.html_body = templateEditor.value?.innerHTML ?? templateForm.value.html_body;
};

const addTemplateLink = () => {
    const url = window.prompt('Enter the link URL');
    if (!url) {
        return;
    }

    runTemplateCommand('createLink', url);
};

const saveTemplate = async () => {
    const errors = {};

    if (isBlank(templateForm.value.name)) {
        errors.name = requiredMessage('Template name');
    }

    if (isBlank(templateForm.value.subject)) {
        errors.subject = requiredMessage('Email subject');
    }

    if (isBlank(templateForm.value.html_body)) {
        errors.html_body = requiredMessage('Template body');
    }

    if (!isBlank(templateForm.value.button_text) && isBlank(templateForm.value.button_url)) {
        errors.button_url = 'Button URL is required when button text is entered.';
    }

    templateErrors.value = errors;

    if (hasFieldErrors(errors)) {
        return;
    }

    savingTemplate.value = true;

    try {
        const response = await axios.post(props.data.routes.templateStore, templateForm.value, {
            headers: { Accept: 'application/json' },
        });

        templates.value.unshift(response.data.record);
        resetTemplateForm();
        emitAdminToast({ type: 'success', message: response.data.message });
    } catch (error) {
        templateErrors.value = error.response?.data?.errors ?? {};
        emitAdminToast({ type: 'error', errors: Object.values(templateErrors.value ?? { message: [error.response?.data?.message ?? 'Template could not be saved.'] }).flat() });
    } finally {
        savingTemplate.value = false;
    }
};

const saveGroup = async () => {
    const errors = {};

    if (isBlank(groupForm.value.name)) {
        errors.name = requiredMessage('Group name');
    }

    groupErrors.value = errors;

    if (hasFieldErrors(errors)) {
        return;
    }

    savingGroup.value = true;

    try {
        const response = await axios.post(props.data.routes.groupStore, groupForm.value, {
            headers: { Accept: 'application/json' },
        });

        groups.value.unshift(response.data.record);
        activeGroupId.value = response.data.record.id;
        resetGroupForm();
        showGroupModal.value = false;
        emitAdminToast({ type: 'success', message: response.data.message });
    } catch (error) {
        groupErrors.value = error.response?.data?.errors ?? {};
        emitAdminToast({ type: 'error', errors: Object.values(groupErrors.value ?? { message: [error.response?.data?.message ?? 'Group could not be saved.'] }).flat() });
    } finally {
        savingGroup.value = false;
    }
};

const importGroup = async (group) => {
    const file = importFiles.value[group.id];
    if (!file) {
        emitAdminToast({ type: 'error', errors: ['Choose a CSV file exported from Excel first.'] });
        return;
    }

    importingGroupId.value = group.id;
    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await axios.post(group.import_url, formData, {
            headers: { Accept: 'application/json' },
        });
        groups.value = groups.value.map((entry) => entry.id === group.id ? response.data.record : entry);
        importFiles.value[group.id] = null;
        emitAdminToast({ type: 'success', message: response.data.message });
    } catch (error) {
        emitAdminToast({ type: 'error', errors: Object.values(error.response?.data?.errors ?? { message: [error.response?.data?.message ?? 'Import failed.'] }).flat() });
    } finally {
        importingGroupId.value = null;
    }
};

const selectionFor = (groupId) => {
    if (!recipientSelections.value[groupId]) {
        recipientSelections.value[groupId] = { customer_ids: [], lead_ids: [] };
    }

    return recipientSelections.value[groupId];
};

const groupRecipients = (group) => group.recipients ?? group.customers ?? [];

const existingRecipientKeySet = (group) => new Set(
    groupRecipients(group)
        .map((recipient) => `${recipient.source}:${String(recipient.email ?? '').toLowerCase()}`)
        .filter((value) => !value.endsWith(':')),
);

const optionMatchesGroupSearch = (group, option) => {
    const term = (recipientSearches.value[group.id] ?? '').trim().toLowerCase();

    if (!term) {
        return true;
    }

    return [option.label, option.email, option.phone]
        .filter(Boolean)
        .some((value) => value.toLowerCase().includes(term));
};

const recipientTypeFilterFor = (groupId) => recipientTypeFilters.value[groupId] ?? 'all';

const setRecipientTypeFilter = (groupId, type) => {
    recipientTypeFilters.value[groupId] = type;
};

const availableRecipientRows = (group) => {
    const existing = existingRecipientKeySet(group);
    const selectedType = recipientTypeFilterFor(group.id);
    const customers = recipientOptions.value.customers.map((customer) => ({
        ...customer,
        type: 'customer',
    }));
    const leads = recipientOptions.value.leads.map((lead) => ({
        ...lead,
        type: 'lead',
    }));

    return [...customers, ...leads]
        .filter((recipient) => selectedType === 'all' || recipient.type === selectedType)
        .filter((recipient) => !existing.has(`${recipient.type}:${String(recipient.email ?? '').toLowerCase()}`))
        .filter((recipient) => optionMatchesGroupSearch(group, recipient))
        .sort((first, second) => first.label.localeCompare(second.label));
};

const isRecipientSelected = (group, recipient) => {
    const selection = selectionFor(group.id);

    return recipient.type === 'customer'
        ? selection.customer_ids.includes(recipient.id)
        : selection.lead_ids.includes(recipient.id);
};

const setRecipientSelected = (group, recipient, checked) => {
    const selection = selectionFor(group.id);
    const key = recipient.type === 'customer' ? 'customer_ids' : 'lead_ids';

    selection[key] = checked
        ? [...new Set([...selection[key], recipient.id])]
        : selection[key].filter((id) => id !== recipient.id);
};

const selectVisibleRecipients = (group) => {
    for (const recipient of availableRecipientRows(group)) {
        setRecipientSelected(group, recipient, true);
    }
};

const clearRecipientSelection = (group) => {
    recipientSelections.value[group.id] = { customer_ids: [], lead_ids: [] };
};

const selectedRecipientCount = (group) => {
    const selection = selectionFor(group.id);

    return selection.customer_ids.length + selection.lead_ids.length;
};

const attachRecipients = async (group) => {
    const selection = selectionFor(group.id);

    if (!selection.customer_ids.length && !selection.lead_ids.length) {
        emitAdminToast({ type: 'error', errors: ['Select at least one customer or lead.'] });
        return;
    }

    attachingGroupId.value = group.id;

    try {
        const response = await axios.post(group.recipient_store_url, selection, {
            headers: { Accept: 'application/json' },
        });
        groups.value = groups.value.map((entry) => entry.id === group.id ? response.data.record : entry);
        recipientSelections.value[group.id] = { customer_ids: [], lead_ids: [] };
        emitAdminToast({ type: 'success', message: response.data.message });
    } catch (error) {
        emitAdminToast({ type: 'error', errors: Object.values(error.response?.data?.errors ?? { message: [error.response?.data?.message ?? 'Recipients could not be added.'] }).flat() });
    } finally {
        attachingGroupId.value = null;
    }
};
</script>

<template>
    <section class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-pink-200">Campaign Studio</p>
        <h2 class="text-sm font-bold italic text-white">Campaigns, templates, and subscriber groups</h2>
        <p class="text-sm text-stone-300">
            Build reusable rich email templates, maintain subscriber groups, import Excel CSV contact lists, and review campaign engagement.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 pb-3">
            <div class="flex flex-wrap gap-2">
                <button v-for="tab in ['campaigns', 'templates', 'groups']" :key="tab" type="button" class="rounded-xl px-4 py-2 text-sm font-semibold capitalize transition" :class="activeTab === tab ? 'bg-pink-300 text-slate-950' : 'border border-white/10 text-white hover:bg-white/5'" @click="openTab(tab)">
                    {{ tab }}
                </button>
            </div>
            <a :href="data.routes.create" class="rounded-xl bg-pink-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-pink-200">
                Create campaign
            </a>
        </div>

        <div v-if="activeTab === 'campaigns'" class="pt-4">
            <div class="grid gap-2 md:grid-cols-[minmax(0,1fr)_220px]">
                <input v-model="search" type="text" placeholder="Search campaigns" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-pink-300/50">
                <select v-model="selectedStatus" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-pink-300/50">
                    <option v-for="status in data.campaignStatuses ?? ['all']" :key="status" :value="status">{{ status === 'all' ? 'All statuses' : status.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase()) }}</option>
                </select>
            </div>

            <div class="mt-4 overflow-hidden rounded-2xl border border-white/10">
                <a v-for="campaign in filteredCampaigns" :key="campaign.id" :href="campaign.show_url" class="grid gap-3 border-b border-white/10 px-4 py-3 transition hover:bg-white/[0.03] lg:grid-cols-[minmax(0,1.3fr)_160px_110px_110px_110px]">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-white">{{ campaign.subject }}</p>
                        <p class="mt-1 truncate text-xs text-stone-400">{{ campaign.template_name || 'No template' }} · {{ campaign.preheader || campaign.headline || 'No preview text' }}</p>
                    </div>
                    <span class="text-sm font-medium" :class="campaign.status === 'sent' ? 'text-emerald-200' : 'text-amber-200'">{{ campaign.status_label }}</span>
                    <span class="text-sm text-stone-300">{{ campaign.sent_count }} sent</span>
                    <span class="text-sm text-stone-300">{{ campaign.opened_count }} opened</span>
                    <span class="text-sm text-stone-300">{{ campaign.unsubscribed_count }} unsubscribed</span>
                </a>
                <div v-if="!filteredCampaigns.length" class="px-4 py-8 text-sm text-stone-400">
                    No campaigns match the current filters.
                </div>
            </div>
        </div>

        <div v-else-if="activeTab === 'templates'" class="space-y-4 pt-4">
            <form class="rounded-3xl border border-white/10 bg-slate-950/40 p-4 shadow-2xl shadow-black/10" novalidate @submit.prevent="saveTemplate">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.3em] text-pink-200">Template Builder</p>
                        <h3 class="mt-1 text-sm font-semibold italic">Create reusable rich email template</h3>
                    </div>
                    <button type="submit" class="rounded-xl bg-pink-300 px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-pink-200 disabled:opacity-60" :disabled="savingTemplate">
                        {{ savingTemplate ? 'Saving...' : 'Save template' }}
                    </button>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-4">
                    <div>
                        <input v-model="templateForm.name" type="text" placeholder="Template name" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-pink-300/50" :class="firstError(templateErrors, 'name') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(templateErrors, 'name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(templateErrors, 'name') }}</p>
                    </div>
                    <div class="lg:col-span-2">
                        <input v-model="templateForm.subject" type="text" placeholder="Email subject" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-pink-300/50" :class="firstError(templateErrors, 'subject') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(templateErrors, 'subject')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(templateErrors, 'subject') }}</p>
                    </div>
                    <input v-model="templateForm.preheader" type="text" placeholder="Preview text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-pink-300/50">
                    <input v-model="templateForm.headline" type="text" placeholder="Header headline" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-pink-300/50 lg:col-span-2">
                    <input v-model="templateForm.button_text" type="text" placeholder="CTA button text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-pink-300/50">
                    <div>
                        <input v-model="templateForm.button_url" type="url" placeholder="CTA button URL" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-pink-300/50" :class="firstError(templateErrors, 'button_url') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(templateErrors, 'button_url')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(templateErrors, 'button_url') }}</p>
                    </div>
                </div>

                <div class="mt-5 overflow-hidden rounded-2xl border border-white/10 bg-white">
                    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-slate-100 px-3 py-2">
                        <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 transition hover:bg-pink-50" @click="runTemplateCommand('bold')">Bold</button>
                        <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 transition hover:bg-pink-50" @click="runTemplateCommand('italic')">Italic</button>
                        <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 transition hover:bg-pink-50" @click="runTemplateCommand('underline')">Underline</button>
                        <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 transition hover:bg-pink-50" @click="runTemplateCommand('formatBlock', 'h2')">Heading</button>
                        <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 transition hover:bg-pink-50" @click="runTemplateCommand('formatBlock', 'p')">Paragraph</button>
                        <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 transition hover:bg-pink-50" @click="runTemplateCommand('insertUnorderedList')">Bullets</button>
                        <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 transition hover:bg-pink-50" @click="runTemplateCommand('insertOrderedList')">Numbers</button>
                        <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 transition hover:bg-pink-50" @click="addTemplateLink">Link</button>
                        <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 transition hover:bg-pink-50" @click="runTemplateCommand('removeFormat')">Clear</button>
                    </div>

                    <div
                        ref="templateEditor"
                        contenteditable="true"
                        class="min-h-[420px] px-8 py-7 text-base leading-8 text-slate-900 outline-none focus:ring-4 focus:ring-pink-200/60"
                        :class="firstError(templateErrors, 'html_body') ? 'ring-4 ring-rose-300/60' : ''"
                        @input="updateTemplateBody"
                        v-html="templateForm.html_body"
                    />
                </div>
                <p v-if="firstError(templateErrors, 'html_body')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(templateErrors, 'html_body') }}</p>

                <div class="mt-4 rounded-2xl border border-pink-200/20 bg-pink-200/10 px-4 py-3 text-sm leading-6 text-pink-50">
                    Personalization placeholders can be typed directly into the body, for example <span class="font-semibold" v-text="'{{ first_name }}'" />.
                </div>
            </form>

            <section class="rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Saved Templates</p>
                        <h3 class="mt-1 text-sm font-semibold italic">{{ templates.length }} template{{ templates.length === 1 ? '' : 's' }}</h3>
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="template in templates" :key="template.id" class="rounded-2xl border border-white/10 bg-slate-950/50 p-4">
                        <p class="text-sm font-semibold text-white">{{ template.name }}</p>
                        <p class="mt-1 text-xs text-stone-400">{{ template.subject }}</p>
                        <div class="mt-3 max-h-32 overflow-hidden rounded-xl border border-white/10 bg-white px-3 py-2 text-sm text-slate-800" v-html="template.html_body" />
                    </article>
                </div>
                <p v-if="!templates.length" class="rounded-2xl border border-dashed border-white/15 px-4 py-6 text-sm text-stone-400">Create your first reusable template.</p>
            </section>
        </div>

        <div v-else class="grid gap-4 pt-4 xl:grid-cols-[320px_minmax(0,1fr)]">
            <aside class="rounded-2xl border border-white/10 bg-slate-950/40 p-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Groups</p>
                        <p class="mt-1 text-xs text-stone-500">{{ groups.length }} subscriber group{{ groups.length === 1 ? '' : 's' }}</p>
                    </div>
                </div>

                <div class="mt-3 max-h-[680px] space-y-2 overflow-y-auto pr-1">
                    <button v-for="group in groups" :key="group.id" type="button" class="w-full rounded-xl border px-3 py-3 text-left transition" :class="selectedGroup?.id === group.id ? 'border-pink-300/60 bg-pink-300/10 shadow-lg shadow-pink-950/10' : 'border-white/10 bg-white/[0.03] hover:bg-white/[0.06]'" @click="activeGroupId = group.id">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-white">{{ group.name }}</p>
                                <p class="mt-1 line-clamp-2 text-xs leading-5 text-stone-400">{{ group.description || 'No description' }}</p>
                            </div>
                            <span class="shrink-0 rounded-lg bg-white/[0.06] px-2 py-1 text-xs text-stone-300">{{ group.customers_count }}</span>
                        </div>
                    </button>
                </div>

                <p v-if="!groups.length" class="mt-3 rounded-2xl border border-dashed border-white/15 px-4 py-6 text-sm text-stone-400">Create a subscriber group to start adding members.</p>

                <div class="mt-4 rounded-2xl border border-pink-200/15 bg-pink-200/[0.05] p-3">
                    <p class="text-[11px] uppercase tracking-[0.24em] text-pink-200">New Subscriber Group</p>
                    <p class="mt-2 text-xs leading-5 text-stone-400">Create a group first, then select it here to manage members and imports.</p>
                    <button type="button" class="mt-3 w-full rounded-xl bg-pink-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-pink-200" @click="openGroupModal">
                        Add group
                    </button>
                </div>
            </aside>

            <article v-if="selectedGroup" class="rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.3em] text-pink-200">Group Details</p>
                        <h3 class="mt-1 text-sm font-semibold italic text-white">{{ selectedGroup.name }}</h3>
                        <p class="mt-1 text-sm leading-6 text-stone-400">{{ selectedGroup.description || 'No description' }}</p>
                    </div>
                    <span class="rounded-xl bg-white/[0.05] px-3 py-2 text-sm text-stone-300">{{ selectedGroup.customers_count }} users</span>
                </div>

                <div v-if="groupRecipients(selectedGroup).length" class="mt-4 rounded-2xl border border-white/10 bg-white/[0.03] p-3">
                    <p class="text-[11px] uppercase tracking-[0.28em] text-stone-400">Current Members</p>
                    <div class="mt-3 max-h-48 overflow-auto rounded-xl border border-white/10">
                        <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                            <thead class="sticky top-0 z-10 bg-slate-950/95 text-[11px] uppercase tracking-[0.18em] text-stone-400">
                                <tr>
                                    <th class="px-3 py-2">Name</th>
                                    <th class="px-3 py-2">Email</th>
                                    <th class="px-3 py-2">Phone</th>
                                    <th class="px-3 py-2">Type</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 bg-slate-950/35">
                                <tr v-for="recipient in groupRecipients(selectedGroup)" :key="recipient.id" class="transition hover:bg-white/[0.04]">
                                    <td class="max-w-[220px] truncate px-3 py-2 font-medium text-white">{{ recipient.full_name || recipient.email }}</td>
                                    <td class="max-w-[240px] truncate px-3 py-2 text-stone-300">{{ recipient.email || 'No email' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-stone-400">{{ recipient.phone || 'No phone' }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide" :class="recipient.source === 'lead' ? 'bg-amber-200/15 text-amber-100' : 'bg-emerald-200/15 text-emerald-100'">{{ recipient.source }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-else class="mt-4 rounded-2xl border border-dashed border-white/15 px-4 py-6 text-sm text-stone-400">
                    This group has no members yet. Add existing CRM records below or import a CSV.
                </div>

                <div class="mt-4 rounded-2xl border border-pink-200/15 bg-pink-200/[0.04] p-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.28em] text-pink-200">Add From CRM</p>
                            <p class="mt-1 text-xs text-stone-400">Select existing customers and leads without uploading a file.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-lg border border-white/10 bg-slate-950/50 px-2.5 py-1 text-xs text-stone-300">{{ selectedRecipientCount(selectedGroup) }} selected</span>
                            <button type="button" class="rounded-xl border border-white/10 px-3 py-2 text-xs font-semibold text-white transition hover:bg-white/5" @click="selectVisibleRecipients(selectedGroup)">
                                Select all visible
                            </button>
                            <button type="button" class="rounded-xl border border-white/10 px-3 py-2 text-xs font-semibold text-white transition hover:bg-white/5" @click="clearRecipientSelection(selectedGroup)">
                                Clear
                            </button>
                            <button type="button" class="rounded-xl bg-pink-300 px-4 py-2 text-xs font-semibold text-slate-950 transition hover:bg-pink-200 disabled:opacity-60" :disabled="attachingGroupId === selectedGroup.id" @click="attachRecipients(selectedGroup)">
                                {{ attachingGroupId === selectedGroup.id ? 'Adding...' : 'Add selected' }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-3 grid gap-2 lg:grid-cols-[minmax(0,1fr)_auto]">
                        <input v-model="recipientSearches[selectedGroup.id]" type="search" placeholder="Search name, email, or phone" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-pink-300/50">
                        <div class="inline-flex rounded-xl border border-white/10 bg-slate-950/70 p-1">
                            <button v-for="type in ['all', 'customer', 'lead']" :key="type" type="button" class="rounded-lg px-3 py-1.5 text-xs font-semibold capitalize transition" :class="recipientTypeFilterFor(selectedGroup.id) === type ? 'bg-pink-300 text-slate-950' : 'text-stone-300 hover:bg-white/5 hover:text-white'" @click="setRecipientTypeFilter(selectedGroup.id, type)">
                                {{ type === 'all' ? 'All' : `${type}s` }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-3 max-h-64 overflow-auto rounded-2xl border border-white/10">
                        <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                            <thead class="sticky top-0 z-10 bg-slate-950/95 text-[11px] uppercase tracking-[0.18em] text-stone-400">
                                <tr>
                                    <th class="w-10 px-3 py-2">Pick</th>
                                    <th class="px-3 py-2">Name</th>
                                    <th class="px-3 py-2">Email</th>
                                    <th class="px-3 py-2">Phone</th>
                                    <th class="px-3 py-2">Type</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 bg-slate-950/35">
                                <tr v-for="recipient in availableRecipientRows(selectedGroup)" :key="`${recipient.type}-${recipient.id}`" class="transition hover:bg-white/[0.04]">
                                    <td class="px-3 py-2">
                                        <input type="checkbox" class="rounded border-white/20 bg-slate-950 text-pink-300 focus:ring-pink-300" :checked="isRecipientSelected(selectedGroup, recipient)" @change="setRecipientSelected(selectedGroup, recipient, $event.target.checked)">
                                    </td>
                                    <td class="max-w-[220px] truncate px-3 py-2 font-medium text-white">{{ recipient.label }}</td>
                                    <td class="max-w-[240px] truncate px-3 py-2 text-stone-300">{{ recipient.email || 'No email' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-stone-400">{{ recipient.phone || 'No phone' }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide" :class="recipient.type === 'lead' ? 'bg-amber-200/15 text-amber-100' : 'bg-emerald-200/15 text-emerald-100'">{{ recipient.type }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="!availableRecipientRows(selectedGroup).length" class="px-4 py-6 text-center text-xs text-stone-500">No matching recipients available.</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <input type="file" accept=".csv,text/csv" class="rounded-xl border border-dashed border-white/15 bg-slate-950/70 px-3 py-2 text-sm text-stone-300 file:mr-3 file:rounded-lg file:border-0 file:bg-pink-200 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-slate-950" @change="importFiles[selectedGroup.id] = $event.target.files?.[0] ?? null">
                    <button type="button" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5 disabled:opacity-60" :disabled="importingGroupId === selectedGroup.id" @click="importGroup(selectedGroup)">
                        {{ importingGroupId === selectedGroup.id ? 'Importing...' : 'Import CSV' }}
                    </button>
                </div>
                <p class="mt-2 text-xs text-stone-500">CSV headers: first_name, last_name, phone, email.</p>
            </article>

            <div v-else class="rounded-2xl border border-dashed border-white/15 px-4 py-10 text-center text-sm text-stone-400">
                Select or create a subscriber group to view details.
            </div>

        </div>
    </section>

    <Teleport to="body">
        <div v-if="showGroupModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 px-4 py-6 backdrop-blur-sm" @click.self="closeGroupModal">
            <form class="w-full max-w-xl rounded-3xl border border-white/10 bg-slate-950 p-5 shadow-2xl shadow-black/40" novalidate @submit.prevent="saveGroup">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.3em] text-pink-200">New Subscriber Group</p>
                        <h3 class="mt-1 text-sm font-semibold italic text-white">Create group</h3>
                        <p class="mt-1 text-sm leading-6 text-stone-400">Give this group a clear name so it is easy to find in the left-side group list.</p>
                    </div>
                    <button type="button" class="rounded-xl border border-white/10 px-3 py-2 text-sm font-semibold text-white transition hover:bg-white/5 disabled:opacity-60" :disabled="savingGroup" @click="closeGroupModal">
                        Close
                    </button>
                </div>

                <div class="mt-5 space-y-3">
                    <div>
                        <input v-model="groupForm.name" type="text" autofocus placeholder="Group name" class="w-full rounded-xl border border-white/10 bg-slate-900/80 px-3 py-3 text-sm text-white outline-none focus:border-pink-300/50" :class="firstError(groupErrors, 'name') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(groupErrors, 'name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(groupErrors, 'name') }}</p>
                    </div>
                    <textarea v-model="groupForm.description" rows="5" placeholder="Who belongs in this group?" class="w-full rounded-xl border border-white/10 bg-slate-900/80 px-3 py-3 text-sm leading-6 text-white outline-none focus:border-pink-300/50" />
                </div>

                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button type="button" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/5 disabled:opacity-60" :disabled="savingGroup" @click="closeGroupModal">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-xl bg-pink-300 px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-pink-200 disabled:opacity-60" :disabled="savingGroup">
                        {{ savingGroup ? 'Saving...' : 'Save group' }}
                    </button>
                </div>
            </form>
        </div>
    </Teleport>
</template>
