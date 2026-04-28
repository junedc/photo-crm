<script setup>
import { computed, ref } from 'vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const { saving, deleteRecord } = useWorkspaceCrud();
const tasks = ref([...(props.data.tasks ?? [])]);
const search = ref('');
const selectedTaskIds = ref([]);
const tasksToDelete = ref([]);
const showDeleteConfirm = ref(false);
const filteredTasks = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) {
        return tasks.value;
    }

    return tasks.value.filter((task) => {
        const haystack = [
            task.task_name,
            task.status_label,
            task.assigned_to_name,
            task.booking_label,
            task.remarks,
            task.due_date_label,
            task.date_started_label,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(term);
    });
});
const selectedTasks = computed(() => tasks.value.filter((task) => selectedTaskIds.value.includes(task.id)));
const allVisibleSelected = computed(() => filteredTasks.value.length > 0 && filteredTasks.value.every((task) => selectedTaskIds.value.includes(task.id)));

const askRemoveTasks = (tasksToRemove) => {
    tasksToDelete.value = tasksToRemove;
    showDeleteConfirm.value = true;
};

const toggleTaskSelection = (task) => {
    if (selectedTaskIds.value.includes(task.id)) {
        selectedTaskIds.value = selectedTaskIds.value.filter((id) => id !== task.id);
        return;
    }

    selectedTaskIds.value = [...selectedTaskIds.value, task.id];
};

const toggleAllTasks = () => {
    if (allVisibleSelected.value) {
        const visibleIds = filteredTasks.value.map((task) => task.id);
        selectedTaskIds.value = selectedTaskIds.value.filter((id) => !visibleIds.includes(id));
        return;
    }

    const merged = new Set([...selectedTaskIds.value, ...filteredTasks.value.map((task) => task.id)]);
    selectedTaskIds.value = [...merged];
};

const openTask = (task) => {
    window.location.href = task.show_url;
};

const cancelRemoveTasks = () => {
    tasksToDelete.value = [];
    showDeleteConfirm.value = false;
};

const removeTasks = async () => {
    if (tasksToDelete.value.length === 0) {
        return;
    }

    for (const task of tasksToDelete.value) {
        if (!task.delete_url) {
            continue;
        }

        // Use the current delete endpoint so we can support bulk delete without a new backend route.
        await deleteRecord({ url: task.delete_url });
    }

    const deletedIds = tasksToDelete.value.map((task) => task.id);
    tasks.value = tasks.value.filter((entry) => !deletedIds.includes(entry.id));
    selectedTaskIds.value = selectedTaskIds.value.filter((id) => !deletedIds.includes(id));
    cancelRemoveTasks();
};
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
                <h3 class="mt-1 text-sm font-semibold italic">{{ filteredTasks.length }} task{{ filteredTasks.length === 1 ? '' : 's' }}</h3>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search tasks"
                    class="min-w-[220px] rounded-xl border border-white/10 bg-slate-950/40 px-4 py-2 text-sm text-white outline-none transition placeholder:text-stone-500 focus:border-sky-300/60"
                >
                <button
                    type="button"
                    class="rounded-xl border border-rose-400/30 px-4 py-2 text-sm font-semibold text-rose-100 transition hover:bg-rose-400/10 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="selectedTasks.length === 0"
                    @click="selectedTasks.length && askRemoveTasks(selectedTasks)"
                >
                    Delete task<span v-if="selectedTasks.length > 1">s</span>
                </button>
                <a :href="props.data.routes.create" class="rounded-xl bg-sky-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-200">
                    Create task
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <div class="min-w-[1060px]">
                <div class="grid grid-cols-[4.75rem_minmax(0,1.45fr)_8rem_10rem_13rem_7rem_8rem_8rem_14rem] gap-3 px-3 py-2 text-[10px] uppercase tracking-[0.12em] text-stone-500">
                    <label class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            class="h-4 w-4 rounded border-white/20 bg-transparent text-sky-300 focus:ring-sky-300"
                            :checked="allVisibleSelected"
                            @change="toggleAllTasks"
                        >
                        <span>Select</span>
                    </label>
                    <span>Task</span>
                    <span>Status</span>
                    <span>Assignee</span>
                    <span>Booking</span>
                    <span>Hours</span>
                    <span>Due</span>
                    <span>Started</span>
                    <span>Notes</span>
                </div>
                <div
                    v-for="task in filteredTasks"
                    :key="task.id"
                    class="grid cursor-pointer grid-cols-[4.75rem_minmax(0,1.45fr)_8rem_10rem_13rem_7rem_8rem_8rem_14rem] items-center gap-3 border-t border-white/10 px-3 py-2 transition hover:bg-white/[0.03]"
                    :class="selectedTaskIds.includes(task.id) ? 'bg-white/[0.05]' : ''"
                    role="button"
                    tabindex="0"
                    @click="openTask(task)"
                    @keydown.enter.prevent="openTask(task)"
                    @keydown.space.prevent="openTask(task)"
                >
                    <label class="flex items-center justify-center">
                        <input
                            type="checkbox"
                            class="h-4 w-4 rounded border-white/20 bg-transparent text-sky-300 focus:ring-sky-300"
                            :checked="selectedTaskIds.includes(task.id)"
                            @click.stop
                            @change="toggleTaskSelection(task)"
                        >
                    </label>
                    <p class="truncate text-sm font-medium text-white">{{ task.task_name }}</p>
                    <p class="text-sm text-cyan-100 break-words leading-5">{{ task.status_label || 'No status' }}</p>
                    <p class="truncate text-sm text-sky-100">{{ task.assigned_to_name }}</p>
                    <p class="truncate text-sm text-stone-300">{{ task.booking_label || 'General task' }}</p>
                    <p class="text-sm text-stone-300">{{ task.task_duration_hours || '0.00' }}</p>
                    <p class="text-sm text-stone-300">{{ task.due_date_label }}</p>
                    <p class="text-sm text-stone-300">{{ task.date_started_label }}</p>
                    <p class="truncate text-sm text-stone-400">{{ task.remarks || 'No remarks' }}</p>
                </div>
                <p v-if="filteredTasks.length === 0" class="border-t border-white/10 px-3 py-6 text-sm text-stone-400">
                    No tasks matched your search.
                </p>
            </div>
        </div>
    </section>

    <ConfirmDialog
        :open="showDeleteConfirm"
        :title="tasksToDelete.length > 1 ? 'Delete selected tasks?' : 'Delete task?'"
        :message="tasksToDelete.length > 1
            ? `Are you sure you want to delete ${tasksToDelete.length} selected tasks?`
            : `Are you sure you want to delete the record ${tasksToDelete[0]?.task_name || 'this task'}?`"
        :confirm-label="tasksToDelete.length > 1 ? 'Delete tasks' : 'Delete task'"
        :loading="saving"
        @cancel="cancelRemoveTasks"
        @confirm="removeTasks"
    />
</template>
