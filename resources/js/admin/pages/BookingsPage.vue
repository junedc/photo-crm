<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';
import { autoAttachGoogleAddressInputs } from '../../googleAddressAutocomplete';
import { firstError, hasFieldErrors, isBlank, mergeFieldErrors, requiredMessage, validEmail } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const bookingList = ref([...(props.data.bookings ?? [])]);
const bookings = computed(() => bookingList.value);
const bookingSearch = ref('');
const bookingStatusFilter = ref('all');
const pagination = ref(props.data.pagination ?? { total: bookings.value.length, has_more: false, next_page: null });
const loadingMore = ref(false);
const selectedId = ref(props.data.selectedId ?? null);
const showCreateModal = ref(false);
const showDetailModal = ref(Boolean(props.data.selectedId));
const { saving, fieldErrors, submitForm } = useWorkspaceCrud();
let bookingRefreshInterval = null;
let requestId = 0;

const bookingKinds = computed(() => props.data.bookingKinds ?? []);
const packages = computed(() => props.data.packages ?? []);
const equipmentOptions = computed(() => props.data.equipmentOptions ?? []);
const addOnOptions = computed(() => props.data.addOnOptions ?? []);
const discountOptions = computed(() => props.data.discountOptions ?? []);

const editForm = ref({});
const createErrors = ref({});
const invoiceErrors = ref({});
const editErrors = ref({});
const invoiceForm = ref({
    installment_count: '3',
    deposit_percentage: String(props.data.defaultDepositPercentage ?? 30),
    first_due_date: '',
    interval_days: '30',
});
const createForm = ref({});
const createValidationErrors = computed(() => mergeFieldErrors(createErrors.value, fieldErrors.value));
const invoiceValidationErrors = computed(() => mergeFieldErrors(invoiceErrors.value, fieldErrors.value));
const editValidationErrors = computed(() => mergeFieldErrors(editErrors.value, fieldErrors.value));

const selectedBooking = computed(() => bookings.value.find((entry) => entry.id === selectedId.value) ?? null);
const selectedPackage = computed(() =>
    packages.value.find((entry) => String(entry.id) === String(createForm.value.package_id ?? '')) ?? null,
);
const selectedPackageHourlyPrices = computed(() => selectedPackage.value?.hourly_prices ?? []);
const availableDiscountOptions = computed(() => {
    const packageId = Number(createForm.value.package_id ?? 0);
    const equipmentIds = new Set((createForm.value.equipment_ids ?? []).map((id) => Number(id)));

    return discountOptions.value.filter((discount) => {
        const packageMatch = discount.package_ids?.includes(packageId);
        const equipmentMatch = (discount.equipment_ids ?? []).some((id) => equipmentIds.has(Number(id)));

        return packageMatch || equipmentMatch;
    });
});
const bookingKindLabelMap = {
    customer: 'Customer Booking',
    market_stall: 'Market Stall',
    sponsored: 'Sponsored',
};

const isEntryBooking = computed(() => createForm.value.booking_kind === 'market_stall' || createForm.value.booking_kind === 'sponsored');
const bookingKindLabel = (kind) => bookingKindLabelMap[kind] ?? 'Customer Booking';
const statusLabel = (status) => (status || '').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
const invoiceStatusLabel = (status) => statusLabel(status);
const packageLabel = (entry) => `${entry.name} - $${entry.display_price}`;
const addOnSummary = (addOn) => [addOn.product_code, addOn.duration].filter(Boolean).join(' - ');
const equipmentSummary = (item) => [item.category, `$${item.price ?? item.daily_rate}`].filter(Boolean).join(' - ');
const discountLabel = (discount) => `${discount.code} - ${discount.name}`;
const discountValueLabel = (discount) => (
    discount.discount_type === 'percentage'
        ? `${discount.discount_value}%`
        : `$${discount.discount_value}`
);
const entryHeading = computed(() => (createForm.value.booking_kind === 'market_stall' ? 'Stall Details' : createForm.value.booking_kind === 'sponsored' ? 'Sponsored Details' : 'Customer Details'));
const createBookingSummaryLabel = computed(() => (isEntryBooking.value ? 'Entry' : 'Customer'));
const createBookingSummaryName = computed(() => {
    const name = isEntryBooking.value ? createForm.value.entry_name : createForm.value.customer_name;

    return name?.trim() || `${createBookingSummaryLabel.value} name not entered`;
});

const openDatePicker = (event) => {
    try {
        event.target?.showPicker?.();
    } catch {
        // Native browser picker fallback.
    }
};

const resetCreateForm = () => {
    const firstPackage = packages.value[0];
    const firstHourlyPrice = firstPackage?.hourly_prices?.[0] ?? null;

    createForm.value = {
        booking_kind: bookingKinds.value[0] ?? 'customer',
        entry_name: '',
        entry_description: '',
        package_id: firstPackage?.id ? String(firstPackage.id) : '',
        package_hourly_price_id: firstHourlyPrice?.id ? String(firstHourlyPrice.id) : '',
        customer_name: '',
        customer_email: '',
        customer_phone: '',
        event_type: props.data.eventTypes?.[0] ?? 'Wedding',
        event_date: '',
        start_time: '',
        end_time: '',
        total_hours: firstHourlyPrice?.hours ?? '0.00',
        event_location: '',
        notes: '',
        discount_id: '',
        equipment_ids: [],
        add_on_ids: [],
    };
    createErrors.value = {};

    syncBookingKindDefaults();
    syncPackageTimingDefaults();
    syncEndTime();
};

const syncEditForm = (entry) => {
    editForm.value = entry
        ? {
              status: entry.status ?? 'pending',
              notes: entry.notes ?? '',
          }
        : {};
};

const resetInvoiceForm = () => {
    const nextWeek = new Date();
    nextWeek.setDate(nextWeek.getDate() + 7);

    invoiceForm.value = {
        installment_count: '3',
        deposit_percentage: String(props.data.defaultDepositPercentage ?? 30),
        first_due_date: nextWeek.toISOString().slice(0, 10),
        interval_days: '30',
    };
};

const syncBookingKindDefaults = () => {
    if (isEntryBooking.value) {
        createForm.value.event_type = 'Others';
    } else if (!props.data.eventTypes?.includes(createForm.value.event_type)) {
        createForm.value.event_type = props.data.eventTypes?.[0] ?? 'Wedding';
    }
};

const syncPackageTimingDefaults = () => {
    if (!selectedPackage.value) {
        createForm.value.package_hourly_price_id = '';
        return;
    }

    if (!selectedPackageHourlyPrices.value.length) {
        createForm.value.package_hourly_price_id = '';
        return;
    }

    const currentSelected = selectedPackageHourlyPrices.value.find((entry) => String(entry.id) === String(createForm.value.package_hourly_price_id ?? ''));

    if (!currentSelected) {
        const lowestHourly = [...selectedPackageHourlyPrices.value]
            .sort((left, right) => Number(left.hours) - Number(right.hours))[0];

        createForm.value.package_hourly_price_id = lowestHourly ? String(lowestHourly.id) : '';
    }
};

const syncDurationFromPackageTiming = () => {
    if (!selectedPackageHourlyPrices.value.length) {
        return;
    }

    const selectedHourly = selectedPackageHourlyPrices.value.find((entry) => String(entry.id) === String(createForm.value.package_hourly_price_id ?? ''));

    if (selectedHourly) {
        createForm.value.total_hours = Number(selectedHourly.hours).toFixed(2);
    }
};

const syncEndTime = () => {
    if (!createForm.value.start_time || !createForm.value.total_hours) {
        createForm.value.end_time = '';
        return;
    }

    const [startHour, startMinute] = createForm.value.start_time.split(':').map(Number);

    if (Number.isNaN(startHour) || Number.isNaN(startMinute)) {
        createForm.value.end_time = '';
        return;
    }

    const totalMinutes = Math.round(Number(createForm.value.total_hours || 0) * 60);
    const endMinutes = startHour * 60 + startMinute + totalMinutes;
    const normalizedMinutes = ((endMinutes % (24 * 60)) + (24 * 60)) % (24 * 60);
    const hours = String(Math.floor(normalizedMinutes / 60)).padStart(2, '0');
    const minutes = String(normalizedMinutes % 60).padStart(2, '0');

    createForm.value.end_time = `${hours}:${minutes}`;
};

const toggleMultiSelect = (key, id) => {
    const values = new Set(createForm.value[key] ?? []);

    if (values.has(id)) {
        values.delete(id);
    } else {
        values.add(id);
    }

    createForm.value[key] = [...values];
};

watch(selectedBooking, (entry) => {
    syncEditForm(entry);
    resetInvoiceForm();
}, { immediate: true });

watch(() => createForm.value.booking_kind, () => {
    syncBookingKindDefaults();
});

watch(() => createForm.value.package_id, () => {
    syncPackageTimingDefaults();
    syncDurationFromPackageTiming();
    syncEndTime();

    if (!availableDiscountOptions.value.some((entry) => String(entry.id) === String(createForm.value.discount_id ?? ''))) {
        createForm.value.discount_id = '';
    }
});

watch(() => createForm.value.equipment_ids, () => {
    if (!availableDiscountOptions.value.some((entry) => String(entry.id) === String(createForm.value.discount_id ?? ''))) {
        createForm.value.discount_id = '';
    }
}, { deep: true });

watch(() => createForm.value.package_hourly_price_id, () => {
    syncDurationFromPackageTiming();
    syncEndTime();
});

watch(() => createForm.value.total_hours, () => {
    syncEndTime();
});

watch(() => createForm.value.start_time, () => {
    syncEndTime();
});

const fetchBookings = async (page = 1, append = false) => {
    const currentRequestId = ++requestId;
    loadingMore.value = true;

    const params = new URLSearchParams({
        page: String(page),
        search: bookingSearch.value.trim(),
        status: bookingStatusFilter.value,
    });

    try {
        const response = await fetch(`${props.data.routes.bookings}?${params.toString()}`, {
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
        bookingList.value = append ? [...bookingList.value, ...(payload.records ?? [])] : [...(payload.records ?? [])];
        pagination.value = payload.pagination ?? pagination.value;
    } finally {
        if (currentRequestId === requestId) {
            loadingMore.value = false;
        }
    }
};

watch([bookingSearch, bookingStatusFilter], () => fetchBookings(1, false));

watch(showCreateModal, async (open) => {
    if (!open) {
        return;
    }

    await nextTick();
    autoAttachGoogleAddressInputs();
});

const refreshSelectedBooking = async () => {
    if (!selectedBooking.value?.show_url) {
        return;
    }

    try {
        const response = await fetch(selectedBooking.value.show_url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const result = await response.json();
        const record = result?.record;

        if (!record?.id) {
            return;
        }

        bookingList.value = bookingList.value.map((entry) => (entry.id === record.id ? record : entry));
        syncEditForm(record);
    } catch {}
};

const stopBookingRefresh = () => {
    if (bookingRefreshInterval) {
        window.clearInterval(bookingRefreshInterval);
        bookingRefreshInterval = null;
    }
};

const startBookingRefresh = () => {
    stopBookingRefresh();

    if (!showDetailModal.value || !selectedBooking.value) {
        return;
    }

    bookingRefreshInterval = window.setInterval(() => {
        refreshSelectedBooking();
    }, 10000);
};

watch([showDetailModal, selectedId], async ([open, id]) => {
    if (!open || !id) {
        stopBookingRefresh();
        return;
    }

    await refreshSelectedBooking();
    startBookingRefresh();
}, { immediate: true });

const selectBooking = (entry) => {
    selectedId.value = entry.id;
    syncEditForm(entry);
    showDetailModal.value = true;
    window.history.replaceState({}, '', entry.show_url);
};

const openCreateModal = () => {
    resetCreateForm();
    showCreateModal.value = true;
};

const loadMoreBookings = () => pagination.value.next_page && fetchBookings(pagination.value.next_page, true);

const closeCreateModal = () => {
    showCreateModal.value = false;
};

const closeDetailModal = () => {
    showDetailModal.value = false;
    selectedId.value = null;
    const currentUrl = new URL(window.location.href);
    const returnTo = currentUrl.searchParams.get('return_to');

    if (returnTo) {
        window.location.href = returnTo;
        return;
    }

    window.history.replaceState({}, '', props.data.routes.bookings);
};

const onWindowKeydown = (event) => {
    if (event.key === 'Escape') {
        if (showDetailModal.value) {
            closeDetailModal();
        }

        if (showCreateModal.value) {
            closeCreateModal();
        }
    }
};

onMounted(() => {
    nextTick(() => autoAttachGoogleAddressInputs());
    window.addEventListener('keydown', onWindowKeydown);
    window.addEventListener('focus', refreshSelectedBooking);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onWindowKeydown);
    window.removeEventListener('focus', refreshSelectedBooking);
    stopBookingRefresh();
});

const createBooking = async () => {
    const errors = {};

    if (isBlank(createForm.value.package_id)) {
        errors.package_id = requiredMessage('Package');
    }

    if (isEntryBooking.value && isBlank(createForm.value.entry_name)) {
        errors.entry_name = requiredMessage('Entry name');
    }

    if (isBlank(createForm.value.customer_name)) {
        errors.customer_name = requiredMessage(isEntryBooking.value ? 'Invoice contact name' : 'Customer name');
    }

    if (isBlank(createForm.value.customer_email)) {
        errors.customer_email = requiredMessage(isEntryBooking.value ? 'Invoice email' : 'Email');
    } else if (!validEmail(createForm.value.customer_email)) {
        errors.customer_email = 'Enter a valid email address.';
    }

    if (isBlank(createForm.value.customer_phone)) {
        errors.customer_phone = requiredMessage(isEntryBooking.value ? 'Invoice phone' : 'Phone');
    }

    if (isBlank(createForm.value.event_date)) {
        errors.event_date = requiredMessage('Event date');
    }

    if (isBlank(createForm.value.start_time)) {
        errors.start_time = requiredMessage('Start hour');
    }

    if (isBlank(createForm.value.total_hours) || Number(createForm.value.total_hours) < 0.25) {
        errors.total_hours = 'Hour duration must be at least 0.25.';
    }

    if (isBlank(createForm.value.end_time)) {
        errors.end_time = 'End hour is required. Choose a start hour and duration.';
    }

    if (isBlank(createForm.value.event_location)) {
        errors.event_location = requiredMessage('Location');
    }

    createErrors.value = errors;

    if (hasFieldErrors(errors)) {
        return;
    }

    const formData = new FormData();
    formData.append('booking_kind', createForm.value.booking_kind ?? 'customer');
    formData.append('entry_name', createForm.value.entry_name ?? '');
    formData.append('entry_description', createForm.value.entry_description ?? '');
    formData.append('package_id', createForm.value.package_id ?? '');
    formData.append('package_hourly_price_id', createForm.value.package_hourly_price_id ?? '');
    formData.append('customer_name', createForm.value.customer_name ?? '');
    formData.append('customer_email', createForm.value.customer_email ?? '');
    formData.append('customer_phone', createForm.value.customer_phone ?? '');
    formData.append('event_type', createForm.value.event_type ?? 'Others');
    formData.append('event_date', createForm.value.event_date ?? '');
    formData.append('start_time', createForm.value.start_time ?? '');
    formData.append('end_time', createForm.value.end_time ?? '');
    formData.append('total_hours', createForm.value.total_hours ?? '0.00');
    formData.append('event_location', createForm.value.event_location ?? '');
    formData.append('notes', createForm.value.notes ?? '');
    formData.append('discount_id', createForm.value.discount_id ?? '');

    (createForm.value.equipment_ids ?? []).forEach((id) => formData.append('equipment_ids[]', String(id)));
    (createForm.value.add_on_ids ?? []).forEach((id) => formData.append('add_on_ids[]', String(id)));

    try {
        const record = await submitForm({
            url: props.data.bookingCreateUrl,
            method: 'post',
            data: formData,
        });

        bookingList.value = [record, ...bookingList.value];
        pagination.value = {
            ...pagination.value,
            total: Number(pagination.value.total ?? 0) + 1,
        };
        resetCreateForm();
        showCreateModal.value = false;
        createErrors.value = {};
        selectBooking(record);
    } catch {}
};

const createInvoice = async () => {
    if (!selectedBooking.value) {
        return;
    }

    const errors = {};

    if (isBlank(invoiceForm.value.installment_count)) {
        errors.installment_count = requiredMessage('Installments');
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

    const formData = new FormData();
    formData.append('installment_count', invoiceForm.value.installment_count ?? '3');
    formData.append('deposit_percentage', invoiceForm.value.deposit_percentage ?? '30');
    formData.append('first_due_date', invoiceForm.value.first_due_date ?? '');
    formData.append('interval_days', invoiceForm.value.interval_days ?? '30');

    try {
        const invoice = await submitForm({
            url: selectedBooking.value.invoice_create_url,
            method: 'post',
            data: formData,
        });

        const updatedRecord = {
            ...selectedBooking.value,
            invoice,
        };

        bookingList.value = bookingList.value.map((entry) => (entry.id === updatedRecord.id ? updatedRecord : entry));
        invoiceErrors.value = {};
        selectBooking(updatedRecord);
    } catch {}
};

const sendInvoice = async () => {
    if (!selectedBooking.value?.invoice?.send_url) {
        return;
    }

    try {
        const invoice = await submitForm({
            url: selectedBooking.value.invoice.send_url,
            method: 'post',
            data: new FormData(),
        });

        const updatedRecord = {
            ...selectedBooking.value,
            invoice,
        };

        bookingList.value = bookingList.value.map((entry) => (entry.id === updatedRecord.id ? updatedRecord : entry));
        selectBooking(updatedRecord);
    } catch {}
};

const updateBooking = async () => {
    if (!selectedBooking.value) {
        return;
    }

    const errors = {};

    if (isBlank(editForm.value.status)) {
        errors.status = requiredMessage('Status');
    }

    editErrors.value = errors;

    if (hasFieldErrors(errors)) {
        return;
    }

    const formData = new FormData();
    formData.append('_method', 'PUT');
    formData.append('status', editForm.value.status ?? 'pending');
    formData.append('notes', editForm.value.notes ?? '');

    try {
        const record = await submitForm({
            url: selectedBooking.value.update_url,
            method: 'post',
            data: formData,
        });

        bookingList.value = bookingList.value.map((entry) => (entry.id === record.id ? record : entry));
        editErrors.value = {};
        selectBooking(record);
    } catch {}
};
</script>

<template>
    <section class="rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-4 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-rose-200">Bookings Workspace</p>
        <h2 class="mt-2 text-xl font-semibold tracking-tight">Review customer booking requests</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-300">
            Click a booking in the list to open its details in a popup and update its status there.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
        <div class="sticky top-0 z-10 -mx-3 -mt-3 mb-3 rounded-t-2xl border-b border-white/10 bg-[#132035] px-3 pb-3 pt-3">
            <div class="px-2">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Incoming Bookings</p>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <h3 class="text-lg font-semibold">Booking list</h3>
                        <span class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 text-xs text-stone-300">{{ pagination.total ?? bookings.length }}</span>
                    </div>
                    <button type="button" class="rounded-xl bg-rose-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-rose-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="!packages.length" @click="openCreateModal">
                        Create booking
                    </button>
                </div>
            </div>
            <div class="mt-3 grid gap-2">
                <input v-model="bookingSearch" type="text" placeholder="Search name, quote number, email or package" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-rose-300/50">
                <div class="grid grid-cols-5 gap-2">
                    <button type="button" class="rounded-lg border px-2 py-1.5 text-xs font-medium transition" :class="bookingStatusFilter === 'all' ? 'border-rose-300/40 bg-rose-300/10 text-white' : 'border-white/10 text-stone-300 hover:bg-white/5'" @click="bookingStatusFilter = 'all'">All</button>
                    <button type="button" class="rounded-lg border px-2 py-1.5 text-xs font-medium transition" :class="bookingStatusFilter === 'pending' ? 'border-amber-300/40 bg-amber-300/10 text-white' : 'border-white/10 text-stone-300 hover:bg-white/5'" @click="bookingStatusFilter = 'pending'">Pending</button>
                    <button type="button" class="rounded-lg border px-2 py-1.5 text-xs font-medium transition" :class="bookingStatusFilter === 'confirmed' ? 'border-emerald-300/40 bg-emerald-300/10 text-white' : 'border-white/10 text-stone-300 hover:bg-white/5'" @click="bookingStatusFilter = 'confirmed'">Confirmed</button>
                    <button type="button" class="rounded-lg border px-2 py-1.5 text-xs font-medium transition" :class="bookingStatusFilter === 'completed' ? 'border-cyan-300/40 bg-cyan-300/10 text-white' : 'border-white/10 text-stone-300 hover:bg-white/5'" @click="bookingStatusFilter = 'completed'">Completed</button>
                    <button type="button" class="rounded-lg border px-2 py-1.5 text-xs font-medium transition" :class="bookingStatusFilter === 'cancelled' ? 'border-rose-300/40 bg-rose-300/10 text-white' : 'border-white/10 text-stone-300 hover:bg-white/5'" @click="bookingStatusFilter = 'cancelled'">Cancelled</button>
                </div>
            </div>
            <div class="mt-3 grid grid-cols-[minmax(0,1fr)_auto] gap-2 px-2 text-[11px] uppercase tracking-[0.2em] text-stone-500">
                <span>Booking</span>
                <span>Status</span>
            </div>
        </div>

        <div class="max-h-[70vh] overflow-y-auto">
            <button
                v-for="entry in bookings"
                :key="entry.id"
                type="button"
                class="grid w-full grid-cols-[minmax(0,1fr)_auto] items-center gap-3 border-b px-3 py-3 text-left transition"
                :class="selectedId === entry.id ? 'border-rose-300/30 bg-rose-300/8' : 'border-white/10 hover:bg-white/[0.03]'"
                @click="selectBooking(entry)"
            >
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="truncate text-sm font-medium text-white">{{ entry.display_name || entry.customer_name }}</p>
                        <span class="rounded-full border border-cyan-300/20 bg-cyan-300/10 px-2 py-0.5 text-[10px] uppercase tracking-[0.2em] text-cyan-100">
                            {{ entry.booking_kind_label }}
                        </span>
                    </div>
                    <p class="mt-1 truncate text-xs text-stone-400">{{ entry.package_name }} - {{ entry.event_date_label }}</p>
                </div>
                <span class="inline-flex h-8 items-center justify-center rounded-full px-3 text-[11px] font-medium leading-none" :class="entry.status === 'confirmed' ? 'bg-emerald-400/15 text-emerald-200' : entry.status === 'pending' ? 'bg-amber-300/15 text-amber-200' : entry.status === 'completed' ? 'bg-cyan-300/15 text-cyan-200' : 'bg-rose-400/15 text-rose-200'">
                    {{ statusLabel(entry.status) }}
                </span>
            </button>

            <div v-if="!bookings.length" class="rounded-2xl border border-dashed border-white/15 bg-stone-950/40 px-4 py-5 text-sm text-stone-400">
                No bookings match the current filters.
            </div>

            <div v-else-if="pagination.has_more" class="flex justify-center px-3 py-4">
                <button type="button" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:border-rose-300/40 hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loadingMore" @click="loadMoreBookings">
                    {{ loadingMore ? 'Loading...' : 'Load more bookings' }}
                </button>
            </div>
        </div>
    </section>

    <transition name="modal">
        <div v-if="showCreateModal" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm" @click.self="closeCreateModal">
            <div class="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-2xl border border-white/10 bg-[#132035] shadow-2xl shadow-black/30">
                <div class="sticky top-0 z-20 flex items-center justify-between border-b border-white/10 bg-[#132035] px-5 py-4 shadow-lg shadow-black/10">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.3em] text-rose-200">New Booking</p>
                        <h3 class="mt-1 text-lg font-semibold">Create booking, stall, or sponsored entry</h3>
                        <p class="mt-2 text-sm text-stone-300">
                            <span class="text-stone-500">{{ createBookingSummaryLabel }}:</span>
                            <span class="font-semibold text-white">{{ createBookingSummaryName }}</span>
                        </p>
                    </div>
                    <button type="button" class="rounded-lg border border-white/10 px-3 py-2 text-sm text-stone-300 transition hover:bg-white/5" @click="closeCreateModal">Close</button>
                </div>
                <form class="space-y-5 p-5" novalidate @submit.prevent="createBooking">
                    <div class="grid gap-4">
                        <div class="order-2 rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Booking Type</p>
                            <div class="mt-3 grid gap-2 sm:grid-cols-3">
                                <button
                                    v-for="kind in bookingKinds"
                                    :key="kind"
                                    type="button"
                                    class="rounded-xl border px-3 py-3 text-sm font-medium transition"
                                    :class="createForm.booking_kind === kind ? 'border-rose-300/40 bg-rose-300/10 text-white' : 'border-white/10 text-stone-300 hover:bg-white/5'"
                                    @click="createForm.booking_kind = kind"
                                >
                                    {{ bookingKindLabel(kind) }}
                                </button>
                            </div>

                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Package</label>
                                    <select v-model="createForm.package_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'package_id') ? 'border-rose-300/60' : ''">
                                        <option disabled value="">Select a package</option>
                                        <option v-for="entry in packages" :key="entry.id" :value="String(entry.id)">{{ packageLabel(entry) }}</option>
                                    </select>
                                    <p v-if="firstError(createValidationErrors, 'package_id')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(createValidationErrors, 'package_id') }}</p>
                                </div>

                                <div v-if="selectedPackageHourlyPrices.length" class="sm:col-span-2">
                                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Package Timing And Price</label>
                                    <select v-model="createForm.package_hourly_price_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50">
                                        <option v-for="option in selectedPackageHourlyPrices" :key="option.id" :value="String(option.id)">
                                            {{ Number(option.hours).toFixed(2) }} hrs - ${{ option.price }}
                                        </option>
                                    </select>
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Discount</label>
                                    <select v-model="createForm.discount_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50">
                                        <option value="">No discount</option>
                                        <option v-for="discount in availableDiscountOptions" :key="discount.id" :value="String(discount.id)">
                                            {{ discountLabel(discount) }} - {{ discountValueLabel(discount) }}
                                        </option>
                                    </select>
                                    <p class="mt-2 text-xs text-stone-400">
                                        Active discounts appear here when they apply to the selected package or chosen equipment.
                                    </p>
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Event Date</label>
                                    <input v-model="createForm.event_date" type="date" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'event_date') ? 'border-rose-300/60' : ''" @click="openDatePicker" @keydown.prevent>
                                    <p v-if="firstError(createValidationErrors, 'event_date')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(createValidationErrors, 'event_date') }}</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Start Hour</label>
                                    <input v-model="createForm.start_time" type="time" step="1800" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'start_time') ? 'border-rose-300/60' : ''">
                                    <p v-if="firstError(createValidationErrors, 'start_time')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(createValidationErrors, 'start_time') }}</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Hour Duration</label>
                                    <input v-model="createForm.total_hours" :readonly="selectedPackageHourlyPrices.length > 0" type="number" min="0.5" step="0.5" class="w-full rounded-xl border border-white/10 px-3 py-2.5 text-sm text-white outline-none transition" :class="[selectedPackageHourlyPrices.length > 0 ? 'bg-slate-900/60' : 'bg-slate-950/70 focus:border-rose-300/50', firstError(createValidationErrors, 'total_hours') ? 'border-rose-300/60' : '']">
                                    <p v-if="firstError(createValidationErrors, 'total_hours')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(createValidationErrors, 'total_hours') }}</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">End Hour</label>
                                    <input :value="createForm.end_time" readonly type="text" class="w-full rounded-xl border border-white/10 bg-slate-900/60 px-3 py-2.5 text-sm text-white outline-none" :class="firstError(createValidationErrors, 'end_time') ? 'border-rose-300/60' : ''">
                                    <p v-if="firstError(createValidationErrors, 'end_time')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(createValidationErrors, 'end_time') }}</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Location</label>
                                    <input v-model="createForm.event_location" data-google-address-input="true" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'event_location') ? 'border-rose-300/60' : ''">
                                    <p v-if="firstError(createValidationErrors, 'event_location')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(createValidationErrors, 'event_location') }}</p>
                                </div>
                                <div v-if="!isEntryBooking">
                                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Event Type</label>
                                    <select v-model="createForm.event_type" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50">
                                        <option v-for="eventType in data.eventTypes" :key="eventType" :value="eventType">{{ eventType }}</option>
                                    </select>
                                </div>
                                <div :class="isEntryBooking ? 'sm:col-span-2' : ''">
                                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Notes</label>
                                    <textarea v-model="createForm.notes" rows="3" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50" />
                                </div>
                            </div>
                        </div>

                        <div class="order-1 space-y-4">
                            <div class="rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">{{ entryHeading }}</p>
                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <div class="sm:col-span-2">
                                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">{{ isEntryBooking ? 'Name' : 'Full Name' }}</label>
                                        <input v-if="isEntryBooking" v-model="createForm.entry_name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'entry_name') ? 'border-rose-300/60' : ''">
                                        <input v-else v-model="createForm.customer_name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'customer_name') ? 'border-rose-300/60' : ''">
                                        <p v-if="firstError(createValidationErrors, isEntryBooking ? 'entry_name' : 'customer_name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(createValidationErrors, isEntryBooking ? 'entry_name' : 'customer_name') }}</p>
                                    </div>
                                    <div v-if="isEntryBooking" class="sm:col-span-2">
                                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Description</label>
                                        <textarea v-model="createForm.entry_description" rows="3" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50" />
                                    </div>
                                    <template v-if="!isEntryBooking">
                                        <div>
                                            <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Email</label>
                                            <input v-model="createForm.customer_email" type="email" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'customer_email') ? 'border-rose-300/60' : ''">
                                            <p v-if="firstError(createValidationErrors, 'customer_email')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(createValidationErrors, 'customer_email') }}</p>
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Phone</label>
                                            <input v-model="createForm.customer_phone" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'customer_phone') ? 'border-rose-300/60' : ''">
                                            <p v-if="firstError(createValidationErrors, 'customer_phone')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(createValidationErrors, 'customer_phone') }}</p>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div v-if="isEntryBooking" class="rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Invoice Contact</p>
                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <div class="sm:col-span-2">
                                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Invoice Contact Name</label>
                                        <input v-model="createForm.customer_name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'customer_name') ? 'border-rose-300/60' : ''">
                                        <p v-if="firstError(createValidationErrors, 'customer_name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(createValidationErrors, 'customer_name') }}</p>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Invoice Email</label>
                                        <input v-model="createForm.customer_email" type="email" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'customer_email') ? 'border-rose-300/60' : ''">
                                        <p v-if="firstError(createValidationErrors, 'customer_email')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(createValidationErrors, 'customer_email') }}</p>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Invoice Phone</label>
                                        <input v-model="createForm.customer_phone" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'customer_phone') ? 'border-rose-300/60' : ''">
                                        <p v-if="firstError(createValidationErrors, 'customer_phone')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(createValidationErrors, 'customer_phone') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Equipment</p>
                                <span class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] text-stone-300">{{ createForm.equipment_ids?.length ?? 0 }}</span>
                            </div>
                            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                <button
                                    v-for="item in equipmentOptions"
                                    :key="item.id"
                                    type="button"
                                    class="rounded-xl border px-3 py-3 text-left transition"
                                    :class="createForm.equipment_ids?.includes(item.id) ? 'border-cyan-300/40 bg-cyan-300/10 text-white' : 'border-white/10 bg-slate-950/50 text-stone-300 hover:bg-white/5'"
                                    @click="toggleMultiSelect('equipment_ids', item.id)"
                                >
                                    <p class="text-sm font-semibold">{{ item.name }}</p>
                                    <p class="mt-1 text-xs text-stone-400">{{ item.category || 'Equipment' }}</p>
                                    <p class="mt-2 text-xs font-medium text-cyan-100">${{ item.daily_rate }}</p>
                                </button>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-white/10 bg-slate-950/40 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Add-Ons</p>
                                <span class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] text-stone-300">{{ createForm.add_on_ids?.length ?? 0 }}</span>
                            </div>
                            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                <button
                                    v-for="item in addOnOptions"
                                    :key="item.id"
                                    type="button"
                                    class="rounded-xl border px-3 py-3 text-left transition"
                                    :class="createForm.add_on_ids?.includes(item.id) ? 'border-rose-300/40 bg-rose-300/10 text-white' : 'border-white/10 bg-slate-950/50 text-stone-300 hover:bg-white/5'"
                                    @click="toggleMultiSelect('add_on_ids', item.id)"
                                >
                                    <p class="text-sm font-semibold">{{ item.name }}</p>
                                    <p class="mt-1 text-xs text-stone-400">{{ addOnSummary(item) || 'Add-On' }}</p>
                                    <p class="mt-2 text-xs font-medium text-rose-100">${{ item.price }}</p>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="sticky bottom-0 z-20 -mx-5 -mb-5 flex items-center justify-between gap-3 border-t border-white/10 bg-[#132035] px-5 py-4 shadow-[0_-10px_24px_-18px_rgba(0,0,0,0.7)]">
                        <p v-if="!packages.length" class="text-sm text-amber-200">Add and activate a package first so admins can create bookings.</p>
                        <span v-else class="text-sm text-stone-400">Stall and Sponsored entries can be invoiced after save from the booking detail screen.</span>
                        <div class="flex items-center gap-3">
                            <button type="button" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/5" @click="closeCreateModal">Cancel</button>
                            <button type="submit" class="rounded-xl bg-rose-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-rose-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving || !packages.length">
                                {{ saving ? 'Saving...' : 'Create booking' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </transition>

    <transition name="modal">
        <div v-if="showDetailModal && selectedBooking" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm" @click.self="closeDetailModal">
            <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-2xl border border-white/10 bg-[#132035] shadow-2xl shadow-black/30">
                <div class="flex items-center justify-between border-b border-white/10 px-5 py-4">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.3em] text-rose-200">Booking Details</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <h3 class="text-lg font-semibold">{{ selectedBooking.display_name || selectedBooking.customer_name }}</h3>
                            <span class="rounded-full border border-cyan-300/20 bg-cyan-300/10 px-2 py-0.5 text-[10px] uppercase tracking-[0.2em] text-cyan-100">
                                {{ selectedBooking.booking_kind_label }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="rounded-full px-3 py-1 text-xs font-medium" :class="selectedBooking.status === 'confirmed' ? 'bg-emerald-400/15 text-emerald-200' : selectedBooking.status === 'pending' ? 'bg-amber-300/15 text-amber-200' : selectedBooking.status === 'completed' ? 'bg-cyan-300/15 text-cyan-200' : 'bg-rose-400/15 text-rose-200'">
                            {{ statusLabel(selectedBooking.status) }}
                        </span>
                        <button type="button" class="rounded-lg border border-white/10 px-3 py-2 text-sm text-stone-300 transition hover:bg-white/5" @click="closeDetailModal">Close</button>
                    </div>
                </div>
                <div class="p-5">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="overflow-hidden rounded-xl border border-white/10 bg-slate-950/50 sm:col-span-2">
                            <div class="flex flex-col gap-4 p-3 sm:flex-row sm:items-start">
                                <div v-if="selectedBooking.package?.photo_url" class="h-24 w-full shrink-0 overflow-hidden rounded-xl border border-white/10 bg-slate-900/70 sm:w-28">
                                    <img :src="selectedBooking.package.photo_url" :alt="selectedBooking.package.name" class="h-full w-full object-cover">
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Selected Package</p>
                                    <p class="mt-2 text-base font-semibold">{{ selectedBooking.package?.name ?? selectedBooking.package_name ?? 'No package selected' }}</p>
                                    <p v-if="selectedBooking.package?.price ?? selectedBooking.package_price" class="mt-1 text-xs text-stone-400">${{ selectedBooking.package?.price ?? selectedBooking.package_price }}</p>
                                    <p class="mt-3 text-sm leading-6 text-stone-300">{{ selectedBooking.package?.description || 'No package description provided.' }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Quote Number</p>
                            <p class="mt-2 text-base font-semibold text-white">{{ selectedBooking.quote_number || 'Not assigned' }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Event Date</p>
                            <p class="mt-2 text-base font-semibold">{{ selectedBooking.event_date_label }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3 sm:col-span-2">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">{{ selectedBooking.booking_kind === 'customer' ? 'Customer Name' : 'Entry Name' }}</p>
                            <p class="mt-2 text-base font-semibold text-white">{{ selectedBooking.display_name || selectedBooking.customer_name }}</p>
                            <p v-if="selectedBooking.entry_description" class="mt-3 text-sm leading-6 text-stone-300">{{ selectedBooking.entry_description }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Email</p>
                            <p class="mt-2 text-sm font-medium text-stone-200">{{ selectedBooking.customer_email }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Phone</p>
                            <p class="mt-2 text-sm font-medium text-stone-200">{{ selectedBooking.customer_phone }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Event Type</p>
                            <p class="mt-2 text-sm font-medium text-stone-200">{{ selectedBooking.event_type_label || 'Not set' }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Travel Fee</p>
                            <p class="mt-2 text-sm font-medium text-stone-200">${{ selectedBooking.travel_fee }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Discount</p>
                            <p class="mt-2 text-sm font-medium text-stone-200">
                                {{ selectedBooking.discount ? `${selectedBooking.discount.code} - ${selectedBooking.discount.name}` : 'No discount selected' }}
                            </p>
                            <p class="mt-1 text-xs text-emerald-200">-${{ selectedBooking.discount_amount }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-3 sm:col-span-2">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Booking Hours</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-lg border border-white/10 bg-slate-900/60 p-3">
                                    <p class="text-[11px] uppercase tracking-[0.25em] text-stone-500">Start Hour</p>
                                    <p class="mt-2 text-sm font-medium text-stone-200">{{ selectedBooking.start_time_label || 'Not set' }}</p>
                                </div>
                                <div class="rounded-lg border border-white/10 bg-slate-900/60 p-3">
                                    <p class="text-[11px] uppercase tracking-[0.25em] text-stone-500">End Hour</p>
                                    <p class="mt-2 text-sm font-medium text-stone-200">{{ selectedBooking.end_time_label || 'Not set' }}</p>
                                </div>
                                <div class="rounded-lg border border-white/10 bg-slate-900/60 p-3">
                                    <p class="text-[11px] uppercase tracking-[0.25em] text-stone-500">Duration</p>
                                    <p class="mt-2 text-sm font-medium text-stone-200">{{ selectedBooking.total_hours }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-white/10 bg-slate-950/50 p-3">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Event Location</p>
                        <p class="mt-3 text-sm leading-6 text-stone-300">{{ selectedBooking.event_location }}</p>
                    </div>

                    <div class="mt-4 rounded-xl border border-white/10 bg-slate-950/50 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Selected Equipment</p>
                            <span class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] text-stone-300">{{ selectedBooking.equipment?.length ?? 0 }}</span>
                        </div>
                        <div v-if="selectedBooking.equipment?.length" class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <article v-for="item in selectedBooking.equipment" :key="item.id" class="overflow-hidden rounded-xl border border-white/10 bg-slate-900/70">
                                <img v-if="item.photo_url" :src="item.photo_url" :alt="item.name" class="h-20 w-full object-cover">
                                <div class="p-3">
                                    <p class="text-[11px] uppercase tracking-[0.25em] text-stone-500">{{ equipmentSummary(item) || 'Equipment' }}</p>
                                    <p class="mt-2 text-sm font-semibold text-white">{{ item.name }}</p>
                                    <p class="mt-1 text-xs text-stone-400">${{ item.price }}</p>
                                </div>
                            </article>
                        </div>
                        <p v-else class="mt-3 text-sm leading-6 text-stone-400">No equipment was selected for this booking.</p>
                    </div>

                    <div class="mt-4 rounded-xl border border-white/10 bg-slate-950/50 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Selected Add-Ons</p>
                            <span class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] text-stone-300">{{ selectedBooking.addons?.length ?? 0 }}</span>
                        </div>
                        <div v-if="selectedBooking.addons?.length" class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <article v-for="addOn in selectedBooking.addons" :key="addOn.id" class="overflow-hidden rounded-xl border border-white/10 bg-slate-900/70">
                                <img v-if="addOn.photo_url" :src="addOn.photo_url" :alt="addOn.name" class="h-20 w-full object-cover">
                                <div class="p-3">
                                    <p class="text-[11px] uppercase tracking-[0.25em] text-stone-500">{{ addOnSummary(addOn) || 'Add-On' }}</p>
                                    <p class="mt-2 text-sm font-semibold text-white">{{ addOn.name }}</p>
                                    <p class="mt-1 text-xs text-stone-400">${{ addOn.price }}</p>
                                    <p class="mt-3 text-sm leading-6 text-stone-300">{{ addOn.description || 'No add-on description provided.' }}</p>
                                </div>
                            </article>
                        </div>
                        <p v-else class="mt-3 text-sm leading-6 text-stone-400">No add-ons were selected for this booking.</p>
                    </div>

                    <div class="mt-4 rounded-xl border border-white/10 bg-slate-950/50 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Invoice</p>
                                <p class="mt-2 text-base font-semibold text-white">Booking Total: ${{ selectedBooking.booking_total }}</p>
                                <p class="mt-1 text-xs text-emerald-200">Discount Applied: -${{ selectedBooking.discount_amount }}</p>
                            </div>
                            <a
                                v-if="selectedBooking.invoice?.public_url"
                                :href="selectedBooking.invoice.public_url"
                                target="_blank"
                                rel="noreferrer"
                                class="rounded-xl border border-cyan-300/30 px-3 py-2 text-sm font-medium text-cyan-100 transition hover:border-cyan-200/60 hover:bg-cyan-300/10"
                            >
                                Open customer invoice
                            </a>
                        </div>

                        <div v-if="selectedBooking.invoice" class="mt-4 space-y-3">
                            <div class="flex justify-end">
                                <button type="button" class="rounded-xl bg-cyan-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving" @click="sendInvoice">
                                    {{ saving ? 'Sending...' : 'Send invoice email' }}
                                </button>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div class="rounded-xl border border-white/10 bg-slate-900/70 p-3">
                                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Invoice</p>
                                    <p class="mt-2 text-sm font-semibold text-white">{{ selectedBooking.invoice.invoice_number }}</p>
                                    <p class="mt-1 text-xs text-stone-400">{{ invoiceStatusLabel(selectedBooking.invoice.status) }}</p>
                                </div>
                                <div class="rounded-xl border border-white/10 bg-slate-900/70 p-3">
                                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Paid</p>
                                    <p class="mt-2 text-sm font-semibold text-emerald-200">${{ selectedBooking.invoice.amount_paid }}</p>
                                </div>
                                <div class="rounded-xl border border-white/10 bg-slate-900/70 p-3">
                                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Balance Due</p>
                                    <p class="mt-2 text-sm font-semibold text-amber-200">${{ selectedBooking.invoice.balance_due }}</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <article v-for="installment in selectedBooking.invoice.installments" :key="installment.id" class="rounded-xl border border-white/10 bg-slate-900/70 p-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-white">{{ installment.label }}</p>
                                            <p class="mt-1 text-xs text-stone-400">Due {{ installment.due_date_label }}</p>
                                            <p v-if="installment.paid_at_label" class="mt-1 text-xs text-emerald-200">Paid {{ installment.paid_at_label }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-semibold text-white">${{ installment.amount }}</p>
                                            <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium" :class="installment.status === 'paid' ? 'bg-emerald-400/15 text-emerald-200' : 'bg-amber-300/15 text-amber-200'">
                                                {{ invoiceStatusLabel(installment.status) }}
                                            </span>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        </div>

                        <form v-else class="mt-4 space-y-4" novalidate @submit.prevent="createInvoice">
                            <div class="grid gap-4 sm:grid-cols-4">
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Installments</label>
                                    <input v-model="invoiceForm.installment_count" type="number" min="1" max="12" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(invoiceValidationErrors, 'installment_count') ? 'border-rose-300/60' : ''">
                                    <p v-if="firstError(invoiceValidationErrors, 'installment_count')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(invoiceValidationErrors, 'installment_count') }}</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Deposit %</label>
                                    <input v-model="invoiceForm.deposit_percentage" type="number" min="0" max="100" step="0.01" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(invoiceValidationErrors, 'deposit_percentage') ? 'border-rose-300/60' : ''">
                                    <p v-if="firstError(invoiceValidationErrors, 'deposit_percentage')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(invoiceValidationErrors, 'deposit_percentage') }}</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">First Due Date</label>
                                    <input v-model="invoiceForm.first_due_date" type="date" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(invoiceValidationErrors, 'first_due_date') ? 'border-rose-300/60' : ''" @click="openDatePicker" @keydown.prevent>
                                    <p v-if="firstError(invoiceValidationErrors, 'first_due_date')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(invoiceValidationErrors, 'first_due_date') }}</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Interval Days</label>
                                    <input v-model="invoiceForm.interval_days" type="number" min="1" max="90" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(invoiceValidationErrors, 'interval_days') ? 'border-rose-300/60' : ''">
                                    <p v-if="firstError(invoiceValidationErrors, 'interval_days')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(invoiceValidationErrors, 'interval_days') }}</p>
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="rounded-xl bg-cyan-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                                    {{ saving ? 'Creating...' : 'Create installment invoice' }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <form class="mt-5 space-y-4" novalidate @submit.prevent="updateBooking">
                        <div>
                            <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Status</label>
                            <select v-model="editForm.status" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(editValidationErrors, 'status') ? 'border-rose-300/60' : ''">
                                <option v-for="status in data.bookingStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                            </select>
                            <p v-if="firstError(editValidationErrors, 'status')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(editValidationErrors, 'status') }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Notes</label>
                            <textarea v-model="editForm.notes" rows="5" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50" />
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-rose-300/40 hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                                {{ saving ? 'Saving...' : 'Update booking' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </transition>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: all 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
    transform: scale(0.98);
}
</style>
