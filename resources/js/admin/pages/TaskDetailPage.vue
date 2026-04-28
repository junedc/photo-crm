<script setup>
import axios from 'axios';
import { computed, ref, watch } from 'vue';
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
const deleting = ref(false);
const clientErrors = ref({});
const showDeleteConfirm = ref(false);
const taskRecord = ref(props.data.task);
const taskAttachmentInput = ref(null);
const taskStatuses = computed(() => props.data.taskStatuses ?? []);
const defaultTaskStatusId = computed(() => String(taskStatuses.value.find((status) => String(status.name ?? '').toLowerCase() === 'new')?.id ?? taskStatuses.value[0]?.id ?? ''));
const bookings = computed(() => props.data.bookings ?? []);
const baseAssigneeOptions = computed(() => props.data.assigneeOptions ?? []);
const isCreate = computed(() => !taskRecord.value?.id);

const buildForm = (task = null) => ({
    task_name: task?.task_name ?? '',
    task_duration_hours: task?.task_duration_hours ?? '',
    assigned_to: task?.assigned_to ? String(task.assigned_to) : '',
    booking_id: task?.booking_id ? String(task.booking_id) : '',
    task_status_id: task?.task_status_id ? String(task.task_status_id) : defaultTaskStatusId.value,
    remarks: task?.remarks ?? '',
    due_date: task?.due_date ?? '',
    date_started: task?.date_started ?? '',
    date_completed: task?.date_completed ?? '',
    attachments: [],
});

const form = ref(buildForm(taskRecord.value));
const validationErrors = computed(() => mergeFieldErrors(clientErrors.value, fieldErrors.value));
const selectedBooking = computed(() => bookings.value.find((booking) => String(booking.id) === String(form.value.booking_id ?? '')) ?? null);
const assigneeGroups = computed(() => {
    const options = [...baseAssigneeOptions.value];

    if (selectedBooking.value?.customer_assignee && !options.some((option) => option.value === selectedBooking.value.customer_assignee.value)) {
        options.push(selectedBooking.value.customer_assignee);
    }

    return options.reduce((groups, option) => {
        const group = option.group ?? 'Other';
        groups[group] = [...(groups[group] ?? []), option];
        return groups;
    }, {});
});

watch(defaultTaskStatusId, (value) => {
    if (isCreate.value && isBlank(form.value.task_status_id) && value) {
        form.value.task_status_id = value;
    }
}, { immediate: true });

const validateForm = () => {
    const errors = {};

    if (isBlank(form.value.task_name)) {
        errors.task_name = requiredMessage('Task name');
    }

    clientErrors.value = errors;

    return !hasFieldErrors(errors);
};

const syncTaskAttachments = (event) => {
    form.value.attachments = Array.from(event.target.files ?? []);
};

const resetFileInput = () => {
    if (taskAttachmentInput.value) {
        taskAttachmentInput.value.value = '';
    }
};

const saveTask = async () => {
    if (!validateForm()) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('task_name', form.value.task_name ?? '');
        formData.append('task_duration_hours', form.value.task_duration_hours ?? '');
        formData.append('assigned_to', form.value.assigned_to ?? '');
        formData.append('booking_id', form.value.booking_id ?? '');
        formData.append('task_status_id', form.value.task_status_id ?? '');
        formData.append('remarks', form.value.remarks ?? '');
        formData.append('due_date', form.value.due_date ?? '');
        formData.append('date_started', form.value.date_started ?? '');
        formData.append('date_completed', form.value.date_completed ?? '');
        (form.value.attachments ?? []).forEach((file) => {
            formData.append('attachments[]', file);
        });

        if (!isCreate.value) {
            formData.append('_method', 'PUT');
        }

        const record = await submitForm({
            url: taskRecord.value?.update_url ?? props.data.routes.store,
            method: 'post',
            data: formData,
        });

        taskRecord.value = record;
        form.value = buildForm(record);
        clientErrors.value = {};
        resetFileInput();
        window.history.replaceState({}, '', record.show_url);
    } catch {}
};

const askDeleteTask = () => {
    showDeleteConfirm.value = true;
};

const cancelDeleteTask = () => {
    showDeleteConfirm.value = false;
};

const deleteTask = async () => {
    if (!taskRecord.value?.delete_url) {
        return;
    }

    deleting.value = true;

    try {
        await axios.delete(taskRecord.value.delete_url, {
            headers: {
                Accept: 'application/json',
            },
        });

        window.location.href = props.data.routes.tasks;
    } catch {
        emitAdminToast({
            type: 'error',
            errors: ['Unable to delete this task right now.'],
        });
    } finally {
        deleting.value = false;
        showDeleteConfirm.value = false;
    }
};

const pageTitle = computed(() => taskRecord.value?.task_name || 'New Task');
const statusBadgeLabel = computed(() => taskRecord.value?.status_label || (isCreate.value ? 'New' : 'No status'));
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-sky-200">Tasks Workspace</p>
        <h2 class="min-w-0 text-sm font-bold italic text-white">{{ pageTitle }}</h2>
        <p class="text-sm text-stone-300">
            {{ isCreate ? 'Create a new task and assign it cleanly.' : 'Review the task, attached files, and customer response in one place.' }}
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[11px] uppercase tracking-[0.3em] text-sky-200">{{ isCreate ? 'New Task' : 'Task Details' }}</p>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <h3 class="min-w-0 text-sm font-semibold italic text-white">{{ pageTitle }}</h3>
                    <span class="inline-flex max-w-full break-words rounded-full border border-cyan-300/20 bg-cyan-300/10 px-3 py-1 text-xs font-medium leading-5 text-cyan-100">
                        {{ statusBadgeLabel }}
                    </span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a :href="data.routes.tasks" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">
                    Back to list
                </a>
                <button
                    v-if="!isCreate"
                    type="button"
                    class="rounded-xl border border-rose-400/30 px-4 py-2 text-sm font-semibold text-rose-100 transition hover:bg-rose-400/10 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="deleting"
                    @click="askDeleteTask"
                >
                    {{ deleting ? 'Deleting...' : 'Delete task' }}
                </button>
            </div>
        </div>

        <form class="space-y-4" novalidate @submit.prevent="saveTask">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Task Name</label>
                    <input v-model="form.task_name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300/50" :class="firstError(validationErrors, 'task_name') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'task_name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'task_name') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Status</label>
                    <select v-model="form.task_status_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300/50" :class="firstError(validationErrors, 'task_status_id') ? 'border-rose-300/60' : ''">
                        <option v-for="status in taskStatuses" :key="status.id" :value="String(status.id)">{{ status.label ?? status.name }}</option>
                    </select>
                    <p v-if="firstError(validationErrors, 'task_status_id')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'task_status_id') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Assigned To</label>
                    <select v-model="form.assigned_to" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300/50" :class="firstError(validationErrors, 'assigned_to') ? 'border-rose-300/60' : ''">
                        <option value="">Unassigned</option>
                        <optgroup v-for="(options, group) in assigneeGroups" :key="group" :label="group">
                            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </optgroup>
                    </select>
                    <p v-if="firstError(validationErrors, 'assigned_to')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'assigned_to') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Attached Booking</label>
                    <select v-model="form.booking_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300/50" :class="firstError(validationErrors, 'booking_id') ? 'border-rose-300/60' : ''">
                        <option value="">General task</option>
                        <option v-for="booking in bookings" :key="booking.id" :value="String(booking.id)">{{ [booking.quote_number, booking.display_name, booking.event_date_label].filter(Boolean).join(' - ') }}</option>
                    </select>
                    <p v-if="firstError(validationErrors, 'booking_id')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'booking_id') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Task Duration In Hours</label>
                    <input v-model="form.task_duration_hours" type="number" min="0" step="0.25" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300/50" :class="firstError(validationErrors, 'task_duration_hours') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'task_duration_hours')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'task_duration_hours') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Due Date</label>
                    <input v-model="form.due_date" type="date" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300/50" :class="firstError(validationErrors, 'due_date') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'due_date')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'due_date') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Date Started</label>
                    <input v-model="form.date_started" type="date" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300/50" :class="firstError(validationErrors, 'date_started') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'date_started')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'date_started') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Date Completed</label>
                    <input v-model="form.date_completed" type="date" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300/50" :class="firstError(validationErrors, 'date_completed') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'date_completed')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'date_completed') }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Remarks</label>
                    <textarea v-model="form.remarks" rows="4" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300/50" :class="firstError(validationErrors, 'remarks') ? 'border-rose-300/60' : ''" />
                    <p v-if="firstError(validationErrors, 'remarks')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'remarks') }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Task Files</label>
                    <input ref="taskAttachmentInput" type="file" multiple accept="image/*,video/*,.pdf" class="block w-full rounded-xl border border-dashed border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-stone-300 file:mr-3 file:rounded-full file:border-0 file:bg-sky-300/15 file:px-3 file:py-2 file:text-xs file:font-medium file:text-sky-100 hover:file:bg-sky-300/20" @change="syncTaskAttachments">
                    <p class="mt-1 text-xs text-stone-400">Upload files the assignee should be able to view. New uploads are added to any files already on the task.</p>
                    <p v-if="firstError(validationErrors, 'attachments')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'attachments') }}</p>
                    <p v-else-if="firstError(validationErrors, 'attachments.0')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'attachments.0') }}</p>
                </div>
                <div v-if="taskRecord?.task_attachments?.length" class="sm:col-span-2 rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                    <p class="text-[11px] uppercase tracking-[0.24em] text-stone-400">Existing Files</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <a
                            v-for="attachment in taskRecord.task_attachments"
                            :key="`${taskRecord.id}-${attachment.url}`"
                            :href="attachment.url"
                            target="_blank"
                            rel="noreferrer"
                            class="inline-flex items-center rounded-full border border-sky-300/20 bg-sky-300/10 px-3 py-1 text-xs font-medium text-sky-100 transition hover:bg-sky-300/15"
                        >
                            {{ attachment.name }}
                        </a>
                    </div>
                </div>
                <div v-if="taskRecord" class="sm:col-span-2 rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.28em] text-stone-400">Customer Response</p>
                            <p class="mt-1 text-sm text-stone-300">Latest reply and attachments from the customer portal.</p>
                        </div>
                        <span class="inline-flex max-w-full break-words rounded-full border border-white/10 bg-white/[0.03] px-3 py-1 text-xs leading-5 text-stone-300">
                            {{ taskRecord.customer_response_at_label || 'No reply yet' }}
                        </span>
                    </div>
                    <div class="mt-4 rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3">
                        <p class="text-sm leading-6 text-white">{{ taskRecord.customer_response_note || 'No customer reply yet.' }}</p>
                    </div>
                    <div v-if="taskRecord.customer_response_attachments?.length" class="mt-4">
                        <p class="text-[11px] uppercase tracking-[0.24em] text-stone-400">Attachments</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <a
                                v-for="attachment in taskRecord.customer_response_attachments"
                                :key="`${taskRecord.id}-${attachment.url}`"
                                :href="attachment.url"
                                target="_blank"
                                rel="noreferrer"
                                class="inline-flex items-center rounded-full border border-sky-300/20 bg-sky-300/10 px-3 py-1 text-xs font-medium text-sky-100 transition hover:bg-sky-300/15"
                            >
                                {{ attachment.name }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3">
                <button type="submit" class="rounded-xl bg-sky-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-sky-200 disabled:opacity-60" :disabled="saving">
                    {{ saving ? 'Saving...' : (isCreate ? 'Create task' : 'Save changes') }}
                </button>
            </div>
        </form>
    </section>

    <ConfirmDialog
        :open="showDeleteConfirm"
        title="Delete task?"
        :message="`Are you sure you want to delete the record ${taskRecord?.task_name || 'this task'}?`"
        confirm-label="Delete task"
        :loading="deleting"
        @cancel="cancelDeleteTask"
        @confirm="deleteTask"
    />
</template>
