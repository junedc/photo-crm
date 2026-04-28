<script setup>
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { emitAdminToast } from '../useWorkspaceCrud';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const leadList = ref([...(props.data.leads ?? [])]);
const search = ref('');
const selectedStatus = ref('all');
const pagination = ref(props.data.pagination ?? { total: leadList.value.length, has_more: false, next_page: null });
const loadingMore = ref(false);
const selectedLeadIds = ref([]);
const deletingLeadIds = ref([]);
const bulkDeleting = ref(false);
const sortKey = ref('customer_name');
const sortDirection = ref('asc');
const csrfToken = window.adminProps?.csrfToken ?? '';
const confirmDialog = ref({
    open: false,
    title: '',
    message: '',
    confirmLabel: 'Delete',
    leadIds: [],
    mode: 'single',
});
let requestId = 0;

const sortableColumns = {
    customer_name: (entry) => entry.customer_name || '',
    customer_phone: (entry) => entry.customer_phone || '',
    customer_email: (entry) => entry.customer_email || '',
};

const leads = computed(() => {
    const getValue = sortableColumns[sortKey.value] ?? sortableColumns.customer_name;

    return [...leadList.value].sort((left, right) => {
        const leftValue = String(getValue(left)).toLocaleLowerCase();
        const rightValue = String(getValue(right)).toLocaleLowerCase();
        const comparison = leftValue.localeCompare(rightValue, undefined, { numeric: true, sensitivity: 'base' });

        return sortDirection.value === 'asc' ? comparison : comparison * -1;
    });
});

const allVisibleSelected = computed(() => leads.value.length > 0 && leads.value.every((entry) => selectedLeadIds.value.includes(entry.id)));
const selectedCount = computed(() => selectedLeadIds.value.length);

const fetchLeads = async (page = 1, append = false) => {
    const currentRequestId = ++requestId;
    loadingMore.value = true;

    const params = new URLSearchParams({
        page: String(page),
        search: search.value.trim(),
        status: selectedStatus.value,
    });

    try {
        const response = await fetch(`${props.data.routes.leads}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok || currentRequestId !== requestId) {
            return;
        }

        const payload = await response.json();
        leadList.value = append ? [...leadList.value, ...(payload.records ?? [])] : [...(payload.records ?? [])];
        pagination.value = payload.pagination ?? pagination.value;
        selectedLeadIds.value = selectedLeadIds.value.filter((id) => leadList.value.some((entry) => entry.id === id));
    } finally {
        if (currentRequestId === requestId) {
            loadingMore.value = false;
        }
    }
};

const loadMoreLeads = () => pagination.value.next_page && fetchLeads(pagination.value.next_page, true);

const toggleSort = (column) => {
    if (sortKey.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        return;
    }

    sortKey.value = column;
    sortDirection.value = 'asc';
};

const toggleSelectAll = () => {
    if (allVisibleSelected.value) {
        selectedLeadIds.value = [];
        return;
    }

    selectedLeadIds.value = leads.value.map((entry) => entry.id);
};

const sortIndicator = (column) => {
    if (sortKey.value !== column) {
        return '↕';
    }

    return sortDirection.value === 'asc' ? '↑' : '↓';
};

const deleteLeadIds = async (leadIds, successMessage) => {
    const formData = new FormData();
    leadIds.forEach((id) => formData.append('lead_ids[]', String(id)));

    if (csrfToken) {
        formData.append('_token', csrfToken);
    }

    const response = await axios.post(props.data.routes.bulk_delete, formData, {
        headers: {
            Accept: 'application/json',
        },
    });

    leadList.value = leadList.value.filter((entry) => !leadIds.includes(entry.id));
    selectedLeadIds.value = selectedLeadIds.value.filter((id) => !leadIds.includes(id));
    pagination.value = {
        ...pagination.value,
        total: Math.max(0, (pagination.value.total ?? leadList.value.length) - leadIds.length),
    };

    emitAdminToast({
        type: 'success',
        message: successMessage ?? response.data.message ?? 'Lead deleted.',
    });
};

const openSingleDeleteDialog = (entry) => {
    confirmDialog.value = {
        open: true,
        title: 'Delete lead?',
        message: `Are you sure you want to delete the record ${entry.customer_name || 'this lead'}?`,
        confirmLabel: 'Delete lead',
        leadIds: [entry.id],
        mode: 'single',
    };
};

const openBulkDeleteDialog = () => {
    if (!selectedLeadIds.value.length) {
        return;
    }

    const label = selectedLeadIds.value.length === 1 ? 'this lead' : `these ${selectedLeadIds.value.length} leads`;
    confirmDialog.value = {
        open: true,
        title: 'Delete selected leads?',
        message: `Are you sure you want to delete the record ${label}?`,
        confirmLabel: selectedLeadIds.value.length === 1 ? 'Delete lead' : 'Delete leads',
        leadIds: [...selectedLeadIds.value],
        mode: 'bulk',
    };
};

const closeDeleteDialog = (force = false) => {
    if (!force && (bulkDeleting.value || deletingLeadIds.value.length)) {
        return;
    }

    confirmDialog.value = {
        open: false,
        title: '',
        message: '',
        confirmLabel: 'Delete',
        leadIds: [],
        mode: 'single',
    };
};

const confirmDelete = async () => {
    if (!confirmDialog.value.leadIds.length) {
        return;
    }

    const leadIds = [...confirmDialog.value.leadIds];

    if (confirmDialog.value.mode === 'single') {
        deletingLeadIds.value = [...new Set([...deletingLeadIds.value, ...leadIds])];
    } else {
        bulkDeleting.value = true;
    }

    try {
        await deleteLeadIds(leadIds, confirmDialog.value.mode === 'single' ? 'Lead deleted.' : null);
    } catch (error) {
        emitAdminToast({
            type: 'error',
            errors: [error.response?.data?.message ?? `Something went wrong while deleting ${confirmDialog.value.mode === 'single' ? 'the lead' : 'the selected leads'}.`],
        });
    } finally {
        if (confirmDialog.value.mode === 'single') {
            deletingLeadIds.value = deletingLeadIds.value.filter((id) => !leadIds.includes(id));
        } else {
            bulkDeleting.value = false;
        }

        if (!deletingLeadIds.value.length && !bulkDeleting.value) {
            closeDeleteDialog(true);
        }
    }
};

watch([search, selectedStatus], () => fetchLeads(1, false));
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-violet-200">Leads Workspace</p>
        <h2 class="text-sm font-bold italic text-white">Track booking prospects and follow-ups</h2>
        <p class="text-sm text-stone-300">
            Review autosaved booking prospects, add manual leads, and maintain the sales pipeline for each tenant.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
        <div class="sticky top-0 z-10 -mx-3 -mt-3 mb-3 rounded-t-2xl border-b border-white/10 bg-[#132035] px-3 pb-3 pt-3">
            <div class="px-2">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Lead Records</p>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <h3 class="text-sm font-semibold italic">Lead list</h3>
                        <span class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 text-xs text-stone-300">{{ pagination.total ?? leads.length }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-rose-300/20 bg-rose-400/10 px-4 py-2 text-sm font-semibold text-rose-100 transition hover:border-rose-300/40 hover:bg-rose-400/15 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!selectedCount || bulkDeleting"
                            @click="openBulkDeleteDialog"
                        >
                            {{ bulkDeleting ? 'Deleting...' : `Delete selected${selectedCount ? ` (${selectedCount})` : ''}` }}
                        </button>
                        <a :href="data.routes.create" class="rounded-xl bg-violet-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-violet-200">
                            Create lead
                        </a>
                    </div>
                </div>
            </div>
            <div class="mt-3 grid gap-2 md:grid-cols-[minmax(0,1fr)_220px]">
                <input v-model="search" type="text" placeholder="Search leads" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-violet-300/50">
                <select v-model="selectedStatus" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-violet-300/50">
                    <option value="all">All statuses</option>
                    <option v-for="status in data.leadStatuses" :key="status" :value="status">{{ status }}</option>
                </select>
            </div>
        </div>

        <div class="max-h-[70vh] overflow-auto">
            <table class="min-w-full table-fixed border-separate border-spacing-0">
                <thead class="sticky top-0 z-10 bg-[#132035]">
                    <tr class="text-left text-[11px] uppercase tracking-[0.2em] text-stone-500">
                        <th scope="col" class="w-14 border-b border-white/10 px-3 py-3">
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border border-white/20 bg-slate-950/80 text-violet-300 focus:ring-violet-300"
                                :checked="allVisibleSelected"
                                :aria-label="allVisibleSelected ? 'Deselect all leads' : 'Select all leads'"
                                @change="toggleSelectAll"
                            >
                        </th>
                        <th scope="col" class="border-b border-white/10 px-3 py-3">
                            <button type="button" class="flex items-center gap-2 text-left transition hover:text-white" @click="toggleSort('customer_name')">
                                <span>Name</span>
                                <span class="text-xs leading-none text-stone-400" aria-hidden="true">{{ sortIndicator('customer_name') }}</span>
                            </button>
                        </th>
                        <th scope="col" class="border-b border-white/10 px-3 py-3">
                            <button type="button" class="flex items-center gap-2 text-left transition hover:text-white" @click="toggleSort('customer_phone')">
                                <span>Phone</span>
                                <span class="text-xs leading-none text-stone-400" aria-hidden="true">{{ sortIndicator('customer_phone') }}</span>
                            </button>
                        </th>
                        <th scope="col" class="border-b border-white/10 px-3 py-3">
                            <button type="button" class="flex items-center gap-2 text-left transition hover:text-white" @click="toggleSort('customer_email')">
                                <span>Email</span>
                                <span class="text-xs leading-none text-stone-400" aria-hidden="true">{{ sortIndicator('customer_email') }}</span>
                            </button>
                        </th>
                        <th scope="col" class="w-44 border-b border-white/10 px-3 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="entry in leads" :key="entry.id" class="transition hover:bg-white/[0.03]">
                        <td class="border-b border-white/10 px-3 py-3 align-middle">
                            <input
                                v-model="selectedLeadIds"
                                type="checkbox"
                                class="h-4 w-4 rounded border border-white/20 bg-slate-950/80 text-violet-300 focus:ring-violet-300"
                                :value="entry.id"
                                :aria-label="`Select ${entry.customer_name || 'lead'}`"
                            >
                        </td>
                        <td class="border-b border-white/10 px-3 py-3 align-middle">
                            <div class="min-w-0">
                                <a :href="entry.show_url" class="truncate text-sm font-medium text-white transition hover:text-violet-200">
                                    {{ entry.customer_name || 'Unnamed lead' }}
                                </a>
                            </div>
                        </td>
                        <td class="border-b border-white/10 px-3 py-3 align-middle text-sm text-stone-300">
                            {{ entry.customer_phone || 'No phone' }}
                        </td>
                        <td class="border-b border-white/10 px-3 py-3 align-middle text-sm text-stone-300">
                            {{ entry.customer_email || 'No email' }}
                        </td>
                        <td class="border-b border-white/10 px-3 py-3 align-middle">
                            <div class="flex justify-end gap-2">
                                <a
                                    :href="entry.show_url"
                                    class="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-white transition hover:border-violet-300/40 hover:bg-white/10 hover:text-violet-100"
                                >
                                    Edit
                                </a>
                                <button
                                    type="button"
                                    class="rounded-lg border border-rose-300/20 bg-rose-400/10 px-3 py-1.5 text-xs font-semibold text-rose-100 transition hover:border-rose-300/40 hover:bg-rose-400/15 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="deletingLeadIds.includes(entry.id)"
                                    @click="openSingleDeleteDialog(entry)"
                                >
                                    {{ deletingLeadIds.includes(entry.id) ? 'Deleting...' : 'Delete' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!leads.length" class="rounded-2xl border border-dashed border-white/15 bg-stone-950/40 px-4 py-5 text-sm text-stone-400">
                No leads match the current filters.
            </div>

            <div v-else-if="pagination.has_more" class="flex justify-center px-3 py-4">
                <button type="button" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-violet-300/40 hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loadingMore" @click="loadMoreLeads">
                    {{ loadingMore ? 'Loading...' : 'Load more leads' }}
                </button>
            </div>
        </div>
    </section>

    <ConfirmDialog
        :open="confirmDialog.open"
        :title="confirmDialog.title"
        :message="confirmDialog.message"
        :confirm-label="confirmDialog.confirmLabel"
        :loading="bulkDeleting || deletingLeadIds.length > 0"
        @cancel="closeDeleteDialog"
        @confirm="confirmDelete"
    />
</template>
