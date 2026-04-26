<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';
import { autoAttachGoogleAddressInputs } from '../../googleAddressAutocomplete';
import { firstError, hasFieldErrors, isBlank, mergeFieldErrors, requiredMessage } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const { saving, fieldErrors, submitForm, deleteRecord } = useWorkspaceCrud();
const bookingRecord = ref(props.data.booking);
const activeTab = ref('overview');
const isEditing = ref(false);
const editErrors = ref({});
const invoiceErrors = ref({});
const taskErrors = ref({});
const tasks = ref([...(props.data.booking?.tasks ?? [])]);
const localTaskStatuses = ref([...(props.data.taskStatuses ?? [])]);
const bookingStatusOptions = computed(() => props.data.bookingStatusOptions ?? []);
const defaultTaskStatusId = computed(() => String(localTaskStatuses.value.find((status) => String(status.name ?? '').toLowerCase() === 'new')?.id ?? ''));

const buildEditForm = (record) => ({
    booking_status_id: record?.status_id ? String(record.status_id) : String(props.data.bookingStatusOptions?.[0]?.id ?? ''),
    booking_kind: record?.booking_kind ?? (props.data.bookingKinds?.[0] ?? 'customer'),
    entry_name: record?.entry_name ?? '',
    entry_description: record?.entry_description ?? '',
    package_id: record?.package_id ? String(record.package_id) : '',
    package_hourly_price_id: record?.package_hourly_price_id ? String(record.package_hourly_price_id) : '',
    customer_name: record?.customer_name ?? '',
    customer_email: record?.customer_email ?? '',
    customer_phone: record?.customer_phone ?? '',
    event_type: record?.event_type ?? (props.data.eventTypes?.[0] ?? 'Wedding'),
    event_date: record?.event_date ?? '',
    start_time: record?.start_time ?? '',
    end_time: record?.end_time ?? '',
    total_hours: record?.total_hours ?? '0.00',
    event_location: record?.event_location ?? '',
    notes: record?.notes ?? '',
    discount_id: record?.discount_id ? String(record.discount_id) : '',
    equipment_ids: [...(record?.equipment_ids ?? [])],
    add_on_ids: [...(record?.add_on_ids ?? [])],
    equipment_discount_types: { ...(record?.equipment_discount_types ?? {}) },
    equipment_discount_values: { ...(record?.equipment_discount_values ?? {}) },
    add_on_discount_types: { ...(record?.add_on_discount_types ?? {}) },
    add_on_discount_values: { ...(record?.add_on_discount_values ?? {}) },
});

const buildTaskForm = (task = null) => ({
    task_name: task?.task_name ?? '',
    task_duration_hours: task?.task_duration_hours ?? '',
    assigned_to: task?.assigned_to ? String(task.assigned_to) : '',
    task_status_id: task?.task_status_id ? String(task.task_status_id) : defaultTaskStatusId.value,
    due_date: task?.due_date ?? '',
    date_started: task?.date_started ?? '',
    date_completed: task?.date_completed ?? '',
    remarks: task?.remarks ?? '',
});

const editForm = ref(buildEditForm(props.data.booking));
const editingTask = ref(null);
const taskForm = ref(buildTaskForm());
const invoiceForm = ref({
    installment_count: '3',
    deposit_percentage: String(props.data.defaultDepositPercentage ?? 30),
    first_due_date: '',
    interval_days: '30',
});

const editValidationErrors = computed(() => mergeFieldErrors(editErrors.value, fieldErrors.value));
const invoiceValidationErrors = computed(() => mergeFieldErrors(invoiceErrors.value, fieldErrors.value));
const taskValidationErrors = computed(() => mergeFieldErrors(taskErrors.value, fieldErrors.value));
const packages = computed(() => props.data.packages ?? []);
const equipmentOptions = computed(() => props.data.equipmentOptions ?? []);
const addOnOptions = computed(() => props.data.addOnOptions ?? []);
const discountOptions = computed(() => props.data.discountOptions ?? []);
const taskAssignees = computed(() => bookingRecord.value.task_assignees ?? props.data.taskAssignees ?? []);
const taskStatuses = computed(() => localTaskStatuses.value);
const isEntryBooking = computed(() => editForm.value.booking_kind === 'market_stall' || editForm.value.booking_kind === 'sponsored');
const invoiceAmountLocked = computed(() => {
    const invoice = bookingRecord.value.invoice;

    if (!invoice) {
        return false;
    }

    return ['partially_paid', 'paid'].includes(invoice.status) || Number(invoice.amount_paid || 0) > 0;
});
const selectedPackage = computed(() =>
    packages.value.find((entry) => String(entry.id) === String(editForm.value.package_id ?? '')) ?? null,
);
const selectedPackageHourlyPrices = computed(() => selectedPackage.value?.hourly_prices ?? []);
const availableDiscountOptions = computed(() => {
    const packageId = Number(editForm.value.package_id ?? 0);
    const equipmentIds = new Set((editForm.value.equipment_ids ?? []).map((id) => Number(id)));

    return discountOptions.value.filter((discount) => {
        const packageMatch = discount.package_ids?.includes(packageId);
        const equipmentMatch = (discount.equipment_ids ?? []).some((id) => equipmentIds.has(Number(id)));

        return packageMatch || equipmentMatch;
    });
});
const combinedOptionalItems = computed(() => [
    ...equipmentOptions.value.map((item) => ({
        ...item,
        selection_key: 'equipment_ids',
        type_label: 'Equipment',
        details_label: item.category || 'Equipment',
        price_label: item.daily_rate,
        selected: (editForm.value.equipment_ids ?? []).includes(item.id),
        tone: 'cyan',
    })),
    ...addOnOptions.value.map((item) => ({
        ...item,
        selection_key: 'add_on_ids',
        type_label: 'Add-On',
        details_label: [item.product_code, item.duration].filter(Boolean).join(' - ') || 'Add-On',
        price_label: item.price,
        selected: (editForm.value.add_on_ids ?? []).includes(item.id),
        tone: 'rose',
    })),
]);
const clampDiscountPercentage = (value) => Math.min(100, Math.max(0, Number(value) || 0));
const clampDiscountAmount = (value) => Math.max(0, Number(value) || 0);
const formatMoney = (value) => Number(value || 0).toFixed(2);
const itemDiscountTypeMapKey = (selectionKey) => (
    selectionKey === 'equipment_ids' ? 'equipment_discount_types' : 'add_on_discount_types'
);
const itemDiscountValueMapKey = (selectionKey) => (
    selectionKey === 'equipment_ids' ? 'equipment_discount_values' : 'add_on_discount_values'
);
const itemDiscountType = (item) => (
    editForm.value[itemDiscountTypeMapKey(item.selection_key)]?.[String(item.id)] ?? 'percentage'
);
const itemDiscountValue = (item) => (
    editForm.value[itemDiscountValueMapKey(item.selection_key)]?.[String(item.id)] ?? '0'
);
const applyDiscount = (amount, discountType, discountValue) => {
    const numericAmount = Number(amount || 0);

    if (discountType === 'amount') {
        return Math.max(0, numericAmount - clampDiscountAmount(discountValue));
    }

    return numericAmount * (1 - (clampDiscountPercentage(discountValue) / 100));
};
const itemFinalPrice = (item) => formatMoney(applyDiscount(item.price_label, itemDiscountType(item), itemDiscountValue(item)));
const setItemDiscountType = (item, value) => {
    const key = itemDiscountTypeMapKey(item.selection_key);
    const normalizedValue = value === 'amount' ? 'amount' : 'percentage';

    editForm.value[key] = {
        ...(editForm.value[key] ?? {}),
        [String(item.id)]: normalizedValue,
    };
};
const setItemDiscountValue = (item, value) => {
    const key = itemDiscountValueMapKey(item.selection_key);
    const normalizedValue = itemDiscountType(item) === 'amount'
        ? String(clampDiscountAmount(value).toFixed(2))
        : String(clampDiscountPercentage(value).toFixed(2));

    editForm.value[key] = {
        ...(editForm.value[key] ?? {}),
        [String(item.id)]: normalizedValue,
    };
};
const clearItemDiscountValue = (selectionKey, id) => {
    const valueKey = itemDiscountValueMapKey(selectionKey);
    const typeKey = itemDiscountTypeMapKey(selectionKey);
    const currentValues = { ...(editForm.value[valueKey] ?? {}) };
    const currentTypes = { ...(editForm.value[typeKey] ?? {}) };

    delete currentValues[String(id)];
    delete currentTypes[String(id)];
    editForm.value[valueKey] = currentValues;
    editForm.value[typeKey] = currentTypes;
};
const itemDiscountLabel = (selectionKey, id) => {
    const type = editForm.value[itemDiscountTypeMapKey(selectionKey)]?.[String(id)] ?? 'percentage';
    const value = editForm.value[itemDiscountValueMapKey(selectionKey)]?.[String(id)] ?? '0';

    return type === 'amount' ? `$${formatMoney(value)}` : `${formatMoney(value)}%`;
};
const taskAssigneeGroups = computed(() => {
    return taskAssignees.value.reduce((groups, option) => {
        const group = option.group ?? 'Other';
        groups[group] = [...(groups[group] ?? []), option];
        return groups;
    }, {});
});

const statusLabel = (status) => (status || '').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
const bookingKindLabel = (kind) => ({
    customer: 'Customer Booking',
    market_stall: 'Market Stall',
    sponsored: 'Sponsored',
}[kind] ?? 'Customer Booking');
const equipmentSummary = (item) => [item.category, `$${item.price ?? item.daily_rate}`].filter(Boolean).join(' - ');
const addOnSummary = (addOn) => [addOn.product_code, addOn.duration].filter(Boolean).join(' - ');
const overviewSelectedItems = computed(() => {
    const rows = [];

    if (bookingRecord.value.package) {
        rows.push({
            id: `package-${bookingRecord.value.package.id ?? 'selected'}`,
            type_label: 'Package',
            name: bookingRecord.value.package.name ?? bookingRecord.value.package_name ?? 'No package selected',
            details: bookingRecord.value.total_hours ? `${bookingRecord.value.total_hours} hrs` : 'Package',
            price: bookingRecord.value.package.price ?? bookingRecord.value.package_price ?? '0.00',
            description: bookingRecord.value.package.description || 'Selected package',
        });
    }

    (bookingRecord.value.equipment ?? []).forEach((item) => {
        rows.push({
            id: `equipment-${item.id}`,
            type_label: 'Equipment',
            name: item.name,
            details: equipmentSummary(item) || 'Equipment',
            price: item.price ?? item.original_price ?? '0.00',
            description: item.description || 'Assigned equipment',
        });
    });

    (bookingRecord.value.addons ?? []).forEach((item) => {
        rows.push({
            id: `addon-${item.id}`,
            type_label: 'Add-On',
            name: item.name,
            details: addOnSummary(item) || 'Add-On',
            price: item.price ?? item.original_price ?? '0.00',
            description: item.description || 'Assigned add-on',
        });
    });

    return rows;
});

const openDatePicker = (event) => {
    try {
        event.target?.showPicker?.();
    } catch {
        // Native fallback.
    }
};

const syncBooking = (record) => {
    bookingRecord.value = record;
    editForm.value = buildEditForm(record);
    tasks.value = [...(record.tasks ?? [])];
    window.history.replaceState({}, '', record.show_url);
};

const syncPackageTimingDefaults = () => {
    if (!selectedPackage.value || !selectedPackageHourlyPrices.value.length) {
        editForm.value.package_hourly_price_id = '';
        return;
    }

    const currentSelected = selectedPackageHourlyPrices.value.find((entry) => String(entry.id) === String(editForm.value.package_hourly_price_id ?? ''));

    if (!currentSelected) {
        const lowestHourly = [...selectedPackageHourlyPrices.value]
            .sort((left, right) => Number(left.hours) - Number(right.hours))[0];

        editForm.value.package_hourly_price_id = lowestHourly ? String(lowestHourly.id) : '';
    }
};

const syncDurationFromPackageTiming = () => {
    const selectedHourly = selectedPackageHourlyPrices.value.find((entry) => String(entry.id) === String(editForm.value.package_hourly_price_id ?? ''));

    if (selectedHourly) {
        editForm.value.total_hours = Number(selectedHourly.hours).toFixed(2);
    }
};

const syncEndTime = () => {
    if (!editForm.value.start_time || !editForm.value.total_hours) {
        editForm.value.end_time = '';
        return;
    }

    const [startHour, startMinute] = editForm.value.start_time.split(':').map(Number);

    if (Number.isNaN(startHour) || Number.isNaN(startMinute)) {
        editForm.value.end_time = '';
        return;
    }

    const totalMinutes = Math.round(Number(editForm.value.total_hours || 0) * 60);
    const endMinutes = startHour * 60 + startMinute + totalMinutes;
    const normalizedMinutes = ((endMinutes % (24 * 60)) + (24 * 60)) % (24 * 60);
    const hours = String(Math.floor(normalizedMinutes / 60)).padStart(2, '0');
    const minutes = String(normalizedMinutes % 60).padStart(2, '0');

    editForm.value.end_time = `${hours}:${minutes}`;
};

const toggleMultiSelect = (key, id) => {
    const values = new Set(editForm.value[key] ?? []);

    if (values.has(id)) {
        values.delete(id);
        clearItemDiscountValue(key, id);
    } else {
        values.add(id);
    }

    editForm.value[key] = [...values];
};

watch(() => editForm.value.package_id, () => {
    syncPackageTimingDefaults();
    syncDurationFromPackageTiming();
    syncEndTime();

    if (!availableDiscountOptions.value.some((entry) => String(entry.id) === String(editForm.value.discount_id ?? ''))) {
        editForm.value.discount_id = '';
    }
});

watch(() => editForm.value.package_hourly_price_id, () => {
    syncDurationFromPackageTiming();
    syncEndTime();
});

watch(() => editForm.value.total_hours, () => {
    syncEndTime();
});

watch(() => editForm.value.start_time, () => {
    syncEndTime();
});

watch(isEditing, (value) => {
    if (value) {
        nextTick(() => autoAttachGoogleAddressInputs());
    }
});

onMounted(() => {
    syncPackageTimingDefaults();
    startTaskCreate();
});

const grantClientAccess = async () => {
    try {
        const record = await submitForm({
            url: bookingRecord.value.grant_client_access_url,
            data: {},
        });

        syncBooking(record);
    } catch {}
};

const updateBooking = async () => {
    const errors = {};

    if (isBlank(editForm.value.booking_status_id)) {
        errors.booking_status_id = requiredMessage('Status');
    }

    if (isBlank(editForm.value.package_id)) {
        errors.package_id = requiredMessage('Package');
    }

    if (isBlank(isEntryBooking.value ? editForm.value.entry_name : editForm.value.customer_name)) {
        errors[isEntryBooking.value ? 'entry_name' : 'customer_name'] = requiredMessage(isEntryBooking.value ? 'Name' : 'Customer name');
    }

    if (isBlank(editForm.value.customer_email)) {
        errors.customer_email = requiredMessage('Email');
    }

    if (isBlank(editForm.value.customer_phone)) {
        errors.customer_phone = requiredMessage('Phone');
    }

    if (isBlank(editForm.value.event_date)) {
        errors.event_date = requiredMessage('Event date');
    }

    if (isBlank(editForm.value.start_time)) {
        errors.start_time = requiredMessage('Start hour');
    }

    if (isBlank(editForm.value.total_hours)) {
        errors.total_hours = requiredMessage('Hour duration');
    }

    if (isBlank(editForm.value.end_time)) {
        errors.end_time = requiredMessage('End hour');
    }

    if (isBlank(editForm.value.event_location)) {
        errors.event_location = requiredMessage('Location');
    }

    editErrors.value = errors;

    if (hasFieldErrors(errors)) {
        return;
    }

    try {
        const record = await submitForm({
            url: bookingRecord.value.update_url,
            method: 'put',
            data: { ...editForm.value },
        });

        editErrors.value = {};
        syncBooking(record);
        isEditing.value = false;
    } catch {}
};

const startEditing = () => {
    activeTab.value = 'overview';
    editErrors.value = {};
    editForm.value = buildEditForm(bookingRecord.value);
    isEditing.value = true;
};

const cancelEditing = () => {
    editErrors.value = {};
    editForm.value = buildEditForm(bookingRecord.value);
    isEditing.value = false;
};

const createInvoice = async () => {
    const errors = {};

    if (isBlank(invoiceForm.value.installment_count)) {
        errors.installment_count = requiredMessage('Installments');
    }

    if (isBlank(invoiceForm.value.deposit_percentage)) {
        errors.deposit_percentage = requiredMessage('Deposit %');
    }

    if (isBlank(invoiceForm.value.first_due_date)) {
        errors.first_due_date = requiredMessage('First due date');
    }

    if (isBlank(invoiceForm.value.interval_days)) {
        errors.interval_days = requiredMessage('Interval days');
    }

    invoiceErrors.value = errors;

    if (hasFieldErrors(errors)) {
        return;
    }

    try {
        const record = await submitForm({
            url: bookingRecord.value.invoice_create_url,
            method: 'post',
            data: { ...invoiceForm.value },
        });

        invoiceErrors.value = {};
        syncBooking(record);
    } catch {}
};

const sendInvoice = async () => {
    if (!bookingRecord.value.invoice?.send_url) {
        return;
    }

    try {
        const invoice = await submitForm({
            url: bookingRecord.value.invoice.send_url,
            method: 'post',
            data: {},
        });

        syncBooking({
            ...bookingRecord.value,
            invoice,
        });
    } catch {}
};

const startTaskCreate = () => {
    editingTask.value = null;
    taskErrors.value = {};
    taskForm.value = buildTaskForm();
};

const startTaskEdit = (task) => {
    editingTask.value = task;
    taskErrors.value = {};
    taskForm.value = buildTaskForm(task);
};

const cancelTaskEdit = () => {
    startTaskCreate();
};

const saveTask = async () => {
    const errors = {};

    if (isBlank(taskForm.value.task_name)) {
        errors.task_name = requiredMessage('Task name');
    }

    taskErrors.value = errors;

    if (hasFieldErrors(errors)) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('task_name', taskForm.value.task_name ?? '');
        formData.append('task_duration_hours', taskForm.value.task_duration_hours ?? '');
        formData.append('assigned_to', taskForm.value.assigned_to ?? '');
        formData.append('booking_id', String(bookingRecord.value.id));
        formData.append('task_status_id', taskForm.value.task_status_id ?? '');
        formData.append('due_date', taskForm.value.due_date ?? '');
        formData.append('date_started', taskForm.value.date_started ?? '');
        formData.append('date_completed', taskForm.value.date_completed ?? '');
        formData.append('remarks', taskForm.value.remarks ?? '');

        if (editingTask.value) {
            formData.append('_method', 'PUT');
        }

        const record = await submitForm({
            url: editingTask.value?.update_url ?? props.data.taskRoutes.store,
            method: 'post',
            data: formData,
        });

        const index = tasks.value.findIndex((task) => task.id === record.id);
        tasks.value = index >= 0
            ? tasks.value.map((task) => (task.id === record.id ? record : task))
            : [record, ...tasks.value];
        bookingRecord.value = {
            ...bookingRecord.value,
            tasks: [...tasks.value],
        };
        startTaskCreate();
    } catch {}
};

const removeTask = async (task) => {
    await deleteRecord({ url: task.delete_url });
    tasks.value = tasks.value.filter((entry) => entry.id !== task.id);
    bookingRecord.value = {
        ...bookingRecord.value,
        tasks: [...tasks.value],
    };

    if (editingTask.value?.id === task.id) {
        startTaskCreate();
    }
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-3 gap-y-1.5 rounded-xl border border-white/10 bg-white/[0.03] px-4 py-2.5 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-rose-200">Bookings Workspace</p>
        <h2 class="text-sm font-bold italic text-white">{{ bookingRecord.display_name || bookingRecord.customer_name }}</h2>
        <p class="text-xs text-stone-300">
            Review the booking, manage invoice activity, and keep booking tasks together.
        </p>
    </section>

    <section class="rounded-xl border border-white/10 bg-[#132035] shadow-2xl shadow-black/20">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-rose-200">Booking Details</p>
                <div class="mt-0.5 flex flex-wrap items-center gap-2">
                    <h3 class="text-sm font-semibold italic">{{ bookingRecord.display_name || bookingRecord.customer_name }}</h3>
                    <span class="rounded-full border border-cyan-300/20 bg-cyan-300/10 px-2 py-0.5 text-[10px] uppercase tracking-[0.2em] text-cyan-100">
                        {{ bookingRecord.booking_kind_label }}
                    </span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full px-2.5 py-1 text-[11px] font-medium" :class="bookingRecord.status === 'confirmed' ? 'bg-emerald-400/15 text-emerald-200' : bookingRecord.status === 'pending' ? 'bg-amber-300/15 text-amber-200' : bookingRecord.status === 'completed' ? 'bg-cyan-300/15 text-cyan-200' : 'bg-rose-400/15 text-rose-200'">
                    {{ statusLabel(bookingRecord.status) }}
                </span>
                <button type="button" class="rounded-lg border border-cyan-300/30 px-3 py-1.5 text-sm font-medium text-cyan-100 transition hover:bg-cyan-300/10 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving" @click="grantClientAccess">
                    {{ saving ? 'Sending...' : (bookingRecord.client_portal_access_granted ? 'Resend Access' : 'Grant Access') }}
                </button>
                <button type="button" class="rounded-lg border border-rose-300/30 px-3 py-1.5 text-sm font-medium text-rose-100 transition hover:bg-rose-300/10" @click="isEditing ? cancelEditing() : startEditing()">
                    {{ isEditing ? 'Cancel edit' : 'Edit Booking' }}
                </button>
                <a :href="data.routes.bookings" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm text-stone-300 transition hover:bg-white/5">Back to list</a>
            </div>
        </div>

        <div class="p-4">
            <form v-if="isEditing" class="mb-3 rounded-xl border border-rose-300/20 bg-rose-300/5 p-3.5" novalidate @submit.prevent="updateBooking">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.3em] text-rose-200">Edit Booking</p>
                        <p class="mt-0.5 text-xs text-stone-300">Update the booking details, package, items, and status.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-white/5" @click="cancelEditing">Cancel</button>
                        <button type="submit" class="rounded-lg bg-rose-300 px-3 py-1.5 text-sm font-semibold text-slate-950 transition hover:bg-rose-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                            {{ saving ? 'Saving...' : 'Save changes' }}
                        </button>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="xl:col-span-4">
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Booking Type</label>
                        <div class="grid gap-2 sm:grid-cols-3">
                            <button v-for="kind in data.bookingKinds" :key="kind" type="button" class="rounded-lg border px-3 py-1.5 text-sm font-medium transition" :class="editForm.booking_kind === kind ? 'border-rose-300/40 bg-rose-300/10 text-white' : 'border-white/10 text-stone-300 hover:bg-white/5'" @click="editForm.booking_kind = kind">
                                {{ bookingKindLabel(kind) }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Status</label>
                        <select v-model="editForm.booking_status_id" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(editValidationErrors, 'booking_status_id') ? 'border-rose-300/60' : ''">
                            <option v-for="status in bookingStatusOptions" :key="status.id" :value="String(status.id)">{{ status.label }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">{{ isEntryBooking ? 'Entry Name' : 'Customer Name' }}</label>
                        <input v-if="isEntryBooking" v-model="editForm.entry_name" type="text" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(editValidationErrors, 'entry_name') ? 'border-rose-300/60' : ''">
                        <input v-else v-model="editForm.customer_name" type="text" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(editValidationErrors, 'customer_name') ? 'border-rose-300/60' : ''">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Email</label>
                        <input v-model="editForm.customer_email" type="email" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(editValidationErrors, 'customer_email') ? 'border-rose-300/60' : ''">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Phone</label>
                        <input v-model="editForm.customer_phone" type="text" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(editValidationErrors, 'customer_phone') ? 'border-rose-300/60' : ''">
                    </div>
                    <div v-if="isEntryBooking" class="xl:col-span-4">
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Entry Description</label>
                        <textarea v-model="editForm.entry_description" rows="2" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50" />
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Package</label>
                        <select v-model="editForm.package_id" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50 disabled:cursor-not-allowed disabled:opacity-50" :class="firstError(editValidationErrors, 'package_id') ? 'border-rose-300/60' : ''" :disabled="invoiceAmountLocked">
                            <option disabled value="">Select a package</option>
                            <option v-for="entry in packages" :key="entry.id" :value="String(entry.id)">{{ entry.name }} - ${{ entry.display_price }}</option>
                        </select>
                    </div>
                    <div v-if="selectedPackageHourlyPrices.length">
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Package Timing</label>
                        <select v-model="editForm.package_hourly_price_id" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="invoiceAmountLocked">
                            <option v-for="option in selectedPackageHourlyPrices" :key="option.id" :value="String(option.id)">{{ Number(option.hours).toFixed(2) }} hrs - ${{ option.price }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Discount</label>
                        <select v-model="editForm.discount_id" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="invoiceAmountLocked">
                            <option value="">No discount</option>
                            <option v-for="discount in availableDiscountOptions" :key="discount.id" :value="String(discount.id)">{{ discount.code }} - {{ discount.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Event Type</label>
                        <select v-model="editForm.event_type" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50">
                            <option v-for="eventType in data.eventTypes" :key="eventType" :value="eventType">{{ eventType }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Event Date</label>
                        <input v-model="editForm.event_date" type="date" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(editValidationErrors, 'event_date') ? 'border-rose-300/60' : ''" @click="openDatePicker" @keydown.prevent>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Start Hour</label>
                        <input v-model="editForm.start_time" type="time" step="1800" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(editValidationErrors, 'start_time') ? 'border-rose-300/60' : ''">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">End Hour</label>
                        <input v-model="editForm.end_time" type="time" step="1800" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(editValidationErrors, 'end_time') ? 'border-rose-300/60' : ''">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Duration</label>
                        <input v-model="editForm.total_hours" type="number" min="0.25" step="0.25" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50 disabled:cursor-not-allowed disabled:opacity-50" :class="firstError(editValidationErrors, 'total_hours') ? 'border-rose-300/60' : ''" :disabled="invoiceAmountLocked">
                    </div>
                    <div class="xl:col-span-4">
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Location</label>
                        <input v-model="editForm.event_location" data-google-address type="text" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(editValidationErrors, 'event_location') ? 'border-rose-300/60' : ''">
                    </div>
                    <div class="xl:col-span-4">
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Items</label>
                        <p v-if="invoiceAmountLocked" class="mb-2 text-xs text-amber-200">Invoice amounts are locked because this invoice is already partially or fully paid.</p>
                        <div class="overflow-hidden rounded-xl border border-white/10">
                            <div class="grid grid-cols-[2rem_6rem_minmax(0,1fr)_8rem_5.5rem_9.5rem_6rem] gap-2 bg-white/[0.04] px-2.5 py-1.5 text-[10px] uppercase tracking-[0.18em] text-stone-500">
                                <span></span>
                                <span>Type</span>
                                <span>Item</span>
                                <span>Details</span>
                                <span>Price</span>
                                <span>Discount</span>
                                <span>Final</span>
                            </div>
                            <button v-for="item in combinedOptionalItems" :key="`${item.selection_key}-${item.id}`" type="button" class="grid w-full grid-cols-[2rem_6rem_minmax(0,1fr)_8rem_5.5rem_9.5rem_6rem] gap-2 border-t border-white/10 px-2.5 py-2 text-left text-sm transition disabled:cursor-not-allowed disabled:opacity-70" :class="item.selected ? (item.tone === 'cyan' ? 'bg-cyan-300/10 text-white' : 'bg-rose-300/10 text-white') : 'text-stone-300 hover:bg-white/[0.03]'" :disabled="invoiceAmountLocked" @click="toggleMultiSelect(item.selection_key, item.id)">
                                <span class="flex items-center justify-center">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-md border text-[11px] font-semibold" :class="item.selected ? (item.tone === 'cyan' ? 'border-cyan-300/40 bg-cyan-300/20 text-cyan-100' : 'border-rose-300/40 bg-rose-300/20 text-rose-100') : 'border-white/10 text-stone-500'">{{ item.selected ? '✓' : '' }}</span>
                                </span>
                                <span>{{ item.type_label }}</span>
                                <span class="truncate font-medium">{{ item.name }}</span>
                                <span>{{ item.details_label }}</span>
                                <span>${{ item.price_label }}</span>
                                <span class="flex gap-1">
                                    <select
                                        :value="itemDiscountType(item)"
                                        class="w-[4.2rem] rounded-lg border border-white/10 bg-slate-950/70 px-2 py-1 text-xs text-white outline-none transition focus:border-rose-300/50 disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="invoiceAmountLocked || !item.selected"
                                        @click.stop
                                        @keydown.stop
                                        @change="setItemDiscountType(item, $event.target.value)"
                                    >
                                        <option value="percentage">%</option>
                                        <option value="amount">$</option>
                                    </select>
                                    <input
                                        :value="itemDiscountValue(item)"
                                        type="number"
                                        min="0"
                                        :max="itemDiscountType(item) === 'percentage' ? 100 : undefined"
                                        step="0.01"
                                        class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-2 py-1 text-right text-sm text-white outline-none transition focus:border-rose-300/50 disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="invoiceAmountLocked || !item.selected"
                                        @click.stop
                                        @keydown.stop
                                        @input="setItemDiscountValue(item, $event.target.value)"
                                    >
                                </span>
                                <span>${{ itemFinalPrice(item) }}</span>
                            </button>
                        </div>
                    </div>
                    <div class="xl:col-span-4">
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Notes</label>
                        <textarea v-model="editForm.notes" rows="3" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50" />
                    </div>
                </div>
            </form>

            <div v-if="!isEditing" class="mb-3 flex items-center gap-2 rounded-xl border border-white/10 bg-slate-950/40 p-1">
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'overview' ? 'bg-rose-300 text-slate-950' : 'text-stone-300 hover:bg-white/5'" @click="activeTab = 'overview'">Overview</button>
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'tasks' ? 'bg-rose-300 text-slate-950' : 'text-stone-300 hover:bg-white/5'" @click="activeTab = 'tasks'">Task List</button>
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'invoice' ? 'bg-rose-300 text-slate-950' : 'text-stone-300 hover:bg-white/5'" @click="activeTab = 'invoice'">Invoice</button>
            </div>

            <div v-if="!isEditing && activeTab === 'overview'" class="space-y-3">
                <div class="grid gap-2.5 sm:grid-cols-2">
                    <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Quote Number</p>
                        <p class="mt-1.5 text-sm font-semibold text-white">{{ bookingRecord.quote_number || 'Not assigned' }}</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Event Date</p>
                        <p class="mt-1.5 text-sm font-semibold">{{ bookingRecord.event_date_label }}</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5 sm:col-span-2">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">{{ bookingRecord.booking_kind === 'customer' ? 'Customer Name' : 'Entry Name' }}</p>
                        <p class="mt-1.5 text-sm font-semibold text-white">{{ bookingRecord.display_name || bookingRecord.customer_name }}</p>
                        <p v-if="bookingRecord.entry_description" class="mt-2 text-sm leading-5 text-stone-300">{{ bookingRecord.entry_description }}</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Email</p>
                        <p class="mt-1.5 text-sm font-medium text-stone-200">{{ bookingRecord.customer_email }}</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Phone</p>
                        <p class="mt-1.5 text-sm font-medium text-stone-200">{{ bookingRecord.customer_phone }}</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5 sm:col-span-2">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Client Portal Access</p>
                        <p class="mt-1.5 text-sm font-medium text-stone-200">{{ bookingRecord.client_portal_access_granted ? 'Granted' : 'Not granted yet' }}</p>
                        <p v-if="bookingRecord.client_portal_access_granted_at_label" class="mt-1 text-xs text-stone-400">
                            Last email sent {{ bookingRecord.client_portal_access_granted_at_label }}
                        </p>
                        <a v-if="bookingRecord.client_portal_access_url" :href="bookingRecord.client_portal_access_url" target="_blank" rel="noreferrer" class="mt-2 inline-flex text-xs font-medium text-cyan-200 hover:text-cyan-100">
                            Open portal link
                        </a>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Event Type</p>
                        <p class="mt-1.5 text-sm font-medium text-stone-200">{{ bookingRecord.event_type_label || 'Not set' }}</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Travel Fee</p>
                        <p class="mt-1.5 text-sm font-medium text-stone-200">${{ bookingRecord.travel_fee }}</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Discount</p>
                        <p class="mt-1.5 text-sm font-medium text-stone-200">
                            {{ bookingRecord.discount ? `${bookingRecord.discount.code} - ${bookingRecord.discount.name}` : 'No discount selected' }}
                        </p>
                        <p class="mt-1 text-xs text-emerald-200">-${{ bookingRecord.discount_amount }}</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5 sm:col-span-2">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Booking Hours</p>
                        <div class="mt-2 grid gap-2 sm:grid-cols-3">
                            <div class="rounded-lg border border-white/10 bg-slate-900/60 p-2.5">
                                <p class="text-[11px] uppercase tracking-[0.25em] text-stone-500">Start Hour</p>
                                <p class="mt-1.5 text-sm font-medium text-stone-200">{{ bookingRecord.start_time_label || 'Not set' }}</p>
                            </div>
                            <div class="rounded-lg border border-white/10 bg-slate-900/60 p-2.5">
                                <p class="text-[11px] uppercase tracking-[0.25em] text-stone-500">End Hour</p>
                                <p class="mt-1.5 text-sm font-medium text-stone-200">{{ bookingRecord.end_time_label || 'Not set' }}</p>
                            </div>
                            <div class="rounded-lg border border-white/10 bg-slate-900/60 p-2.5">
                                <p class="text-[11px] uppercase tracking-[0.25em] text-stone-500">Duration</p>
                                <p class="mt-1.5 text-sm font-medium text-stone-200">{{ bookingRecord.total_hours }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Event Location</p>
                    <p class="mt-2 text-sm leading-5 text-stone-300">{{ bookingRecord.event_location }}</p>
                    <p v-if="bookingRecord.notes" class="mt-3 text-sm leading-5 text-stone-400">{{ bookingRecord.notes }}</p>
                </div>

                <div class="overflow-hidden rounded-xl border border-white/10 bg-slate-950/50">
                    <div class="flex items-center justify-between gap-3">
                        <p class="px-2.5 py-2.5 text-[11px] uppercase tracking-[0.3em] text-stone-500">Selected Items</p>
                        <span class="mr-3 rounded-full bg-white/5 px-2.5 py-1 text-[11px] text-stone-300">{{ overviewSelectedItems.length }}</span>
                    </div>
                    <div v-if="overviewSelectedItems.length" class="overflow-x-auto border-t border-white/10">
                        <div class="min-w-[920px]">
                            <div class="grid grid-cols-[8rem_minmax(0,1fr)_12rem_8rem_minmax(0,1.2fr)] gap-2 border-b border-white/10 px-2.5 py-1.5 text-[10px] uppercase tracking-[0.18em] text-stone-500">
                                <span>Type</span>
                                <span>Item</span>
                                <span>Details</span>
                                <span>Price</span>
                                <span>Description</span>
                            </div>
                            <div v-for="item in overviewSelectedItems" :key="item.id" class="grid grid-cols-[8rem_minmax(0,1fr)_12rem_8rem_minmax(0,1.2fr)] items-center gap-2 border-b border-white/10 px-2.5 py-2 last:border-b-0">
                                <p class="text-sm font-medium text-cyan-100">{{ item.type_label }}</p>
                                <p class="truncate text-sm font-medium text-white">{{ item.name }}</p>
                                <p class="truncate text-sm text-stone-300">{{ item.details }}</p>
                                <p class="text-sm font-semibold text-cyan-100">${{ item.price }}</p>
                                <p class="truncate text-sm text-stone-400">{{ item.description }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="border-t border-white/10 px-2.5 py-2.5 text-sm leading-5 text-stone-400">No package, equipment, or add-ons were selected for this booking.</p>
                </div>
            </div>

            <div v-if="!isEditing && activeTab === 'tasks'" class="space-y-3">
                <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Booking Tasks</p>
                            <p class="mt-1 text-sm text-stone-300">Package action items are added here automatically, and you can still add general tasks manually.</p>
                        </div>
                        <button v-if="editingTask" type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm text-stone-300 transition hover:bg-white/5" @click="cancelTaskEdit">Cancel edit</button>
                    </div>

                    <form class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" novalidate @submit.prevent="saveTask">
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Task Name</label>
                            <input v-model="taskForm.task_name" type="text" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(taskValidationErrors, 'task_name') ? 'border-rose-300/60' : ''">
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Status</label>
                            <select v-model="taskForm.task_status_id" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(taskValidationErrors, 'task_status_id') ? 'border-rose-300/60' : ''">
                                <option v-for="status in taskStatuses" :key="status.id" :value="String(status.id)">{{ status.label ?? status.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Assigned To</label>
                            <select v-model="taskForm.assigned_to" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50">
                                <option value="">Unassigned</option>
                                <optgroup v-for="(options, group) in taskAssigneeGroups" :key="group" :label="group">
                                    <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </optgroup>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Hours</label>
                            <input v-model="taskForm.task_duration_hours" type="number" min="0" step="0.25" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50">
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Due Date</label>
                            <input v-model="taskForm.due_date" type="date" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" @click="openDatePicker" @keydown.prevent>
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Date Started</label>
                            <input v-model="taskForm.date_started" type="date" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" @click="openDatePicker" @keydown.prevent>
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Date Completed</label>
                            <input v-model="taskForm.date_completed" type="date" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" @click="openDatePicker" @keydown.prevent>
                        </div>
                        <div class="xl:col-span-4">
                            <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Remarks</label>
                            <textarea v-model="taskForm.remarks" rows="3" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" />
                        </div>
                        <div class="xl:col-span-4 flex justify-end">
                            <button type="submit" class="rounded-lg bg-cyan-300 px-3 py-1.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                                {{ saving ? 'Saving...' : editingTask ? 'Save task' : 'Add task' }}
                            </button>
                        </div>
                    </form>
                </div>

                <div class="overflow-hidden rounded-xl border border-white/10 bg-slate-950/50">
                    <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Task List</p>
                        <span class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] text-stone-300">{{ tasks.length }}</span>
                    </div>
                    <div v-if="tasks.length" class="overflow-x-auto border-t border-white/10">
                        <div class="min-w-[980px]">
                            <div class="grid grid-cols-[minmax(0,1.2fr)_10rem_10rem_8rem_8rem_8rem_minmax(0,1fr)_8rem] gap-2 border-b border-white/10 px-3 py-1.5 text-[10px] uppercase tracking-[0.18em] text-stone-500">
                                <span>Task</span>
                                <span>Status</span>
                                <span>Assigned To</span>
                                <span>Hours</span>
                                <span>Due Date</span>
                                <span>Started</span>
                                <span>Remarks</span>
                                <span>Actions</span>
                            </div>
                            <div v-for="task in tasks" :key="task.id" class="grid grid-cols-[minmax(0,1.2fr)_10rem_10rem_8rem_8rem_8rem_minmax(0,1fr)_8rem] items-center gap-2 border-b border-white/10 px-3 py-2 last:border-b-0">
                                <p class="truncate text-sm font-medium text-white">{{ task.task_name }}</p>
                                <p class="truncate text-sm text-cyan-100">{{ task.status_name || 'No status' }}</p>
                                <p class="truncate text-sm text-stone-300">{{ task.assigned_to_name }}</p>
                                <p class="text-sm text-stone-300">{{ task.task_duration_hours || '0.00' }}</p>
                                <p class="text-sm text-stone-300">{{ task.due_date_label }}</p>
                                <p class="text-sm text-stone-300">{{ task.date_started_label }}</p>
                                <p class="truncate text-sm text-stone-400">{{ task.remarks || 'No remarks' }}</p>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="rounded-lg border border-white/10 px-2.5 py-1 text-xs font-semibold text-white transition hover:bg-white/5" @click="startTaskEdit(task)">Edit</button>
                                    <button type="button" class="rounded-lg border border-rose-400/30 px-2.5 py-1 text-xs font-semibold text-rose-100 transition hover:bg-rose-400/10" @click="removeTask(task)">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="border-t border-white/10 px-3 py-3 text-sm text-stone-400">No tasks have been attached to this booking yet.</p>
                </div>
            </div>

            <div v-if="isEditing || activeTab === 'invoice'" class="mt-3 rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Invoice</p>
                        <p class="mt-1.5 text-sm font-semibold text-white">Booking Total: ${{ bookingRecord.booking_total }}</p>
                        <p class="mt-1 text-xs text-emerald-200">Discount Applied: -${{ bookingRecord.discount_amount }}</p>
                    </div>
                    <a
                        v-if="bookingRecord.invoice?.public_url"
                        :href="bookingRecord.invoice.public_url"
                        target="_blank"
                        rel="noreferrer"
                        class="rounded-lg border border-cyan-300/30 px-3 py-1.5 text-sm font-medium text-cyan-100 transition hover:border-cyan-200/60 hover:bg-cyan-300/10"
                    >
                        Open customer invoice
                    </a>
                </div>

                <div v-if="bookingRecord.invoice" class="mt-3 space-y-2.5">
                    <div class="flex justify-end">
                        <button type="button" class="rounded-lg bg-cyan-300 px-3 py-1.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving" @click="sendInvoice">
                            {{ saving ? 'Sending...' : 'Send invoice email' }}
                        </button>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <div class="rounded-xl border border-white/10 bg-slate-900/70 p-2.5">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Invoice</p>
                            <p class="mt-1.5 text-sm font-semibold text-white">{{ bookingRecord.invoice.invoice_number }}</p>
                            <p class="mt-1 text-xs text-stone-400">{{ statusLabel(bookingRecord.invoice.status) }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-900/70 p-2.5">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Paid</p>
                            <p class="mt-1.5 text-sm font-semibold text-emerald-200">${{ bookingRecord.invoice.amount_paid }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-900/70 p-2.5">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Balance Due</p>
                            <p class="mt-1.5 text-sm font-semibold text-amber-200">${{ bookingRecord.invoice.balance_due }}</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <article v-for="installment in bookingRecord.invoice.installments" :key="installment.id" class="rounded-xl border border-white/10 bg-slate-900/70 p-2.5">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-white">{{ installment.label }}</p>
                                    <p class="mt-1 text-xs text-stone-400">Due {{ installment.due_date_label }}</p>
                                    <p v-if="installment.paid_at_label" class="mt-1 text-xs text-emerald-200">Paid {{ installment.paid_at_label }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-white">${{ installment.amount }}</p>
                                    <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium" :class="installment.status === 'paid' ? 'bg-emerald-400/15 text-emerald-200' : 'bg-amber-300/15 text-amber-200'">
                                        {{ statusLabel(installment.status) }}
                                    </span>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

                <form v-else class="mt-3 space-y-3" novalidate @submit.prevent="createInvoice">
                    <div class="grid gap-3 sm:grid-cols-4">
                        <div>
                            <label class="mb-1 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Installments</label>
                            <input v-model="invoiceForm.installment_count" type="number" min="1" max="12" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(invoiceValidationErrors, 'installment_count') ? 'border-rose-300/60' : ''">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Deposit %</label>
                            <input v-model="invoiceForm.deposit_percentage" type="number" min="0" max="100" step="0.01" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(invoiceValidationErrors, 'deposit_percentage') ? 'border-rose-300/60' : ''">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">First Due Date</label>
                            <input v-model="invoiceForm.first_due_date" type="date" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(invoiceValidationErrors, 'first_due_date') ? 'border-rose-300/60' : ''" @click="openDatePicker" @keydown.prevent>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Interval Days</label>
                            <input v-model="invoiceForm.interval_days" type="number" min="1" max="90" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(invoiceValidationErrors, 'interval_days') ? 'border-rose-300/60' : ''">
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-lg bg-cyan-300 px-3 py-1.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                            {{ saving ? 'Creating...' : 'Create invoice' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</template>
