<script setup>
import { computed, onMounted, ref } from 'vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';
import { firstError } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const { saving, fieldErrors, submitForm, deleteRecord } = useWorkspaceCrud();
const tasks = ref([...(props.data.tasks ?? [])]);
const taskStatuses = computed(() => props.data.taskStatuses ?? []);
const defaultTaskStatusId = computed(() => String(taskStatuses.value.find((status) => String(status.name ?? '').toLowerCase() === 'new')?.id ?? ''));
const baseAssigneeOptions = computed(() => props.data.assigneeOptions ?? []);
const bookings = computed(() => props.data.bookings ?? []);
const editingTask = ref(null);
const showModal = ref(false);
const form = ref(buildForm());
const taskToDelete = ref(null);
const showDeleteConfirm = ref(false);

function buildForm(task = null) {
    return {
        task_name: task?.task_name ?? '',
        task_duration_hours: task?.task_duration_hours ?? '',
        assigned_to: task?.assigned_to ? String(task.assigned_to) : '',
        booking_id: task?.booking_id ? String(task.booking_id) : '',
        task_status_id: task?.task_status_id ? String(task.task_status_id) : defaultTaskStatusId.value,
        remarks: task?.remarks ?? '',
        due_date: task?.due_date ?? '',
        date_started: task?.date_started ?? '',
        date_completed: task?.date_completed ?? '',
    };
}
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

const openCreate = () => {
    editingTask.value = null;
    form.value = buildForm();
    showModal.value = true;
};

const openEdit = (task) => {
    editingTask.value = task;
    form.value = buildForm(task);
    showModal.value = true;
};

const openTaskFromQuery = () => {
    const params = new URLSearchParams(window.location.search);
    const taskId = params.get('task');

    if (!taskId) {
        return;
    }

    const matchedTask = tasks.value.find((task) => String(task.id) === String(taskId));

    if (matchedTask) {
        openEdit(matchedTask);
    }
};

const closeModal = () => {
    showModal.value = false;
    editingTask.value = null;

    const url = new URL(window.location.href);

    if (url.searchParams.has('task')) {
        url.searchParams.delete('task');
        window.history.replaceState({}, '', url.toString());
    }
};

const bookingLabel = (booking) => [booking.quote_number, booking.display_name, booking.event_date_label].filter(Boolean).join(' - ');

const saveTask = async () => {
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

    if (editingTask.value) {
        formData.append('_method', 'PUT');
    }

    const record = await submitForm({
        url: editingTask.value?.update_url ?? props.data.routes.store,
        method: 'post',
        data: formData,
    });

    const index = tasks.value.findIndex((task) => task.id === record.id);
    tasks.value = index >= 0
        ? tasks.value.map((task) => (task.id === record.id ? record : task))
        : [record, ...tasks.value];
    closeModal();
};

const askRemoveTask = (task) => {
    taskToDelete.value = task;
    showDeleteConfirm.value = true;
};

const cancelRemoveTask = () => {
    taskToDelete.value = null;
    showDeleteConfirm.value = false;
};

const removeTask = async () => {
    if (!taskToDelete.value?.delete_url) {
        return;
    }

    await deleteRecord({ url: taskToDelete.value.delete_url });
    tasks.value = tasks.value.filter((entry) => entry.id !== taskToDelete.value.id);
    cancelRemoveTask();
};

onMounted(() => {
    openTaskFromQuery();
});
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-sky-200">Tasks Workspace</p>
        <h2 class="text-sm font-bold italic text-white">Manage team tasks</h2>
        <p class="text-sm text-stone-300">Track remarks, status, owners, and booking-linked work in one list.</p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
        <div class="mb-3 flex items-center justify-between gap-3 border-b border-white/10 px-2 pb-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Tasks</p>
                <h3 class="mt-1 text-sm font-semibold italic">{{ tasks.length }} task{{ tasks.length === 1 ? '' : 's' }}</h3>
            </div>
            <button type="button" class="rounded-xl bg-sky-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-200" @click="openCreate">
                Create task
            </button>
        </div>

        <div class="overflow-x-auto">
            <div class="min-w-[1180px]">
                <div class="grid grid-cols-[minmax(0,1.3fr)_10rem_10rem_13rem_8rem_8rem_8rem_14rem_9rem] gap-3 px-3 py-2 text-[11px] uppercase tracking-[0.2em] text-stone-500">
                    <span>Task</span>
                    <span>Status</span>
                    <span>Assigned To</span>
                    <span>Booking</span>
                    <span>Hours</span>
                    <span>Due Date</span>
                    <span>Started</span>
                    <span>Remarks</span>
                    <span>Actions</span>
                </div>
                <div v-for="task in tasks" :key="task.id" class="grid grid-cols-[minmax(0,1.3fr)_10rem_10rem_13rem_8rem_8rem_8rem_14rem_9rem] items-center gap-3 border-t border-white/10 px-3 py-2">
                    <p class="truncate text-sm font-medium text-white">{{ task.task_name }}</p>
                    <p class="truncate text-sm text-cyan-100">{{ task.status_label || 'No status' }}</p>
                    <p class="truncate text-sm text-sky-100">{{ task.assigned_to_name }}</p>
                    <p class="truncate text-sm text-stone-300">{{ task.booking_label || 'General task' }}</p>
                    <p class="text-sm text-stone-300">{{ task.task_duration_hours || '0.00' }}</p>
                    <p class="text-sm text-stone-300">{{ task.due_date_label }}</p>
                    <p class="text-sm text-stone-300">{{ task.date_started_label }}</p>
                    <p class="truncate text-sm text-stone-400">{{ task.remarks || 'No remarks' }}</p>
                    <div class="flex items-center gap-2">
                        <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/5" @click="openEdit(task)">Edit</button>
                        <button type="button" class="rounded-lg border border-rose-400/30 px-3 py-1.5 text-xs font-semibold text-rose-100 transition hover:bg-rose-400/10" @click="askRemoveTask(task)">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <transition name="modal">
        <div v-if="showModal" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm" @click.self="closeModal">
            <form class="w-full max-w-4xl rounded-2xl border border-white/10 bg-[#132035] shadow-2xl shadow-black/30" novalidate @submit.prevent="saveTask">
                <div class="flex items-center justify-between border-b border-white/10 px-5 py-4">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.3em] text-sky-200">{{ editingTask ? 'Edit Task' : 'New Task' }}</p>
                        <h3 class="mt-1 text-sm font-semibold italic">Task details</h3>
                    </div>
                    <button type="button" class="rounded-lg border border-white/10 px-3 py-2 text-sm text-stone-300 transition hover:bg-white/5" @click="closeModal">Close</button>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Task Name</label>
                        <input v-model="form.task_name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-sky-300/50" :class="firstError(fieldErrors, 'task_name') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(fieldErrors, 'task_name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(fieldErrors, 'task_name') }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Status</label>
                        <select v-model="form.task_status_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-sky-300/50" :class="firstError(fieldErrors, 'task_status_id') ? 'border-rose-300/60' : ''">
                            <option v-for="status in taskStatuses" :key="status.id" :value="String(status.id)">{{ status.label ?? status.name }}</option>
                        </select>
                        <p v-if="firstError(fieldErrors, 'task_status_id')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(fieldErrors, 'task_status_id') }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Assigned To</label>
                        <select v-model="form.assigned_to" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-sky-300/50" :class="firstError(fieldErrors, 'assigned_to') ? 'border-rose-300/60' : ''">
                            <option value="">Unassigned</option>
                            <optgroup v-for="(options, group) in assigneeGroups" :key="group" :label="group">
                                <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </optgroup>
                        </select>
                        <p v-if="firstError(fieldErrors, 'assigned_to')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(fieldErrors, 'assigned_to') }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Attached Booking</label>
                        <select v-model="form.booking_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-sky-300/50" :class="firstError(fieldErrors, 'booking_id') ? 'border-rose-300/60' : ''">
                            <option value="">General task</option>
                            <option v-for="booking in bookings" :key="booking.id" :value="String(booking.id)">{{ bookingLabel(booking) }}</option>
                        </select>
                        <p v-if="firstError(fieldErrors, 'booking_id')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(fieldErrors, 'booking_id') }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Task Duration In Hours</label>
                        <input v-model="form.task_duration_hours" type="number" min="0" step="0.25" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-sky-300/50" :class="firstError(fieldErrors, 'task_duration_hours') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(fieldErrors, 'task_duration_hours')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(fieldErrors, 'task_duration_hours') }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Due Date</label>
                        <input v-model="form.due_date" type="date" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-sky-300/50" :class="firstError(fieldErrors, 'due_date') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(fieldErrors, 'due_date')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(fieldErrors, 'due_date') }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Date Started</label>
                        <input v-model="form.date_started" type="date" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-sky-300/50" :class="firstError(fieldErrors, 'date_started') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(fieldErrors, 'date_started')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(fieldErrors, 'date_started') }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Date Completed</label>
                        <input v-model="form.date_completed" type="date" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-sky-300/50" :class="firstError(fieldErrors, 'date_completed') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(fieldErrors, 'date_completed')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(fieldErrors, 'date_completed') }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Remarks</label>
                        <textarea v-model="form.remarks" rows="3" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-sky-300/50" :class="firstError(fieldErrors, 'remarks') ? 'border-rose-300/60' : ''" />
                        <p v-if="firstError(fieldErrors, 'remarks')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(fieldErrors, 'remarks') }}</p>
                    </div>
                    <div v-if="editingTask" class="sm:col-span-2 rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-[11px] uppercase tracking-[0.28em] text-stone-400">Customer Response</p>
                                <p class="mt-1 text-sm text-stone-300">Latest reply and attachments from the customer portal.</p>
                            </div>
                            <span class="rounded-full border border-white/10 bg-white/[0.03] px-3 py-1 text-xs text-stone-300">
                                {{ editingTask.customer_response_at_label || 'No reply yet' }}
                            </span>
                        </div>
                        <div class="mt-4 rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3">
                            <p class="text-sm leading-6 text-white">{{ editingTask.customer_response_note || 'No customer reply yet.' }}</p>
                        </div>
                        <div v-if="editingTask.customer_response_attachments?.length" class="mt-4">
                            <p class="text-[11px] uppercase tracking-[0.24em] text-stone-400">Attachments</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <a
                                    v-for="attachment in editingTask.customer_response_attachments"
                                    :key="`${editingTask.id}-${attachment.url}`"
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
                <div class="flex justify-end gap-3 border-t border-white/10 px-5 py-4">
                    <button type="button" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/5" @click="closeModal">Cancel</button>
                    <button type="submit" class="rounded-xl bg-sky-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-sky-200 disabled:opacity-60" :disabled="saving">
                        {{ saving ? 'Saving...' : 'Save task' }}
                    </button>
                </div>
            </form>
        </div>
    </transition>

    <ConfirmDialog
        :open="showDeleteConfirm"
        title="Delete task?"
        :message="`Are you sure you want to delete the record ${taskToDelete?.task_name || 'this task'}?`"
        confirm-label="Delete task"
        :loading="saving"
        @cancel="cancelRemoveTask"
        @confirm="removeTask"
    />
</template>
