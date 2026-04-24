<script setup>
import { computed, ref } from 'vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const { saving, submitForm } = useWorkspaceCrud();
const emailLogs = ref([...(props.data.emailLogs ?? [])]);
const selectedIds = ref([]);
const activeLogId = ref(props.data.emailLogs?.[0]?.id ?? null);

const activeLog = computed(() => emailLogs.value.find((entry) => entry.id === activeLogId.value) ?? null);
const allSelected = computed(() => emailLogs.value.length > 0 && selectedIds.value.length === emailLogs.value.length);

const toggleAll = () => {
    selectedIds.value = allSelected.value ? [] : emailLogs.value.map((entry) => entry.id);
};

const toggleSelection = (id) => {
    selectedIds.value = selectedIds.value.includes(id)
        ? selectedIds.value.filter((entry) => entry !== id)
        : [...selectedIds.value, id];
};

const showDetail = (log) => {
    activeLogId.value = log.id;
};

const resendLog = async (log) => {
    try {
        const record = await submitForm({
            url: log.resend_url,
            data: {},
        });

        emailLogs.value = [record, ...emailLogs.value];
        activeLogId.value = record.id;
    } catch (error) {
        const failedRecord = error?.response?.data?.record;

        if (failedRecord) {
            emailLogs.value = [failedRecord, ...emailLogs.value];
            activeLogId.value = failedRecord.id;
        }
    }
};

const deleteSelected = async () => {
    if (!selectedIds.value.length) {
        return;
    }

    try {
        await submitForm({
            url: props.data.routes.bulkDelete,
            data: {
                email_log_ids: selectedIds.value,
            },
        });

        emailLogs.value = emailLogs.value.filter((entry) => !selectedIds.value.includes(entry.id));

        if (selectedIds.value.includes(activeLogId.value)) {
            activeLogId.value = emailLogs.value[0]?.id ?? null;
        }

        selectedIds.value = [];
    } catch {}
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-cyan-200">Email Tracking</p>
        <h2 class="text-sm font-bold italic text-white">Sent emails and delivery status</h2>
        <p class="text-sm text-stone-300">
            Review outgoing emails, inspect the content, resend them, or remove selected records.
        </p>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(22rem,0.8fr)]">
        <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03]">
            <div class="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Email List</p>
                    <h3 class="mt-1 text-sm font-semibold italic">{{ emailLogs.length }} email{{ emailLogs.length === 1 ? '' : 's' }}</h3>
                </div>
                <button type="button" class="rounded-xl border border-rose-400/30 px-4 py-2 text-sm font-semibold text-rose-100 transition hover:bg-rose-400/10 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving || !selectedIds.length" @click="deleteSelected">
                    {{ saving ? 'Working...' : `Delete selected${selectedIds.length ? ` (${selectedIds.length})` : ''}` }}
                </button>
            </div>

            <div v-if="emailLogs.length" class="overflow-x-auto">
                <div class="min-w-[920px]">
                    <div class="grid grid-cols-[3rem_minmax(0,1.4fr)_minmax(0,1.4fr)_8rem_11rem_9rem] gap-3 border-b border-white/10 px-4 py-3 text-xs font-medium uppercase tracking-[0.2em] text-stone-400">
                        <label class="flex items-center justify-center">
                            <input :checked="allSelected" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-slate-950 text-cyan-300" @change="toggleAll">
                        </label>
                        <span>Recipient</span>
                        <span>Subject</span>
                        <span>Status</span>
                        <span>Sent</span>
                        <span>Actions</span>
                    </div>

                    <div v-for="log in emailLogs" :key="log.id" class="grid grid-cols-[3rem_minmax(0,1.4fr)_minmax(0,1.4fr)_8rem_11rem_9rem] items-center gap-3 border-b border-white/10 px-4 py-3 text-sm last:border-b-0" :class="activeLogId === log.id ? 'bg-cyan-300/5' : ''">
                        <label class="flex items-center justify-center">
                            <input :checked="selectedIds.includes(log.id)" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-slate-950 text-cyan-300" @change="toggleSelection(log.id)">
                        </label>
                        <div class="min-w-0">
                            <p class="truncate font-medium text-white">{{ log.recipient_label }}</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.18em] text-stone-500">{{ log.recipient_type }}</p>
                        </div>
                        <p class="truncate text-stone-200">{{ log.subject }}</p>
                        <span class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium" :class="log.status === 'sent' ? 'bg-emerald-400/15 text-emerald-200' : 'bg-rose-400/15 text-rose-200'">
                            {{ log.status_label }}
                        </span>
                        <p class="text-stone-300">{{ log.sent_at_label }}</p>
                        <div class="flex items-center gap-2">
                            <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/5" @click="showDetail(log)">Detail</button>
                            <button type="button" class="rounded-lg border border-cyan-300/30 px-3 py-1.5 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-300/10" :disabled="saving" @click="resendLog(log)">Resend</button>
                        </div>
                    </div>
                </div>
            </div>

            <p v-else class="px-4 py-8 text-sm text-stone-400">No tracked emails yet.</p>
        </div>

        <div class="rounded-2xl border border-white/10 bg-white/[0.03]">
            <div class="border-b border-white/10 px-4 py-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Email Detail</p>
                <h3 class="mt-1 text-sm font-semibold italic">{{ activeLog?.subject || 'Select an email' }}</h3>
            </div>

            <div v-if="activeLog" class="space-y-4 p-4">
                <div class="grid gap-3 rounded-xl border border-white/10 bg-slate-950/50 p-3 text-sm text-stone-300">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.18em] text-stone-500">Recipient</p>
                        <p class="mt-1 text-white">{{ activeLog.recipient_label }}</p>
                    </div>
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.18em] text-stone-500">Status</p>
                            <p class="mt-1 text-white">{{ activeLog.status_label }}</p>
                            <p v-if="activeLog.error_message" class="mt-1 text-rose-200">{{ activeLog.error_message }}</p>
                        </div>
                        <div v-if="activeLog.attachments?.length">
                            <p class="text-[11px] uppercase tracking-[0.18em] text-stone-500">Attachments</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span
                                    v-for="attachment in activeLog.attachments"
                                    :key="`${attachment.name}-${attachment.mime}`"
                                    class="inline-flex items-center rounded-full border border-cyan-300/20 bg-cyan-300/10 px-2.5 py-1 text-xs font-medium text-cyan-100"
                                >
                                    {{ attachment.name }}
                                </span>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="rounded-lg border border-cyan-300/30 px-3 py-1.5 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-300/10" :disabled="saving" @click="resendLog(activeLog)">Resend</button>
                        </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-white/10 bg-white">
                    <div class="border-b border-stone-200 px-3 py-2 text-xs font-medium uppercase tracking-[0.18em] text-stone-500">
                        Email Content
                    </div>
                    <div class="max-h-[42rem] overflow-auto p-3 text-sm text-stone-900" v-html="activeLog.html_content" />
                </div>
            </div>

            <p v-else class="px-4 py-8 text-sm text-stone-400">Select an email from the list to inspect its recipient and content.</p>
        </div>
    </section>
</template>
