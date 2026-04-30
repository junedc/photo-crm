<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';
import { autoAttachGoogleAddressInputs } from '../../googleAddressAutocomplete';
import { firstError, hasFieldErrors, isBlank, mergeFieldErrors, requiredMessage } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const emptyBookingRecord = () => ({
    id: null,
    display_name: '',
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    booking_kind: 'customer',
    booking_kind_label: 'Customer Booking',
    quote_number: '',
    booking_no: '',
    event_name: '',
    event_type: '',
    event_type_label: '',
    event_date: '',
    event_date_label: 'Not set',
    start_time: '',
    start_time_label: 'Not set',
    end_time: '',
    end_time_label: 'Not set',
    total_hours: '0.00',
    event_location: '',
    notes: '',
    status: 'pending',
    status_label: 'Pending',
    customer_response_status: 'pending',
    customer_response_label: 'Pending',
    customer_responded_at_label: '',
    package: null,
    package_id: '',
    package_name: '',
    package_price: '0.00',
    discount: null,
    discount_id: '',
    discount_amount: '0.00',
    booking_discount_source: 'none',
    booking_discount_type: 'amount',
    booking_discount_value: '',
    booking_total: '0.00',
    travel_fee: '0.00',
    travel_distance_km: '',
    entry_name: '',
    entry_description: '',
    client_portal_access_granted: false,
    client_portal_access_granted_at_label: '',
    client_portal_access_url: null,
    grant_client_access_url: null,
    update_url: null,
    invoice_create_url: null,
    show_url: null,
    invoice: null,
    equipment: [],
    addons: [],
    tasks: [],
    contacts: [],
    expenses: [],
    documents: [],
    task_assignees: [],
    equipment_ids: [],
    add_on_ids: [],
    equipment_discount_types: {},
    equipment_discount_values: {},
    add_on_discount_types: {},
    add_on_discount_values: {},
});

const normalizeBookingRecord = (record) => {
    const base = emptyBookingRecord();
    const nextRecord = record && typeof record === 'object' ? record : {};

    return {
        ...base,
        ...nextRecord,
        equipment: Array.isArray(nextRecord.equipment) ? nextRecord.equipment : [],
        addons: Array.isArray(nextRecord.addons) ? nextRecord.addons : [],
        tasks: Array.isArray(nextRecord.tasks) ? nextRecord.tasks : [],
        contacts: Array.isArray(nextRecord.contacts) ? nextRecord.contacts : [],
        expenses: Array.isArray(nextRecord.expenses) ? nextRecord.expenses : [],
        documents: Array.isArray(nextRecord.documents) ? nextRecord.documents : [],
        task_assignees: Array.isArray(nextRecord.task_assignees) ? nextRecord.task_assignees : [],
        equipment_ids: Array.isArray(nextRecord.equipment_ids) ? nextRecord.equipment_ids : [],
        add_on_ids: Array.isArray(nextRecord.add_on_ids) ? nextRecord.add_on_ids : [],
        equipment_discount_types: nextRecord.equipment_discount_types && typeof nextRecord.equipment_discount_types === 'object'
            ? nextRecord.equipment_discount_types
            : {},
        equipment_discount_values: nextRecord.equipment_discount_values && typeof nextRecord.equipment_discount_values === 'object'
            ? nextRecord.equipment_discount_values
            : {},
        add_on_discount_types: nextRecord.add_on_discount_types && typeof nextRecord.add_on_discount_types === 'object'
            ? nextRecord.add_on_discount_types
            : {},
        add_on_discount_values: nextRecord.add_on_discount_values && typeof nextRecord.add_on_discount_values === 'object'
            ? nextRecord.add_on_discount_values
            : {},
    };
};

const { saving, fieldErrors, submitForm, deleteRecord } = useWorkspaceCrud();
const bookingRecord = ref(normalizeBookingRecord(props.data.booking));
const activeTab = ref('overview');
const isEditing = ref(false);
const editErrors = ref({});
const invoiceErrors = ref({});
const taskErrors = ref({});
const expenseErrors = ref({});
const documentErrors = ref({});
const contactErrors = ref({});
const manualPaymentErrors = ref({});
const tasks = ref([...(bookingRecord.value.tasks ?? [])]);
const contacts = ref([...(bookingRecord.value.contacts ?? [])]);
const localTaskStatuses = ref([...(props.data.taskStatuses ?? [])]);
const bookingStatusOptions = computed(() => props.data.bookingStatusOptions ?? []);
const quoteResponseStatusOptions = computed(() => props.data.quoteResponseStatusOptions ?? []);
const isLightTheme = computed(() => document.body?.dataset.theme === 'light');
const bookingReady = computed(() => {
    const record = bookingRecord.value;

    return Boolean(record && (record.id || record.quote_number || record.customer_name || record.display_name));
});
const defaultTaskStatusId = computed(() => String(localTaskStatuses.value.find((status) => String(status.name ?? '').toLowerCase() === 'new')?.id ?? ''));
const resolvedDefaultTaskStatusId = computed(() => defaultTaskStatusId.value || String(localTaskStatuses.value[0]?.id ?? ''));
const resolveBookingStatusId = (record) => {
    if (record?.status_id) {
        return String(record.status_id);
    }

    const matchedStatus = bookingStatusOptions.value.find((status) => String(status.name ?? '').toLowerCase() === String(record?.status ?? '').toLowerCase());

    if (matchedStatus?.id) {
        return String(matchedStatus.id);
    }

    return String(props.data.bookingStatusOptions?.[0]?.id ?? '');
};
const resolveQuoteResponseStatusId = (record) => {
    if (record?.customer_response_status_id) {
        return String(record.customer_response_status_id);
    }

    const matchedStatus = quoteResponseStatusOptions.value.find((status) => String(status.name ?? '').toLowerCase() === String(record?.customer_response_status ?? 'pending').toLowerCase());

    if (matchedStatus?.id) {
        return String(matchedStatus.id);
    }

    return String(props.data.quoteResponseStatusOptions?.[0]?.id ?? '');
};

const buildEditForm = (record) => ({
    booking_status_id: resolveBookingStatusId(record),
    quote_response_status_id: resolveQuoteResponseStatusId(record),
    booking_kind: record?.booking_kind ?? (props.data.bookingKinds?.[0] ?? 'customer'),
    entry_name: record?.entry_name ?? '',
    entry_description: record?.entry_description ?? '',
    package_id: record?.package_id ? String(record.package_id) : '',
    package_hourly_price_id: record?.package_hourly_price_id ? String(record.package_hourly_price_id) : '',
    customer_name: record?.customer_name ?? '',
    customer_email: record?.customer_email ?? '',
    customer_phone: record?.customer_phone ?? '',
    event_name: record?.event_name ?? '',
    booking_no: record?.booking_no ?? '',
    event_type: record?.event_type ?? (props.data.eventTypes?.[0] ?? 'Wedding'),
    event_date: record?.event_date ?? '',
    start_time: record?.start_time ?? '',
    end_time: record?.end_time ?? '',
    total_hours: record?.total_hours ?? '0.00',
    event_location: record?.event_location ?? '',
    notes: record?.notes ?? '',
    discount_id: record?.discount_id ? String(record.discount_id) : '',
    booking_discount_source: record?.booking_discount_source ?? (record?.discount_id ? 'package' : 'none'),
    booking_discount_type: record?.booking_discount_type ?? 'amount',
    booking_discount_value: record?.booking_discount_value ?? '',
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
    task_status_id: task?.task_status_id ? String(task.task_status_id) : resolvedDefaultTaskStatusId.value,
    due_date: task?.due_date ?? '',
    date_started: task?.date_started ?? '',
    date_completed: task?.date_completed ?? '',
    remarks: task?.remarks ?? '',
    attachments: [],
});

const editForm = ref(buildEditForm(props.data.booking));
const editingTask = ref(null);
const showTaskEditor = ref(false);
const taskForm = ref(buildTaskForm());
const taskAttachmentInput = ref(null);
const showExpenseEditor = ref(false);
const showExpenseDetails = ref(false);
const selectedExpense = ref(null);
const expenseReceiptInput = ref(null);
const showDocumentEditor = ref(false);
const documentFileInput = ref(null);
const showManualPaymentEditor = ref(false);
const selectedPaymentInstallment = ref(null);
const buildExpenseForm = () => ({
    expense_name: '',
    expense_date: new Date().toISOString().slice(0, 10),
    amount: '',
    vendor_id: '',
    user_id: '',
    expense_category_id: '',
    notes: '',
    receipt: null,
});
const expenseForm = ref(buildExpenseForm());
const buildDocumentForm = () => ({
    document_type: 'user_file',
    title: '',
    notes: '',
    file: null,
});
const documentForm = ref(buildDocumentForm());
const buildContactForm = () => ({
    source_type: 'manual',
    source_id: '',
    name: '',
    company_name: '',
    role: '',
    email: '',
    phone: '',
    notes: '',
});
const contactForm = ref(buildContactForm());
const showContactEditor = ref(false);
const todayInput = () => new Date().toISOString().slice(0, 10);
const buildManualPaymentForm = () => ({
    payment_method: 'bank_transfer',
    paid_at: todayInput(),
    payment_reference: '',
    payment_notes: '',
});
const manualPaymentForm = ref(buildManualPaymentForm());
const pendingDelete = ref(null);
const showDeleteConfirm = ref(false);
function formatHours(value) {
    const numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
        return '0';
    }

    return numericValue % 1 === 0 ? String(numericValue) : numericValue.toFixed(2).replace(/\.?0+$/, '');
}

const defaultInvoiceDescription = (record) => {
    const packageName = record?.package?.name || record?.package_name || 'Booking package';
    const hoursLabel = record?.total_hours ? ` - ${formatHours(record.total_hours)} hrs` : '';
    const packageHeading = `${packageName}${hoursLabel}`;
    const equipmentNames = record?.package?.equipment_names ?? [];
    const addOnNames = record?.package?.add_on_names ?? [];
    const inclusions = [...equipmentNames, ...addOnNames]
        .map((item) => String(item || '').trim())
        .filter(Boolean)
        .filter((item, index, array) => array.indexOf(item) === index);

    if (!inclusions.length) {
        return packageHeading;
    }

    return `${packageHeading} inclusions:\n${inclusions.map((item) => `- ${item}`).join('\n')}`;
};
const addDaysInput = (date, days) => {
    const next = new Date(`${date}T00:00:00`);
    next.setDate(next.getDate() + days);

    return next.toISOString().slice(0, 10);
};
const buildInvoiceForm = (invoice = null) => ({
    invoice_number: invoice?.invoice_number ?? '',
    issue_date: invoice?.issued_at ?? todayInput(),
    amounts_are: invoice?.amounts_are ?? 'tax_exclusive',
    line_description: invoice?.line_description ?? defaultInvoiceDescription(bookingRecord.value ?? props.data.booking),
    tax_rate: invoice?.tax_rate ?? 'gst_free_income',
    installment_count: String(invoice?.installment_count ?? 3),
    deposit_type: 'percentage',
    deposit_percentage: String(invoice?.deposit_percentage ?? props.data.defaultDepositPercentage ?? 30),
    deposit_amount: String(invoice?.deposit_amount ?? ''),
    first_due_date: invoice?.first_due_date ?? addDaysInput(todayInput(), 7),
    interval_days: String(invoice?.interval_days ?? 30),
});
const invoiceForm = ref(buildInvoiceForm(props.data.booking?.invoice));
const amountsAreOptions = [
    { value: 'tax_exclusive', label: 'Tax exclusive' },
    { value: 'tax_inclusive', label: 'Tax inclusive' },
    { value: 'no_tax', label: 'No Tax' },
];
const taxRateOptions = [
    { value: 'bas_excluded', label: 'BAS Excluded' },
    { value: 'gst_free_income', label: 'GST Free Income' },
    { value: 'gst_on_income', label: 'GST on Income' },
];

const editValidationErrors = computed(() => mergeFieldErrors(editErrors.value, fieldErrors.value));
const invoiceValidationErrors = computed(() => mergeFieldErrors(invoiceErrors.value, fieldErrors.value));
const taskValidationErrors = computed(() => mergeFieldErrors(taskErrors.value, fieldErrors.value));
const expenseValidationErrors = computed(() => mergeFieldErrors(expenseErrors.value, fieldErrors.value));
const documentValidationErrors = computed(() => mergeFieldErrors(documentErrors.value, fieldErrors.value));
const contactValidationErrors = computed(() => mergeFieldErrors(contactErrors.value, fieldErrors.value));
const manualPaymentValidationErrors = computed(() => mergeFieldErrors(manualPaymentErrors.value, fieldErrors.value));
const packages = computed(() => props.data.packages ?? []);
const equipmentOptions = computed(() => props.data.equipmentOptions ?? []);
const addOnOptions = computed(() => props.data.addOnOptions ?? []);
const discountOptions = computed(() => props.data.discountOptions ?? []);
const vendorOptions = computed(() => props.data.vendorOptions ?? []);
const customerOptions = computed(() => props.data.customerOptions ?? []);
const userOptions = computed(() => props.data.userOptions ?? []);
const expenseCategoryOptions = computed(() => props.data.expenseCategoryOptions ?? []);
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
const packageDiscountOptions = computed(() => {
    const packageId = Number(editForm.value.package_id ?? 0);
    const equipmentIds = new Set((editForm.value.equipment_ids ?? []).map((id) => Number(id)));

    return discountOptions.value.filter((discount) => {
        const packageMatch = discount.package_ids?.includes(packageId);
        const equipmentMatch = (discount.equipment_ids ?? []).some((id) => equipmentIds.has(Number(id)));

        return packageMatch || equipmentMatch;
    });
});
const availableDiscountOptions = computed(() => {
    if (editForm.value.booking_discount_source === 'global') {
        return discountOptions.value;
    }

    if (editForm.value.booking_discount_source === 'package') {
        return packageDiscountOptions.value;
    }

    return [];
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
const formatIntegerQuantity = (value = 1) => String(Math.max(0, Math.round(Number(value || 0))));
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
const selectedExpenseReceiptName = computed(() => expenseForm.value.receipt?.name ?? '');
const selectedDocumentFileName = computed(() => documentForm.value.file?.name ?? '');
const documentTypeOptions = [
    { value: 'quote', label: 'Quote' },
    { value: 'invoice', label: 'Invoice' },
    { value: 'receipt', label: 'Receipt' },
    { value: 'client_file', label: 'Client File' },
    { value: 'user_file', label: 'User File' },
    { value: 'other', label: 'Other' },
];
const contactSourceOptions = computed(() => {
    if (contactForm.value.source_type === 'vendor') {
        return vendorOptions.value;
    }

    if (contactForm.value.source_type === 'customer') {
        return customerOptions.value;
    }

    return [];
});
const taskAssigneeGroups = computed(() => {
    return taskAssignees.value.reduce((groups, option) => {
        const group = option.group ?? 'Other';
        groups[group] = [...(groups[group] ?? []), option];
        return groups;
    }, {});
});
const overviewInitialTotal = computed(() => (
    (Number(bookingRecord.value.booking_total || 0) + Number(bookingRecord.value.discount_amount || 0)).toFixed(2)
));
const invoiceLineAmount = computed(() => Number(bookingRecord.value.invoice?.total_amount ?? bookingRecord.value.booking_total ?? 0));
const invoiceItemsPreview = computed(() => {
    const rows = [];

    if (bookingRecord.value.package_id || bookingRecord.value.package_price) {
        rows.push({
            id: `package-${bookingRecord.value.package_id ?? 'selected'}`,
            type: 'package',
            item: bookingRecord.value.package?.name || bookingRecord.value.package_name || 'Booking package',
            description: invoiceForm.value.line_description,
            quantity: formatIntegerQuantity(),
            price: bookingRecord.value.package_price || '0.00',
            discount: '0.00',
            taxAmount: '0.00',
            amount: bookingRecord.value.package_price || '0.00',
        });
    }

    (bookingRecord.value.equipment ?? []).forEach((item) => {
        rows.push({
            id: `equipment-${item.id}`,
            type: 'equipment',
            item: item.name || 'Equipment',
            description: item.description || item.category || 'Selected equipment',
            quantity: formatIntegerQuantity(),
            price: item.original_price || item.price || '0.00',
            discount: itemDiscountLabel('equipment_ids', item.id),
            taxAmount: '0.00',
            amount: item.price || '0.00',
        });
    });

    (bookingRecord.value.addons ?? []).forEach((item) => {
        rows.push({
            id: `addon-${item.id}`,
            type: 'add_on',
            item: item.name || 'Add-On',
            description: item.description || item.duration || 'Selected add-on',
            quantity: formatIntegerQuantity(),
            price: item.original_price || item.price || '0.00',
            discount: itemDiscountLabel('add_on_ids', item.id),
            taxAmount: '0.00',
            amount: item.price || '0.00',
        });
    });

    if (Number(bookingRecord.value.travel_fee || 0) > 0) {
        rows.push({
            id: 'travel-fee',
            type: 'travel_fee',
            item: 'Travel Fee',
            description: bookingRecord.value.travel_distance_km
                ? `${bookingRecord.value.travel_distance_km} km`
                : 'Travel charge',
            quantity: formatIntegerQuantity(),
            price: bookingRecord.value.travel_fee,
            discount: '0.00',
            taxAmount: '0.00',
            amount: bookingRecord.value.travel_fee,
        });
    }

    if (Number(bookingRecord.value.discount_amount || 0) > 0) {
        rows.push({
            id: 'booking-discount',
            type: 'booking_discount',
            item: 'Booking Discount',
            description: bookingRecord.value.discount
                ? `${bookingRecord.value.discount.code} - ${bookingRecord.value.discount.name}`
                : 'Applied booking discount',
            quantity: formatIntegerQuantity(),
            price: bookingRecord.value.discount_amount,
            discount: 'Included',
            taxAmount: '0.00',
            amount: `-${bookingRecord.value.discount_amount}`,
        });
    }

    return rows;
});
const invoiceSubtotal = computed(() => invoiceItemsPreview.value
    .filter((item) => item.type !== 'booking_discount')
    .reduce((total, item) => total + Number(item.amount || 0), 0)
    .toFixed(2));
const invoiceTaxRateDisabled = computed(() => invoiceForm.value.amounts_are === 'no_tax');
const invoiceGstAmount = computed(() => {
    if (invoiceTaxRateDisabled.value || invoiceForm.value.tax_rate !== 'gst_on_income') {
        return '0.00';
    }

    if (invoiceForm.value.amounts_are === 'tax_inclusive') {
        return (invoiceLineAmount.value / 11).toFixed(2);
    }

    return (invoiceLineAmount.value * 0.1).toFixed(2);
});
const invoiceTotalAmount = computed(() => invoiceLineAmount.value.toFixed(2));
const invoiceCanEdit = computed(() => {
    const invoice = bookingRecord.value.invoice;

    if (!invoice) {
        return true;
    }

    return Number(invoice.amount_paid ?? 0) <= 0 && !(invoice.installments ?? []).some((installment) => installment.status === 'paid');
});
const invoiceDepositAmountPreview = computed(() => {
    const total = Number(invoiceTotalAmount.value || 0);

    if (invoiceForm.value.deposit_type === 'amount') {
        return Math.min(Math.max(Number(invoiceForm.value.deposit_amount || 0), 0), total).toFixed(2);
    }

    return (total * (Math.min(Math.max(Number(invoiceForm.value.deposit_percentage || 0), 0), 100) / 100)).toFixed(2);
});
const invoiceDepositPercentagePreview = computed(() => {
    const total = Number(invoiceTotalAmount.value || 0);

    if (total <= 0) {
        return '0.00';
    }

    if (invoiceForm.value.deposit_type === 'percentage') {
        return Math.min(Math.max(Number(invoiceForm.value.deposit_percentage || 0), 0), 100).toFixed(2);
    }

    return ((Number(invoiceDepositAmountPreview.value) / total) * 100).toFixed(2);
});
const invoiceRemainingBalancePreview = computed(() => (
    Math.max(Number(invoiceTotalAmount.value) - Number(invoiceDepositAmountPreview.value), 0).toFixed(2)
));
const invoicePreviewButtonClass = computed(() => (
    isLightTheme.value
        ? 'rounded border border-[#d8d0c4] bg-[#fffdf8] px-3 py-1.5 text-sm font-medium text-[#66615b] transition hover:border-[#c9c1b5] hover:bg-[#f7f1e8]'
        : 'rounded border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-blue-700 transition hover:bg-blue-50'
));
const invoiceSaveButtonClass = computed(() => (
    isLightTheme.value
        ? 'rounded border border-[#51cbce]/40 bg-white px-3 py-1.5 text-sm font-semibold text-[#23979a] transition hover:bg-[#eefbfb] disabled:cursor-not-allowed disabled:opacity-60'
        : 'rounded border border-blue-700 bg-white px-3 py-1.5 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-60'
));
const invoiceApproveButtonClass = computed(() => (
    isLightTheme.value
        ? 'rounded bg-cyan-300 px-3 py-1.5 text-sm font-semibold text-slate-950 transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60'
        : 'rounded bg-blue-700 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-60'
));

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
            details: bookingRecord.value.total_hours ? `${formatHours(bookingRecord.value.total_hours)} hrs` : 'Package',
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
    const normalizedRecord = normalizeBookingRecord(record);

    bookingRecord.value = normalizedRecord;
    editForm.value = buildEditForm(normalizedRecord);
    invoiceForm.value = buildInvoiceForm(normalizedRecord.invoice);
    tasks.value = [...(normalizedRecord.tasks ?? [])];
    contacts.value = [...(normalizedRecord.contacts ?? [])];

    if (normalizedRecord.show_url) {
        window.history.replaceState({}, '', normalizedRecord.show_url);
    }
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

const askDelete = ({ kind, label, onConfirm }) => {
    pendingDelete.value = { kind, label, onConfirm };
    showDeleteConfirm.value = true;
};

const cancelDelete = () => {
    pendingDelete.value = null;
    showDeleteConfirm.value = false;
};

const confirmDelete = async () => {
    if (!pendingDelete.value?.onConfirm) {
        return;
    }

    await pendingDelete.value.onConfirm();
    cancelDelete();
};

watch(() => editForm.value.equipment_ids, () => {
    if (!availableDiscountOptions.value.some((entry) => String(entry.id) === String(editForm.value.discount_id ?? ''))) {
        editForm.value.discount_id = '';
    }
}, { deep: true });

watch(() => editForm.value.booking_discount_source, (source) => {
    if (source === 'custom' || source === 'none') {
        editForm.value.discount_id = '';
    }

    if (source === 'package' || source === 'global' || source === 'none') {
        editForm.value.booking_discount_value = '';
    }

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

watch(() => invoiceForm.value.amounts_are, (value) => {
    if (value === 'no_tax') {
        invoiceForm.value.tax_rate = '';
        return;
    }

    if (!invoiceForm.value.tax_rate) {
        invoiceForm.value.tax_rate = 'gst_free_income';
    }
});

watch(() => contactForm.value.source_type, () => {
    contactForm.value = {
        ...buildContactForm(),
        source_type: contactForm.value.source_type,
    };
});

watch(() => contactForm.value.source_id, (value) => {
    const source = contactSourceOptions.value.find((option) => String(option.id) === String(value ?? ''));

    if (!source) {
        return;
    }

    contactForm.value.name = source.name ?? source.label ?? '';
    contactForm.value.company_name = source.company_name ?? '';
    contactForm.value.role = source.role ?? '';
    contactForm.value.email = source.email ?? '';
    contactForm.value.phone = source.phone ?? '';
});

watch(resolvedDefaultTaskStatusId, (value) => {
    if (!editingTask.value && isBlank(taskForm.value.task_status_id) && value) {
        taskForm.value.task_status_id = value;
    }
});

watch(isEditing, (value) => {
    if (value) {
        nextTick(() => autoAttachGoogleAddressInputs());
    }
});

onMounted(() => {
    syncPackageTimingDefaults();
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

    if (isBlank(editForm.value.quote_response_status_id)) {
        errors.quote_response_status_id = requiredMessage('Quote response');
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

    if (bookingRecord.value.invoice && isBlank(invoiceForm.value.invoice_number)) {
        errors.invoice_number = requiredMessage('Invoice number');
    }

    if (isBlank(invoiceForm.value.issue_date)) {
        errors.issue_date = requiredMessage('Issue date');
    }

    if (isBlank(invoiceForm.value.amounts_are)) {
        errors.amounts_are = requiredMessage('Amounts are');
    }

    if (!invoiceTaxRateDisabled.value && isBlank(invoiceForm.value.tax_rate)) {
        errors.tax_rate = requiredMessage('Tax rate');
    }

    if (isBlank(invoiceForm.value.line_description)) {
        errors.line_description = requiredMessage('Description');
    }

    if (isBlank(invoiceForm.value.installment_count)) {
        errors.installment_count = requiredMessage('Installments');
    }

    if (invoiceForm.value.deposit_type === 'percentage' && isBlank(invoiceForm.value.deposit_percentage)) {
        errors.deposit_percentage = requiredMessage('Deposit %');
    }

    if (invoiceForm.value.deposit_type === 'amount' && isBlank(invoiceForm.value.deposit_amount)) {
        errors.deposit_amount = requiredMessage('Deposit amount');
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
        const existingInvoice = bookingRecord.value.invoice;
        const invoice = await submitForm({
            url: existingInvoice?.update_url ?? bookingRecord.value.invoice_create_url,
            method: existingInvoice ? 'put' : 'post',
            data: { ...invoiceForm.value },
        });

        invoiceErrors.value = {};
        syncBooking({
            ...bookingRecord.value,
            invoice,
        });
        activeTab.value = 'invoice';
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

const startManualPayment = (installment) => {
    selectedPaymentInstallment.value = installment;
    manualPaymentErrors.value = {};
    manualPaymentForm.value = buildManualPaymentForm();
    showManualPaymentEditor.value = true;
};

const cancelManualPayment = () => {
    selectedPaymentInstallment.value = null;
    showManualPaymentEditor.value = false;
    manualPaymentErrors.value = {};
    manualPaymentForm.value = buildManualPaymentForm();
};

const saveManualPayment = async () => {
    const errors = {};

    if (isBlank(manualPaymentForm.value.payment_method)) {
        errors.payment_method = requiredMessage('Payment method');
    }

    if (isBlank(manualPaymentForm.value.paid_at)) {
        errors.paid_at = requiredMessage('Payment date');
    }

    manualPaymentErrors.value = errors;

    if (hasFieldErrors(errors) || !selectedPaymentInstallment.value?.record_payment_url) {
        return;
    }

    try {
        const invoice = await submitForm({
            url: selectedPaymentInstallment.value.record_payment_url,
            method: 'post',
            data: { ...manualPaymentForm.value },
        });

        syncBooking({
            ...bookingRecord.value,
            invoice,
        });
        cancelManualPayment();
    } catch {}
};

const startTaskCreate = () => {
    editingTask.value = null;
    showTaskEditor.value = true;
    taskErrors.value = {};
    taskForm.value = buildTaskForm();
};

const startTaskEdit = (task) => {
    editingTask.value = task;
    showTaskEditor.value = true;
    taskErrors.value = {};
    taskForm.value = buildTaskForm(task);
};

const cancelTaskEdit = () => {
    editingTask.value = null;
    showTaskEditor.value = false;
    taskErrors.value = {};
    taskForm.value = buildTaskForm();

    if (taskAttachmentInput.value) {
        taskAttachmentInput.value.value = '';
    }
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
        if (!editingTask.value && isBlank(taskForm.value.task_status_id)) {
            taskForm.value.task_status_id = resolvedDefaultTaskStatusId.value;
        }

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
        (taskForm.value.attachments ?? []).forEach((file) => {
            formData.append('attachments[]', file);
        });

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
        cancelTaskEdit();
    } catch {}
};

const removeTask = async (task) => {
    askDelete({
        kind: 'task',
        label: task.task_name || 'this task',
        onConfirm: async () => {
            await deleteRecord({ url: task.delete_url });
            tasks.value = tasks.value.filter((entry) => entry.id !== task.id);
            bookingRecord.value = {
                ...bookingRecord.value,
                tasks: [...tasks.value],
            };

            if (editingTask.value?.id === task.id) {
                cancelTaskEdit();
            }
        },
    });
};

const startExpenseCreate = () => {
    showExpenseEditor.value = true;
    expenseErrors.value = {};
    expenseForm.value = buildExpenseForm();
};

const cancelExpenseCreate = () => {
    showExpenseEditor.value = false;
    expenseErrors.value = {};
    expenseForm.value = buildExpenseForm();

    if (expenseReceiptInput.value) {
        expenseReceiptInput.value.value = '';
    }
};

const openExpenseDetails = (expense) => {
    selectedExpense.value = expense;
    showExpenseDetails.value = true;
};

const closeExpenseDetails = () => {
    selectedExpense.value = null;
    showExpenseDetails.value = false;
};

const triggerExpenseReceiptUpload = () => {
    expenseReceiptInput.value?.click();
};

const handleExpenseReceiptSelected = (event) => {
    const [file] = event.target?.files ?? [];
    expenseForm.value.receipt = file ?? null;
};

const saveExpense = async () => {
    const errors = {};

    if (isBlank(expenseForm.value.expense_name)) {
        errors.expense_name = requiredMessage('Expense name');
    }

    if (isBlank(expenseForm.value.expense_date)) {
        errors.expense_date = requiredMessage('Expense date');
    }

    if (isBlank(expenseForm.value.amount)) {
        errors.amount = requiredMessage('Amount');
    }

    expenseErrors.value = errors;

    if (hasFieldErrors(errors)) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('expense_name', expenseForm.value.expense_name ?? '');
        formData.append('expense_date', expenseForm.value.expense_date ?? '');
        formData.append('amount', expenseForm.value.amount ?? '');
        formData.append('booking_id', String(bookingRecord.value.id));
        formData.append('vendor_id', expenseForm.value.vendor_id ?? '');
        formData.append('user_id', expenseForm.value.user_id ?? '');
        formData.append('expense_category_id', expenseForm.value.expense_category_id ?? '');
        formData.append('notes', expenseForm.value.notes ?? '');

        if (expenseForm.value.receipt) {
            formData.append('receipt', expenseForm.value.receipt);
        }

        const record = await submitForm({
            url: props.data.routes.expenseStore,
            method: 'post',
            data: formData,
        });

        bookingRecord.value = {
            ...bookingRecord.value,
            expenses: [record, ...(bookingRecord.value.expenses ?? [])],
        };
        cancelExpenseCreate();
        openExpenseDetails(record);
    } catch {}
};

const startDocumentCreate = () => {
    showDocumentEditor.value = true;
    documentErrors.value = {};
    documentForm.value = buildDocumentForm();
};

const cancelDocumentCreate = () => {
    showDocumentEditor.value = false;
    documentErrors.value = {};
    documentForm.value = buildDocumentForm();

    if (documentFileInput.value) {
        documentFileInput.value.value = '';
    }
};

const triggerDocumentFileUpload = () => {
    documentFileInput.value?.click();
};

const handleDocumentFileSelected = (event) => {
    const [file] = event.target?.files ?? [];
    documentForm.value.file = file ?? null;

    if (file && isBlank(documentForm.value.title)) {
        documentForm.value.title = file.name.replace(/\.[^/.]+$/, '');
    }
};

const saveDocument = async () => {
    const errors = {};

    if (isBlank(documentForm.value.document_type)) {
        errors.document_type = requiredMessage('Document type');
    }

    if (isBlank(documentForm.value.title)) {
        errors.title = requiredMessage('Document title');
    }

    if (!documentForm.value.file) {
        errors.file = requiredMessage('File');
    }

    documentErrors.value = errors;

    if (hasFieldErrors(errors)) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('document_type', documentForm.value.document_type ?? '');
        formData.append('title', documentForm.value.title ?? '');
        formData.append('notes', documentForm.value.notes ?? '');
        formData.append('file', documentForm.value.file);

        const record = await submitForm({
            url: props.data.routes.documentStore,
            method: 'post',
            data: formData,
        });

        bookingRecord.value = {
            ...bookingRecord.value,
            documents: [record, ...(bookingRecord.value.documents ?? [])],
        };
        cancelDocumentCreate();
        activeTab.value = 'documents';
    } catch {}
};

const removeDocument = async (document) => {
    if (!document?.delete_url) {
        return;
    }

    askDelete({
        kind: 'document',
        label: document.title || document.file_name || 'this document',
        onConfirm: async () => {
            await deleteRecord({ url: document.delete_url });
            bookingRecord.value = {
                ...bookingRecord.value,
                documents: (bookingRecord.value.documents ?? []).filter((entry) => entry.id !== document.id),
            };
        },
    });
};

const startContactCreate = () => {
    showContactEditor.value = true;
    contactErrors.value = {};
    contactForm.value = buildContactForm();
};

const cancelContactCreate = () => {
    showContactEditor.value = false;
    contactErrors.value = {};
    contactForm.value = buildContactForm();
};

const saveContact = async () => {
    const errors = {};

    if (isBlank(contactForm.value.name)) {
        errors.name = requiredMessage('Contact name');
    }

    contactErrors.value = errors;

    if (hasFieldErrors(errors)) {
        return;
    }

    try {
        const record = await submitForm({
            url: props.data.routes.contactStore,
            method: 'post',
            data: { ...contactForm.value },
        });

        contacts.value = [record, ...contacts.value];
        bookingRecord.value = {
            ...bookingRecord.value,
            contacts: [...contacts.value],
        };
        cancelContactCreate();
        activeTab.value = 'contacts';
    } catch {}
};

const syncBookingTaskAttachments = (event) => {
    taskForm.value.attachments = Array.from(event.target.files ?? []);
};

const removeContact = async (contact) => {
    if (!contact?.delete_url) {
        return;
    }

    askDelete({
        kind: 'contact',
        label: contact.name || 'this contact',
        onConfirm: async () => {
            await deleteRecord({ url: contact.delete_url });
            contacts.value = contacts.value.filter((entry) => entry.id !== contact.id);
            bookingRecord.value = {
                ...bookingRecord.value,
                contacts: [...contacts.value],
            };
        },
    });
};
</script>

<template>
    <section v-if="!bookingReady" class="rounded-xl border border-amber-300/30 bg-amber-300/10 px-4 py-4 text-sm text-amber-100 shadow-lg shadow-black/10">
        Booking details could not be loaded for this record yet. Please return to the booking list and open it again.
    </section>

    <template v-else>
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
                    <div>
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Quote Response</label>
                        <select v-model="editForm.quote_response_status_id" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(editValidationErrors, 'quote_response_status_id') ? 'border-rose-300/60' : ''">
                            <option v-for="status in quoteResponseStatusOptions" :key="status.id" :value="String(status.id)">{{ status.label }}</option>
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
                    <div>
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Event Name</label>
                        <input v-model="editForm.event_name" type="text" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Booking No</label>
                        <input v-model="editForm.booking_no" type="text" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50">
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
                    <div class="xl:col-span-4">
                        <div class="grid gap-3 lg:grid-cols-4">
                            <div v-if="selectedPackageHourlyPrices.length">
                                <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Package Timing</label>
                                <select v-model="editForm.package_hourly_price_id" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="invoiceAmountLocked">
                                    <option v-for="option in selectedPackageHourlyPrices" :key="option.id" :value="String(option.id)">{{ Number(option.hours).toFixed(2) }} hrs - ${{ option.price }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Discount Source</label>
                            <select v-model="editForm.booking_discount_source" class="rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="invoiceAmountLocked">
                                <option value="package">Package discount</option>
                                <option value="global">Global discount</option>
                                <option value="custom">Custom discount</option>
                                <option value="none">No discount</option>
                            </select>
                            </div>
                            <div v-if="editForm.booking_discount_source === 'package' || editForm.booking_discount_source === 'global'" class="lg:col-span-2">
                                <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Saved Discount</label>
                                <select v-model="editForm.discount_id" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="invoiceAmountLocked">
                                <option value="">No discount</option>
                                <option v-for="discount in availableDiscountOptions" :key="discount.id" :value="String(discount.id)">{{ discount.code }} - {{ discount.name }}</option>
                            </select>
                            </div>
                            <div v-if="editForm.booking_discount_source === 'custom'">
                                <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Type</label>
                                <select v-model="editForm.booking_discount_type" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="invoiceAmountLocked">
                                <option value="percentage">Percentage</option>
                                <option value="amount">Price</option>
                            </select>
                            </div>
                            <div v-if="editForm.booking_discount_source === 'custom'">
                                <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Discount Value</label>
                                <input v-model="editForm.booking_discount_value" type="number" min="0" :max="editForm.booking_discount_type === 'percentage' ? 100 : undefined" step="0.01" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50 disabled:cursor-not-allowed disabled:opacity-50" :placeholder="editForm.booking_discount_type === 'percentage' ? '0.00%' : '$0.00'" :disabled="invoiceAmountLocked">
                            </div>
                        </div>
                    </div>

                    <div class="xl:col-span-4">
                        <div class="grid gap-3 lg:grid-cols-5">
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
                            <div>
                                <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Event Type</label>
                                <select v-model="editForm.event_type" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50">
                                    <option v-for="eventType in data.eventTypes" :key="eventType" :value="eventType">{{ eventType }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="xl:col-span-4">
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Location</label>
                        <input v-model="editForm.event_location" data-google-address type="text" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(editValidationErrors, 'event_location') ? 'border-rose-300/60' : ''">
                    </div>
                    <div class="xl:col-span-4">
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Notes</label>
                        <textarea v-model="editForm.notes" rows="3" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-rose-300/50" />
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
                </div>
            </form>

            <div v-if="!isEditing" class="mb-3 flex items-center gap-2 rounded-xl border border-white/10 bg-slate-950/40 p-1">
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'overview' ? 'bg-rose-300 text-slate-950' : 'text-stone-300 hover:bg-white/5'" @click="activeTab = 'overview'">Overview</button>
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'tasks' ? 'bg-rose-300 text-slate-950' : 'text-stone-300 hover:bg-white/5'" @click="activeTab = 'tasks'">Task List</button>
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'invoice' ? 'bg-rose-300 text-slate-950' : 'text-stone-300 hover:bg-white/5'" @click="activeTab = 'invoice'">Invoice</button>
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'expenses' ? 'bg-rose-300 text-slate-950' : 'text-stone-300 hover:bg-white/5'" @click="activeTab = 'expenses'">Expense</button>
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'documents' ? 'bg-rose-300 text-slate-950' : 'text-stone-300 hover:bg-white/5'" @click="activeTab = 'documents'">Document</button>
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium transition" :class="activeTab === 'contacts' ? 'bg-rose-300 text-slate-950' : 'text-stone-300 hover:bg-white/5'" @click="activeTab = 'contacts'">Contacts</button>
            </div>

            <div v-if="!isEditing && activeTab === 'overview'" class="space-y-3">
                <div class="grid gap-2.5 lg:grid-cols-12">
                    <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5 lg:col-span-4">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Quote Number</p>
                        <div class="mt-1.5 flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-white">{{ bookingRecord.quote_number || 'Not assigned' }}</p>
                            <span
                                class="inline-flex h-7 items-center justify-center rounded-full px-2.5 text-[11px] font-medium leading-none"
                                :class="bookingRecord.customer_response_status === 'accepted' ? 'bg-emerald-400/15 text-emerald-200' : bookingRecord.customer_response_status === 'rejected' ? 'bg-rose-400/15 text-rose-200' : 'bg-amber-300/15 text-amber-200'"
                            >
                                {{ bookingRecord.customer_response_label || statusLabel(bookingRecord.customer_response_status) }}
                            </span>
                        </div>
                        <p v-if="bookingRecord.customer_responded_at_label" class="mt-1 text-xs text-stone-400">
                            Responded {{ bookingRecord.customer_responded_at_label }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5 lg:col-span-3">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Booking Status</p>
                        <div class="mt-1.5 flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex h-7 items-center justify-center rounded-full px-2.5 text-[11px] font-medium leading-none"
                                :class="bookingRecord.status === 'confirmed' ? 'bg-emerald-400/15 text-emerald-200' : bookingRecord.status === 'pending' ? 'bg-amber-300/15 text-amber-200' : bookingRecord.status === 'completed' ? 'bg-cyan-300/15 text-cyan-200' : 'bg-rose-400/15 text-rose-200'"
                            >
                                {{ bookingRecord.status_label || statusLabel(bookingRecord.status) }}
                            </span>
                        </div>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5 lg:col-span-5">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Event Location</p>
                        <p class="mt-1.5 text-sm font-semibold text-white">{{ bookingRecord.event_location || 'Not set' }}</p>
                    </div>
                    <div class="grid gap-2.5 lg:col-span-12 lg:grid-cols-4">
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Booking Type</p>
                            <p class="mt-1.5 text-sm font-medium text-stone-200">{{ bookingRecord.booking_kind_label || bookingKindLabel(bookingRecord.booking_kind) }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">{{ bookingRecord.booking_kind === 'customer' ? 'Customer Name' : 'Entry Name' }}</p>
                            <p class="mt-1.5 text-sm font-semibold text-white">{{ bookingRecord.display_name || bookingRecord.customer_name }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Email</p>
                            <p class="mt-1.5 text-sm font-medium text-stone-200">{{ bookingRecord.customer_email }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Phone</p>
                            <p class="mt-1.5 text-sm font-medium text-stone-200">{{ bookingRecord.customer_phone }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Event Name</p>
                            <p class="mt-1.5 text-sm font-medium text-stone-200">{{ bookingRecord.event_name || 'Not set' }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Booking No</p>
                            <p class="mt-1.5 text-sm font-medium text-stone-200">{{ bookingRecord.booking_no || 'Not set' }}</p>
                        </div>
                    </div>
                    <div v-if="bookingRecord.entry_description" class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5 lg:col-span-12">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Entry Description</p>
                        <p class="mt-1.5 text-sm leading-5 text-stone-300">{{ bookingRecord.entry_description }}</p>
                    </div>
                    <div class="grid gap-2.5 lg:col-span-12 lg:grid-cols-2">
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
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
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Discount</p>
                            <p class="mt-1.5 text-sm font-medium text-stone-200">
                                {{ bookingRecord.discount ? `${bookingRecord.discount.code} - ${bookingRecord.discount.name}` : 'No discount selected' }}
                            </p>
                            <p class="mt-1 text-xs text-stone-400">
                                {{ bookingRecord.booking_discount_source === 'global' ? 'Global discount' : bookingRecord.booking_discount_source === 'custom' ? 'Custom discount' : bookingRecord.booking_discount_source === 'none' ? 'No discount' : 'Package discount' }}
                            </p>
                            <p v-if="bookingRecord.booking_discount_value && Number(bookingRecord.booking_discount_value) > 0" class="mt-1 text-xs text-stone-300">
                                Booking discount: {{ bookingRecord.booking_discount_type === 'percentage' ? `${bookingRecord.booking_discount_value}%` : `$${bookingRecord.booking_discount_value}` }}
                            </p>
                            <p class="mt-1 text-xs text-emerald-200">-${{ bookingRecord.discount_amount }}</p>
                        </div>
                    </div>
                    <div class="grid gap-2.5 lg:col-span-12 lg:grid-cols-5">
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Event Type</p>
                            <p class="mt-1.5 text-sm font-medium text-stone-200">{{ bookingRecord.event_type_label || 'Not set' }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Event Date</p>
                            <p class="mt-1.5 text-sm font-medium text-stone-200">{{ bookingRecord.event_date_label }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Start Hour</p>
                            <p class="mt-1.5 text-sm font-medium text-stone-200">{{ bookingRecord.start_time_label || 'Not set' }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">End Hour</p>
                            <p class="mt-1.5 text-sm font-medium text-stone-200">{{ bookingRecord.end_time_label || 'Not set' }}</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5">
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Duration</p>
                            <p class="mt-1.5 text-sm font-medium text-stone-200">{{ bookingRecord.total_hours }}</p>
                        </div>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-slate-950/50 p-2.5 lg:col-span-8 xl:col-span-6">
                        <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Notes</p>
                        <p class="mt-1.5 text-sm leading-5 text-stone-300">{{ bookingRecord.notes || 'No notes' }}</p>
                    </div>
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
                    <div v-if="overviewSelectedItems.length" class="space-y-2 border-t border-white/10 px-3 py-2.5">
                        <div class="flex items-center justify-end gap-3">
                            <span class="text-[11px] uppercase tracking-[0.24em] text-stone-500">Initial Total Amount</span>
                            <span class="text-sm font-semibold text-stone-200">${{ overviewInitialTotal }}</span>
                        </div>
                        <div class="flex items-center justify-end gap-3">
                            <span class="text-[11px] uppercase tracking-[0.24em] text-stone-500">Discount Amount</span>
                            <span class="text-sm font-semibold text-emerald-200">-${{ bookingRecord.discount_amount }}</span>
                        </div>
                        <div class="flex items-center justify-end gap-3">
                            <span class="text-[11px] uppercase tracking-[0.24em] text-stone-500">Total Amount</span>
                            <span class="text-base font-semibold text-cyan-100">${{ bookingRecord.booking_total }}</span>
                        </div>
                    </div>
                    <p v-else class="border-t border-white/10 px-2.5 py-2.5 text-sm leading-5 text-stone-400">No package, equipment, or add-ons were selected for this booking.</p>
                </div>
            </div>

            <div v-if="!isEditing && activeTab === 'tasks'" class="space-y-3">
                <div class="overflow-hidden rounded-xl border border-white/10 bg-slate-950/50">
                    <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Task List</p>
                            <p class="mt-1 text-sm text-stone-300">Package action items are added here automatically, and you can still add general tasks manually.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] text-stone-300">{{ tasks.length }}</span>
                            <button type="button" class="rounded-lg bg-cyan-300 px-3 py-1.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200" @click="startTaskCreate">
                                Add Task
                            </button>
                        </div>
                    </div>
                    <div v-if="tasks.length" class="overflow-x-auto border-t border-white/10">
                        <div class="min-w-[1180px]">
                            <div class="grid grid-cols-[minmax(0,1.15fr)_10rem_10rem_8rem_8rem_8rem_minmax(0,1fr)_minmax(0,1.2fr)_8rem] gap-2 border-b border-white/10 px-3 py-1.5 text-[10px] uppercase tracking-[0.18em] text-stone-500">
                                <span>Task</span>
                                <span>Status</span>
                                <span>Assigned To</span>
                                <span>Hours</span>
                                <span>Due Date</span>
                                <span>Started</span>
                                <span>Remarks</span>
                                <span>Customer Reply</span>
                                <span>Actions</span>
                            </div>
                            <div v-for="task in tasks" :key="task.id" class="grid grid-cols-[minmax(0,1.15fr)_10rem_10rem_8rem_8rem_8rem_minmax(0,1fr)_minmax(0,1.2fr)_8rem] items-center gap-2 border-b border-white/10 px-3 py-2 last:border-b-0">
                                <p class="truncate text-sm font-medium text-white">{{ task.task_name }}</p>
                                <p class="truncate text-sm text-cyan-100">{{ task.status_name || 'No status' }}</p>
                                <p class="truncate text-sm text-stone-300">{{ task.assigned_to_name }}</p>
                                <p class="text-sm text-stone-300">{{ task.task_duration_hours || '0.00' }}</p>
                                <p class="text-sm text-stone-300">{{ task.due_date_label }}</p>
                                <p class="text-sm text-stone-300">{{ task.date_started_label }}</p>
                                <p class="truncate text-sm text-stone-400">{{ task.remarks || 'No remarks' }}</p>
                                <div class="min-w-0">
                                    <p class="truncate text-sm text-stone-300">{{ task.customer_response_note || 'No reply yet' }}</p>
                                    <div v-if="task.customer_response_attachments?.length" class="mt-1 flex flex-wrap gap-1">
                                        <a
                                            v-for="attachment in task.customer_response_attachments"
                                            :key="`${task.id}-${attachment.url}`"
                                            :href="attachment.url"
                                            target="_blank"
                                            rel="noreferrer"
                                            class="inline-flex max-w-full truncate rounded-full border border-cyan-300/20 bg-cyan-300/10 px-2 py-0.5 text-[11px] text-cyan-100 hover:bg-cyan-300/15"
                                        >
                                            {{ attachment.name }}
                                        </a>
                                    </div>
                                    <p class="mt-1 text-[11px] text-stone-500">{{ task.customer_response_at_label }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="rounded-lg border border-white/10 px-2.5 py-1 text-xs font-semibold text-white transition hover:bg-white/5" @click="startTaskEdit(task)">Edit Task</button>
                                    <button type="button" class="rounded-lg border border-rose-400/30 px-2.5 py-1 text-xs font-semibold text-rose-100 transition hover:bg-rose-400/10" @click="removeTask(task)">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="border-t border-white/10 px-3 py-3 text-sm text-stone-400">No tasks have been attached to this booking yet.</p>
                </div>

                <div v-if="showTaskEditor" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur-sm" @click.self="cancelTaskEdit">
                    <div class="w-full max-w-5xl overflow-hidden rounded-2xl border border-white/10 bg-[#132035] shadow-2xl shadow-black/40">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
                            <div>
                                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">{{ editingTask ? 'Edit Task' : 'Add Task' }}</p>
                                <p class="mt-1 text-sm text-stone-300">{{ editingTask ? 'Update the selected booking task details.' : 'Add a new task for this booking.' }}</p>
                            </div>
                            <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm text-stone-300 transition hover:bg-white/5" @click="cancelTaskEdit">Close</button>
                        </div>

                        <form class="max-h-[80vh] overflow-y-auto p-4" novalidate @submit.prevent="saveTask">
                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
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
                                    <textarea v-model="taskForm.remarks" rows="4" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" />
                                </div>
                                <div class="xl:col-span-4">
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Task Files</label>
                                    <input ref="taskAttachmentInput" type="file" multiple accept="image/*,video/*,.pdf" class="block w-full rounded-lg border border-dashed border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-stone-300 file:mr-3 file:rounded-full file:border-0 file:bg-cyan-300/15 file:px-3 file:py-2 file:text-xs file:font-medium file:text-cyan-100 hover:file:bg-cyan-300/20" @change="syncBookingTaskAttachments">
                                    <p class="mt-1 text-xs text-stone-400">Upload files the assignee should be able to view in the client portal. New uploads are added to the task.</p>
                                    <p v-if="firstError(taskValidationErrors, 'attachments')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(taskValidationErrors, 'attachments') }}</p>
                                    <p v-else-if="firstError(taskValidationErrors, 'attachments.0')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(taskValidationErrors, 'attachments.0') }}</p>
                                </div>
                                <div v-if="editingTask?.task_attachments?.length" class="xl:col-span-4 rounded-xl border border-white/10 bg-slate-950/40 p-4">
                                    <p class="text-[11px] uppercase tracking-[0.24em] text-stone-400">Existing Files</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <a
                                            v-for="attachment in editingTask.task_attachments"
                                            :key="`${editingTask.id}-${attachment.url}`"
                                            :href="attachment.url"
                                            target="_blank"
                                            rel="noreferrer"
                                            class="inline-flex items-center rounded-full border border-cyan-300/20 bg-cyan-300/10 px-3 py-1 text-xs font-medium text-cyan-100 transition hover:bg-cyan-300/15"
                                        >
                                            {{ attachment.name }}
                                        </a>
                                    </div>
                                </div>
                                <div v-if="editingTask" class="xl:col-span-4 rounded-xl border border-white/10 bg-slate-950/40 p-4">
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
                                                class="inline-flex items-center rounded-full border border-cyan-300/20 bg-cyan-300/10 px-3 py-1 text-xs font-medium text-cyan-100 transition hover:bg-cyan-300/15"
                                            >
                                                {{ attachment.name }}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end gap-2 border-t border-white/10 pt-4">
                                <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm text-stone-300 transition hover:bg-white/5" @click="cancelTaskEdit">Cancel</button>
                                <button type="submit" class="rounded-lg bg-cyan-300 px-3 py-1.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                                    {{ saving ? 'Saving...' : editingTask ? 'Save task' : 'Add task' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div v-if="!isEditing && activeTab === 'expenses'" class="space-y-3">
                <div class="overflow-hidden rounded-xl border border-white/10 bg-slate-950/50">
                    <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Expense List</p>
                            <p class="mt-1 text-sm text-stone-300">Expenses linked to this booking.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] text-stone-300">{{ bookingRecord.expenses?.length ?? 0 }}</span>
                            <button type="button" class="rounded-lg bg-cyan-300 px-3 py-1.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200" @click="startExpenseCreate">
                                Add Expense
                            </button>
                        </div>
                    </div>
                    <div v-if="bookingRecord.expenses?.length" class="overflow-x-auto border-t border-white/10">
                        <div class="min-w-[880px]">
                            <div class="grid grid-cols-[8rem_minmax(0,1.2fr)_10rem_10rem_10rem_minmax(0,1fr)_8rem] gap-2 border-b border-white/10 px-3 py-1.5 text-[10px] uppercase tracking-[0.18em] text-stone-500">
                                <span>Date</span>
                                <span>Expense</span>
                                <span>Category</span>
                                <span>Vendor</span>
                                <span>Amount</span>
                                <span>Notes</span>
                                <span>Receipt</span>
                            </div>
                            <button v-for="expense in bookingRecord.expenses" :key="expense.id" type="button" class="grid w-full grid-cols-[8rem_minmax(0,1.2fr)_10rem_10rem_10rem_minmax(0,1fr)_8rem] items-center gap-2 border-b border-white/10 px-3 py-2 text-left transition hover:bg-white/[0.03] last:border-b-0" @click="openExpenseDetails(expense)">
                                <p class="text-sm text-stone-300">{{ expense.expense_date_label }}</p>
                                <p class="truncate text-sm font-medium text-white">{{ expense.expense_name }}</p>
                                <p class="truncate text-sm text-stone-300">{{ expense.expense_category_label }}</p>
                                <p class="truncate text-sm text-stone-300">{{ expense.vendor_label }}</p>
                                <p class="text-sm font-semibold text-cyan-100">${{ expense.amount }}</p>
                                <p class="truncate text-sm text-stone-400">{{ expense.notes || 'No notes' }}</p>
                                <a v-if="expense.receipt_url" :href="expense.receipt_url" target="_blank" rel="noreferrer" class="text-sm font-medium text-cyan-200 hover:text-cyan-100" @click.stop>View</a>
                                <p v-else class="text-sm text-stone-500">None</p>
                            </button>
                        </div>
                    </div>
                    <p v-else class="border-t border-white/10 px-3 py-3 text-sm text-stone-400">No expenses have been linked to this booking yet.</p>
                </div>

                <div v-if="showExpenseDetails && selectedExpense" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur-sm" @click.self="closeExpenseDetails">
                    <div class="w-full max-w-3xl overflow-hidden rounded-2xl border border-white/10 bg-[#132035] shadow-2xl shadow-black/40">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
                            <div>
                                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Expense Details</p>
                                <p class="mt-1 text-sm text-stone-300">Review this booking-linked expense without leaving the booking page.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm text-stone-300 transition hover:bg-white/5" @click="closeExpenseDetails">Close</button>
                        </div>
                        <div class="grid gap-3 p-4 sm:grid-cols-2">
                            <div class="sm:col-span-2 rounded-xl border border-white/10 bg-slate-950/50 px-3 py-2.5">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Expense</p>
                                <p class="mt-1 text-sm font-semibold text-white">{{ selectedExpense.expense_name }}</p>
                            </div>
                            <div class="rounded-xl border border-white/10 bg-slate-950/50 px-3 py-2.5">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Date</p>
                                <p class="mt-1 text-sm text-white">{{ selectedExpense.expense_date_label }}</p>
                            </div>
                            <div class="rounded-xl border border-white/10 bg-slate-950/50 px-3 py-2.5">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Amount</p>
                                <p class="mt-1 text-sm font-semibold text-cyan-100">${{ selectedExpense.amount }}</p>
                            </div>
                            <div class="rounded-xl border border-white/10 bg-slate-950/50 px-3 py-2.5">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Category</p>
                                <p class="mt-1 text-sm text-white">{{ selectedExpense.expense_category_label }}</p>
                            </div>
                            <div class="rounded-xl border border-white/10 bg-slate-950/50 px-3 py-2.5">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Vendor</p>
                                <p class="mt-1 text-sm text-white">{{ selectedExpense.vendor_label }}</p>
                            </div>
                            <div class="rounded-xl border border-white/10 bg-slate-950/50 px-3 py-2.5">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">User</p>
                                <p class="mt-1 text-sm text-white">{{ selectedExpense.user_label || 'Not linked' }}</p>
                            </div>
                            <div class="sm:col-span-2 rounded-xl border border-white/10 bg-slate-950/50 px-3 py-2.5">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Booking</p>
                                <p class="mt-1 text-sm text-white">{{ selectedExpense.booking_label }}</p>
                            </div>
                            <div class="sm:col-span-2 rounded-xl border border-white/10 bg-slate-950/50 px-3 py-2.5">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Notes</p>
                                <p class="mt-1 text-sm text-white">{{ selectedExpense.notes || 'No notes' }}</p>
                            </div>
                            <div class="sm:col-span-2 rounded-xl border border-white/10 bg-slate-950/50 px-3 py-2.5">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Receipt</p>
                                <a v-if="selectedExpense.receipt_url" :href="selectedExpense.receipt_url" target="_blank" rel="noreferrer" class="mt-1 inline-flex text-sm font-medium text-cyan-200 hover:text-cyan-100">
                                    {{ selectedExpense.receipt_name || 'View receipt' }}
                                </a>
                                <p v-else class="mt-1 text-sm text-stone-400">No receipt attached.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="showExpenseEditor" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur-sm" @click.self="cancelExpenseCreate">
                    <div class="w-full max-w-3xl overflow-hidden rounded-2xl border border-white/10 bg-[#132035] shadow-2xl shadow-black/40">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
                            <div>
                                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Add Expense</p>
                                <p class="mt-1 text-sm text-stone-300">This expense will be attached to {{ bookingRecord.quote_number || bookingRecord.display_name || bookingRecord.customer_name }} automatically.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm text-stone-300 transition hover:bg-white/5" @click="cancelExpenseCreate">Close</button>
                        </div>

                        <form class="max-h-[80vh] overflow-y-auto p-4" novalidate @submit.prevent="saveExpense">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Expense Name</label>
                                    <input v-model="expenseForm.expense_name" type="text" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(expenseValidationErrors, 'expense_name') ? 'border-rose-300/60' : ''">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Expense Date</label>
                                    <input v-model="expenseForm.expense_date" type="date" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(expenseValidationErrors, 'expense_date') ? 'border-rose-300/60' : ''" @click="openDatePicker" @keydown.prevent>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Amount</label>
                                    <input v-model="expenseForm.amount" type="number" min="0" step="0.01" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(expenseValidationErrors, 'amount') ? 'border-rose-300/60' : ''">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Expense Category</label>
                                    <select v-model="expenseForm.expense_category_id" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50">
                                        <option value="">Select category</option>
                                        <option v-for="category in expenseCategoryOptions" :key="category.id" :value="String(category.id)">{{ category.label }}</option>
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Vendor</label>
                                    <select v-model="expenseForm.vendor_id" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50">
                                        <option value="">Select vendor</option>
                                        <option v-for="vendor in vendorOptions" :key="vendor.id" :value="String(vendor.id)">{{ vendor.label }}</option>
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">User</label>
                                    <select v-model="expenseForm.user_id" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50">
                                        <option value="">Select user</option>
                                        <option v-for="user in userOptions" :key="user.id" :value="String(user.id)">{{ user.label }}</option>
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Receipt</label>
                                    <div class="flex flex-wrap items-center gap-2 rounded-xl border border-dashed border-white/10 bg-slate-950/40 px-3 py-3">
                                        <input ref="expenseReceiptInput" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="hidden" @change="handleExpenseReceiptSelected">
                                        <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm text-stone-200 transition hover:bg-white/5" @click="triggerExpenseReceiptUpload">Upload receipt</button>
                                        <span class="text-sm text-stone-400">{{ selectedExpenseReceiptName || 'No file selected' }}</span>
                                    </div>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Notes</label>
                                    <textarea v-model="expenseForm.notes" rows="4" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" />
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end gap-2 border-t border-white/10 pt-4">
                                <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm text-stone-300 transition hover:bg-white/5" @click="cancelExpenseCreate">Cancel</button>
                                <button type="submit" class="rounded-lg bg-cyan-300 px-3 py-1.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                                    {{ saving ? 'Saving...' : 'Add expense' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div v-if="!isEditing && activeTab === 'documents'" class="space-y-3">
                <div class="overflow-hidden rounded-xl border border-white/10 bg-slate-950/50">
                    <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Document List</p>
                            <p class="mt-1 text-sm text-stone-300">Quote, invoice, receipts, client uploads, and booking files in one place.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] text-stone-300">{{ bookingRecord.documents?.length ?? 0 }}</span>
                            <button type="button" class="rounded-lg bg-cyan-300 px-3 py-1.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200" @click="startDocumentCreate">
                                Add Document
                            </button>
                        </div>
                    </div>
                    <div v-if="bookingRecord.documents?.length" class="overflow-x-auto border-t border-white/10">
                        <div class="min-w-[1100px]">
                            <div class="grid grid-cols-[10rem_9rem_minmax(0,1.1fr)_11rem_11rem_minmax(0,1fr)_8rem] gap-2 border-b border-white/10 px-3 py-1.5 text-[10px] uppercase tracking-[0.18em] text-stone-500">
                                <span>Added</span>
                                <span>Type</span>
                                <span>Document</span>
                                <span>Source</span>
                                <span>Uploaded By</span>
                                <span>Notes</span>
                                <span>Actions</span>
                            </div>
                            <div v-for="document in bookingRecord.documents" :key="document.id" class="grid grid-cols-[10rem_9rem_minmax(0,1.1fr)_11rem_11rem_minmax(0,1fr)_8rem] items-center gap-2 border-b border-white/10 px-3 py-2 last:border-b-0">
                                <p class="text-sm text-stone-300">{{ document.created_at_label }}</p>
                                <p class="text-sm text-cyan-100">{{ document.document_type_label }}</p>
                                <div class="min-w-0">
                                    <a :href="document.url" target="_blank" rel="noreferrer" class="block truncate text-sm font-medium text-white hover:text-cyan-100">
                                        {{ document.title }}
                                    </a>
                                    <p class="truncate text-xs text-stone-500">{{ document.file_name }}</p>
                                </div>
                                <p class="truncate text-sm text-stone-300">{{ document.source_label }}</p>
                                <p class="truncate text-sm text-stone-300">{{ document.uploaded_by_label }}</p>
                                <p class="truncate text-sm text-stone-400">{{ document.notes || 'No notes' }}</p>
                                <div class="flex items-center gap-2">
                                    <a :href="document.url" target="_blank" rel="noreferrer" class="rounded-lg border border-cyan-300/20 px-2.5 py-1 text-xs font-medium text-cyan-100 transition hover:bg-cyan-300/10">
                                        Open
                                    </a>
                                    <button v-if="document.can_delete" type="button" class="rounded-lg border border-rose-300/20 px-2.5 py-1 text-xs font-medium text-rose-200 transition hover:bg-rose-300/10" @click="removeDocument(document)">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="border-t border-white/10 px-3 py-3 text-sm text-stone-400">No documents have been collected for this booking yet.</p>
                </div>

                <div v-if="showDocumentEditor" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur-sm" @click.self="cancelDocumentCreate">
                    <div class="w-full max-w-2xl overflow-hidden rounded-2xl border border-white/10 bg-[#132035] shadow-2xl shadow-black/40">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
                            <div>
                                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Add Document</p>
                                <p class="mt-1 text-sm text-stone-300">Upload a file directly onto this booking record.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm text-stone-300 transition hover:bg-white/5" @click="cancelDocumentCreate">Close</button>
                        </div>
                        <form class="max-h-[80vh] overflow-y-auto p-4" novalidate @submit.prevent="saveDocument">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Document Type</label>
                                    <select v-model="documentForm.document_type" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(documentValidationErrors, 'document_type') ? 'border-rose-300/60' : ''">
                                        <option v-for="option in documentTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Title</label>
                                    <input v-model="documentForm.title" type="text" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(documentValidationErrors, 'title') ? 'border-rose-300/60' : ''">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">File</label>
                                    <div class="flex flex-wrap items-center gap-2 rounded-xl border border-dashed border-white/10 bg-slate-950/40 px-3 py-3" :class="firstError(documentValidationErrors, 'file') ? 'border-rose-300/60' : ''">
                                        <input ref="documentFileInput" type="file" class="hidden" @change="handleDocumentFileSelected">
                                        <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm text-stone-200 transition hover:bg-white/5" @click="triggerDocumentFileUpload">Choose file</button>
                                        <span class="text-sm text-stone-400">{{ selectedDocumentFileName || 'No file selected' }}</span>
                                    </div>
                                    <p v-if="firstError(documentValidationErrors, 'file')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(documentValidationErrors, 'file') }}</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Notes</label>
                                    <textarea v-model="documentForm.notes" rows="4" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" />
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end gap-2 border-t border-white/10 pt-4">
                                <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm text-stone-300 transition hover:bg-white/5" @click="cancelDocumentCreate">Cancel</button>
                                <button type="submit" class="rounded-lg bg-cyan-300 px-3 py-1.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                                    {{ saving ? 'Saving...' : 'Add document' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div v-if="!isEditing && activeTab === 'contacts'" class="space-y-3">
                <div class="overflow-hidden rounded-xl border border-white/10 bg-slate-950/50">
                    <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Contact List</p>
                            <p class="mt-1 text-sm text-stone-300">Customer, vendor, and manual contacts for this booking.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] text-stone-300">{{ contacts.length }}</span>
                            <button type="button" class="rounded-lg bg-cyan-300 px-3 py-1.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200" @click="startContactCreate">
                                Add Contact
                            </button>
                        </div>
                    </div>
                    <div v-if="contacts.length" class="overflow-x-auto border-t border-white/10">
                        <div class="min-w-[980px]">
                            <div class="grid grid-cols-[8rem_minmax(0,1fr)_minmax(0,1fr)_13rem_10rem_minmax(0,1fr)_6rem] gap-2 border-b border-white/10 px-3 py-1.5 text-[10px] uppercase tracking-[0.18em] text-stone-500">
                                <span>Source</span>
                                <span>Name</span>
                                <span>Company / Role</span>
                                <span>Email</span>
                                <span>Phone</span>
                                <span>Notes</span>
                                <span></span>
                            </div>
                            <div v-for="contact in contacts" :key="contact.id" class="grid grid-cols-[8rem_minmax(0,1fr)_minmax(0,1fr)_13rem_10rem_minmax(0,1fr)_6rem] items-center gap-2 border-b border-white/10 px-3 py-2 last:border-b-0">
                                <p class="text-sm text-cyan-100">{{ contact.source_label }}</p>
                                <p class="truncate text-sm font-medium text-white">{{ contact.name }}</p>
                                <div class="min-w-0">
                                    <p class="truncate text-sm text-stone-300">{{ contact.company_name || 'No company' }}</p>
                                    <p class="truncate text-xs text-stone-500">{{ contact.role || 'No role' }}</p>
                                </div>
                                <a v-if="contact.email" :href="`mailto:${contact.email}`" class="truncate text-sm text-cyan-100 hover:text-cyan-50">{{ contact.email }}</a>
                                <p v-else class="text-sm text-stone-500">No email</p>
                                <a v-if="contact.phone" :href="`tel:${contact.phone}`" class="truncate text-sm text-stone-300 hover:text-white">{{ contact.phone }}</a>
                                <p v-else class="text-sm text-stone-500">No phone</p>
                                <p class="truncate text-sm text-stone-400">{{ contact.notes || 'No notes' }}</p>
                                <button type="button" class="rounded-lg border border-rose-300/20 px-2.5 py-1 text-xs font-medium text-rose-200 transition hover:bg-rose-300/10" @click="removeContact(contact)">
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-else class="border-t border-white/10 px-3 py-3 text-sm text-stone-400">No contacts have been added to this booking yet.</p>
                </div>

                <div v-if="showContactEditor" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur-sm" @click.self="cancelContactCreate">
                    <div class="w-full max-w-2xl overflow-hidden rounded-2xl border border-white/10 bg-[#132035] shadow-2xl shadow-black/40">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
                            <div>
                                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Add Contact</p>
                                <p class="mt-1 text-sm text-stone-300">Choose a customer, choose a vendor, or type the contact manually.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm text-stone-300 transition hover:bg-white/5" @click="cancelContactCreate">Close</button>
                        </div>
                        <form class="max-h-[80vh] overflow-y-auto p-4" novalidate @submit.prevent="saveContact">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Source</label>
                                    <select v-model="contactForm.source_type" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50">
                                        <option value="manual">Manual</option>
                                        <option value="customer">Customer</option>
                                        <option value="vendor">Vendor</option>
                                    </select>
                                </div>
                                <div v-if="contactForm.source_type !== 'manual'">
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">{{ contactForm.source_type === 'vendor' ? 'Vendor' : 'Customer' }}</label>
                                    <select v-model="contactForm.source_id" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50">
                                        <option value="">Select {{ contactForm.source_type }}</option>
                                        <option v-for="option in contactSourceOptions" :key="option.id" :value="String(option.id)">{{ option.label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Name</label>
                                    <input v-model="contactForm.name" type="text" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(contactValidationErrors, 'name') ? 'border-rose-300/60' : ''">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Company</label>
                                    <input v-model="contactForm.company_name" type="text" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Role</label>
                                    <input v-model="contactForm.role" type="text" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Email</label>
                                    <input v-model="contactForm.email" type="email" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" :class="firstError(contactValidationErrors, 'email') ? 'border-rose-300/60' : ''">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Phone</label>
                                    <input v-model="contactForm.phone" type="text" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Notes</label>
                                    <textarea v-model="contactForm.notes" rows="4" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-1.5 text-sm text-white outline-none transition focus:border-cyan-300/50" />
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end gap-2 border-t border-white/10 pt-4">
                                <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm text-stone-300 transition hover:bg-white/5" @click="cancelContactCreate">Cancel</button>
                                <button type="submit" class="rounded-lg bg-cyan-300 px-3 py-1.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                                    {{ saving ? 'Saving...' : 'Add contact' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div v-if="isEditing || activeTab === 'invoice'" class="mt-3">
                <form class="overflow-hidden rounded-xl border border-white/10 bg-[#f4f5f7] text-slate-950 shadow-xl shadow-black/10" novalidate @submit.prevent="createInvoice">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-300/80 bg-white px-4 py-3">
                        <div>
                            <p class="text-sm text-slate-500">Sales overview / Invoices</p>
                            <div class="mt-1 flex items-center gap-2">
                                <h3 class="text-lg font-semibold text-slate-950">{{ bookingRecord.invoice ? 'Edit invoice' : 'New invoice' }}</h3>
                                <span class="rounded border border-slate-300 bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ bookingRecord.invoice?.status_label ?? statusLabel(bookingRecord.invoice?.status ?? 'draft') }}</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a
                                v-if="bookingRecord.invoice?.pdf_url"
                                :href="bookingRecord.invoice.pdf_url"
                                target="_blank"
                                rel="noreferrer"
                                :class="invoicePreviewButtonClass"
                            >
                                Preview
                            </a>
                            <button type="submit" :class="invoiceSaveButtonClass" :disabled="saving || !invoiceCanEdit">
                                {{ saving ? 'Saving...' : (bookingRecord.invoice ? 'Save & close' : 'Save invoice') }}
                            </button>
                            <button type="button" :class="invoiceApproveButtonClass" :disabled="saving || !bookingRecord.invoice" @click="sendInvoice">
                                {{ saving ? 'Sending...' : 'Approve & email' }}
                            </button>
                        </div>
                    </div>

                    <div class="mx-auto my-5 w-full max-w-7xl rounded border border-slate-300 bg-white p-4">
                        <div v-if="!invoiceCanEdit" class="mb-4 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                            This invoice has payment activity, so the deposit and installment schedule are locked.
                        </div>

                        <div class="grid gap-3 lg:grid-cols-6">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-800">Contact</label>
                                <div class="flex h-9 items-center rounded border border-slate-300 bg-slate-50 px-2 text-sm font-medium text-slate-900">
                                    {{ bookingRecord.customer_name || 'No contact' }}
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-800">Issue date</label>
                                <input v-model="invoiceForm.issue_date" type="date" class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm text-slate-950 outline-none focus:border-blue-500" :disabled="!invoiceCanEdit" :class="firstError(invoiceValidationErrors, 'issue_date') ? 'border-red-500' : ''" @click="openDatePicker">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-800">Due date</label>
                                <input v-model="invoiceForm.first_due_date" type="date" class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm text-slate-950 outline-none focus:border-blue-500" :disabled="!invoiceCanEdit" :class="firstError(invoiceValidationErrors, 'first_due_date') ? 'border-red-500' : ''" @click="openDatePicker">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-800">Invoice number</label>
                                <input v-model="invoiceForm.invoice_number" type="text" class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm text-slate-950 outline-none focus:border-blue-500" :placeholder="bookingRecord.invoice ? '' : 'Auto generated'" :disabled="!bookingRecord.invoice || !invoiceCanEdit" :class="firstError(invoiceValidationErrors, 'invoice_number') ? 'border-red-500' : ''">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-800">Reference</label>
                                <div class="flex h-9 items-center rounded border border-slate-300 bg-slate-50 px-2 text-sm text-slate-900">
                                    {{ bookingRecord.quote_number || 'No quote' }}
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-800">Online payments</label>
                                <div class="flex h-9 items-center rounded bg-slate-100 px-2 text-sm font-semibold text-slate-950">Enabled</div>
                            </div>
                        </div>

                        <div class="mt-4 w-full max-w-xs">
                            <label class="mb-1 block text-xs font-semibold text-slate-800">Amounts are</label>
                            <select v-model="invoiceForm.amounts_are" class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm text-slate-950 outline-none focus:border-blue-500" :disabled="!invoiceCanEdit" :class="firstError(invoiceValidationErrors, 'amounts_are') ? 'border-red-500' : ''">
                                <option v-for="option in amountsAreOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </div>

                        <div class="mt-4 overflow-x-auto">
                            <div class="min-w-[1050px] overflow-hidden border border-slate-300">
                                <div class="grid grid-cols-[2.4rem_1.1fr_2fr_7rem_8rem_7rem_8rem_8rem_8rem] bg-slate-100 text-xs font-semibold text-slate-800">
                                    <div class="border-r border-slate-300 px-2 py-3"></div>
                                    <div class="border-r border-slate-300 px-2 py-3 text-center">Item</div>
                                    <div class="border-r border-slate-300 px-2 py-3">Description</div>
                                    <div class="border-r border-slate-300 px-2 py-3 text-right">Qty.</div>
                                    <div class="border-r border-slate-300 px-2 py-3 text-right">Price</div>
                                    <div class="border-r border-slate-300 px-2 py-3 text-right">Disc.</div>
                                    <div class="border-r border-slate-300 px-2 py-3">Tax rate</div>
                                    <div class="border-r border-slate-300 px-2 py-3 text-right">Tax amount</div>
                                    <div class="px-2 py-3 text-right">Amount</div>
                                </div>
                                <div
                                    v-for="row in invoiceItemsPreview"
                                    :key="row.id"
                                    class="grid grid-cols-[2.4rem_1.1fr_2fr_7rem_8rem_7rem_8rem_8rem_8rem] border-t border-slate-300 text-sm text-slate-950"
                                >
                                    <div class="border-r border-slate-300 px-2 py-3 text-center text-slate-400">::</div>
                                    <div class="border-r border-slate-300 px-2 py-3">{{ row.item }}</div>
                                    <div class="border-r border-slate-300 p-1.5">
                                        <textarea
                                            v-if="row.type === 'package'"
                                            v-model="invoiceForm.line_description"
                                            rows="2"
                                            class="w-full resize-none rounded border border-transparent bg-white px-2 py-1 text-sm text-slate-950 outline-none focus:border-blue-500"
                                            :disabled="!invoiceCanEdit"
                                            :class="firstError(invoiceValidationErrors, 'line_description') ? 'border-red-500' : ''"
                                        />
                                        <div v-else class="px-2 py-1 text-sm text-slate-700">
                                            {{ row.description }}
                                        </div>
                                    </div>
                                    <div class="border-r border-slate-300 px-2 py-3 text-right">{{ row.quantity }}</div>
                                    <div class="border-r border-slate-300 px-2 py-3 text-right">{{ row.price }}</div>
                                    <div class="border-r border-slate-300 px-2 py-3 text-right">{{ row.discount }}</div>
                                    <div class="border-r border-slate-300 p-1.5">
                                        <select
                                            v-if="row.type === 'package'"
                                            v-model="invoiceForm.tax_rate"
                                            class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm text-slate-950 outline-none focus:border-blue-500 disabled:bg-slate-100 disabled:text-slate-500"
                                            :disabled="!invoiceCanEdit || invoiceTaxRateDisabled"
                                            :class="firstError(invoiceValidationErrors, 'tax_rate') ? 'border-red-500' : ''"
                                        >
                                            <option v-if="invoiceTaxRateDisabled" value="">No tax</option>
                                            <option v-for="option in taxRateOptions" v-else :key="option.value" :value="option.value">{{ option.label }}</option>
                                        </select>
                                        <div v-else class="px-2 py-1 text-sm text-slate-700">
                                            {{ invoiceTaxRateDisabled ? 'No tax' : taxRateOptions.find((option) => option.value === invoiceForm.tax_rate)?.label || '' }}
                                        </div>
                                    </div>
                                    <div class="border-r border-slate-300 px-2 py-3 text-right">{{ row.type === 'package' ? invoiceGstAmount : '0.00' }}</div>
                                    <div class="px-2 py-3 text-right font-semibold">{{ row.amount }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_500px]">
                            <div class="space-y-3">
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-slate-800">Installments</label>
                                        <input v-model="invoiceForm.installment_count" type="number" min="1" max="12" class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm text-slate-950 outline-none focus:border-blue-500" :disabled="!invoiceCanEdit" :class="firstError(invoiceValidationErrors, 'installment_count') ? 'border-red-500' : ''">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-slate-800">Every days</label>
                                        <input v-model="invoiceForm.interval_days" type="number" min="1" max="90" class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm text-slate-950 outline-none focus:border-blue-500" :disabled="!invoiceCanEdit" :class="firstError(invoiceValidationErrors, 'interval_days') ? 'border-red-500' : ''">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-slate-800">Deposit type</label>
                                        <select v-model="invoiceForm.deposit_type" class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm text-slate-950 outline-none focus:border-blue-500" :disabled="!invoiceCanEdit">
                                            <option value="percentage">Percentage</option>
                                            <option value="amount">Amount</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="space-y-3 text-sm">
                                    <div class="flex justify-between">
                                        <span>Subtotal</span>
                                        <span>{{ invoiceSubtotal }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Total GST</span>
                                        <span>{{ invoiceGstAmount }}</span>
                                    </div>
                                    <div class="border-t-2 border-slate-400 pt-3">
                                        <div class="flex justify-between text-xl font-bold">
                                            <span>Total</span>
                                            <span>{{ invoiceTotalAmount }}</span>
                                        </div>
                                    </div>
                                    <div class="rounded border border-slate-300 bg-slate-50 p-3">
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold text-slate-800">Deposit %</label>
                                                <input v-model="invoiceForm.deposit_percentage" type="number" min="0" max="100" step="0.01" class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm text-slate-950 outline-none focus:border-blue-500" :disabled="!invoiceCanEdit || invoiceForm.deposit_type !== 'percentage'" :class="firstError(invoiceValidationErrors, 'deposit_percentage') ? 'border-red-500' : ''">
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold text-slate-800">Deposit amount</label>
                                                <input v-model="invoiceForm.deposit_amount" type="number" min="0" :max="invoiceTotalAmount" step="0.01" class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm text-slate-950 outline-none focus:border-blue-500" :disabled="!invoiceCanEdit || invoiceForm.deposit_type !== 'amount'" :class="firstError(invoiceValidationErrors, 'deposit_amount') ? 'border-red-500' : ''">
                                            </div>
                                        </div>
                                        <div class="mt-3 flex justify-between text-sm font-semibold">
                                            <span>Requested deposit</span>
                                            <span>${{ invoiceDepositAmountPreview }} ({{ invoiceDepositPercentagePreview }}%)</span>
                                        </div>
                                        <div class="mt-1 flex justify-between text-xs text-slate-600">
                                            <span>Remaining balance</span>
                                            <span>${{ invoiceRemainingBalancePreview }}</span>
                                        </div>
                                    </div>
                                    <div v-if="bookingRecord.invoice?.installments?.length" class="border-t border-slate-300 pt-3">
                                        <div v-for="installment in bookingRecord.invoice.installments" :key="installment.id" class="flex items-center justify-between gap-3 py-1 text-xs text-slate-700">
                                            <span>
                                                {{ installment.label }} · {{ installment.due_date_label }}
                                                <span v-if="installment.payment_method_label"> · {{ installment.payment_method_label }}</span>
                                                <span v-if="installment.payment_reference"> · {{ installment.payment_reference }}</span>
                                            </span>
                                            <span class="flex items-center gap-2">
                                                <span>${{ installment.amount }} · {{ installment.status_label ?? statusLabel(installment.status) }}</span>
                                                <button
                                                    v-if="installment.status !== 'paid'"
                                                    type="button"
                                                    class="rounded border border-blue-300 bg-white px-2 py-1 text-[11px] font-semibold text-blue-700 transition hover:bg-blue-50"
                                                    @click="startManualPayment(installment)"
                                                >
                                                    Record payment
                                                </button>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <div v-if="showManualPaymentEditor" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur-sm" @click.self="cancelManualPayment">
                    <div class="w-full max-w-lg overflow-hidden rounded-xl border border-slate-300 bg-white text-slate-950 shadow-2xl shadow-black/30">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Record payment</p>
                                <p class="mt-1 text-sm font-semibold text-slate-950">
                                    {{ selectedPaymentInstallment?.label }} · ${{ selectedPaymentInstallment?.amount }}
                                </p>
                            </div>
                            <button type="button" class="rounded border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50" @click="cancelManualPayment">Close</button>
                        </div>

                        <form class="space-y-3 p-4" novalidate @submit.prevent="saveManualPayment">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-800">Payment method</label>
                                <select v-model="manualPaymentForm.payment_method" class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm text-slate-950 outline-none focus:border-blue-500" :class="firstError(manualPaymentValidationErrors, 'payment_method') ? 'border-red-500' : ''">
                                    <option value="bank_transfer">Bank transfer</option>
                                    <option value="cash">Cash</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-800">Payment date</label>
                                <input v-model="manualPaymentForm.paid_at" type="date" class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm text-slate-950 outline-none focus:border-blue-500" :class="firstError(manualPaymentValidationErrors, 'paid_at') ? 'border-red-500' : ''" @click="openDatePicker">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-800">Reference</label>
                                <input v-model="manualPaymentForm.payment_reference" type="text" class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm text-slate-950 outline-none focus:border-blue-500" placeholder="Bank receipt, transfer ID, or note">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-800">Notes</label>
                                <textarea v-model="manualPaymentForm.payment_notes" rows="3" class="w-full rounded border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-950 outline-none focus:border-blue-500" />
                            </div>
                            <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
                                <button type="button" class="rounded border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50" @click="cancelManualPayment">Cancel</button>
                                <button type="submit" class="rounded bg-blue-700 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                                    {{ saving ? 'Recording...' : 'Record payment' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <ConfirmDialog
        :open="showDeleteConfirm"
        :title="pendingDelete?.kind === 'document' ? 'Delete document?' : pendingDelete?.kind === 'contact' ? 'Delete contact?' : 'Delete task?'"
        :message="`Are you sure you want to delete the record ${pendingDelete?.label || 'this record'}?`"
        :confirm-label="pendingDelete?.kind === 'document' ? 'Delete document' : pendingDelete?.kind === 'contact' ? 'Delete contact' : 'Delete task'"
        :loading="saving"
        @cancel="cancelDelete"
        @confirm="confirmDelete"
    />
    </template>
</template>
