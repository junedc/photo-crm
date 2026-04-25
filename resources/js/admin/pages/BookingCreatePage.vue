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

const { saving, fieldErrors, submitForm } = useWorkspaceCrud();
const createWizardStep = ref(1);
const createErrors = ref({});
const createForm = ref({});
const createValidationErrors = computed(() => mergeFieldErrors(createErrors.value, fieldErrors.value));

const bookingKinds = computed(() => props.data.bookingKinds ?? []);
const packages = computed(() => props.data.packages ?? []);
const equipmentOptions = computed(() => props.data.equipmentOptions ?? []);
const addOnOptions = computed(() => props.data.addOnOptions ?? []);
const discountOptions = computed(() => props.data.discountOptions ?? []);

const selectedPackage = computed(() =>
    packages.value.find((entry) => String(entry.id) === String(createForm.value.package_id ?? '')) ?? null,
);
const selectedPackageHourlyPrices = computed(() => selectedPackage.value?.hourly_prices ?? []);
const selectedHourlyPrice = computed(() =>
    selectedPackageHourlyPrices.value.find((entry) => String(entry.id) === String(createForm.value.package_hourly_price_id ?? '')) ?? null,
);
const selectedEquipment = computed(() =>
    equipmentOptions.value.filter((item) => (createForm.value.equipment_ids ?? []).includes(item.id)),
);
const selectedAddOns = computed(() =>
    addOnOptions.value.filter((item) => (createForm.value.add_on_ids ?? []).includes(item.id)),
);
const clampDiscountPercentage = (value) => Math.min(100, Math.max(0, Number(value) || 0));
const clampDiscountAmount = (value) => Math.max(0, Number(value) || 0);
const formatMoney = (value) => Number(value || 0).toFixed(2);
const itemDiscountTypeMapKey = (selectionType) => (
    selectionType === 'equipment' ? 'equipment_discount_types' : 'add_on_discount_types'
);
const itemDiscountValueMapKey = (selectionType) => (
    selectionType === 'equipment' ? 'equipment_discount_values' : 'add_on_discount_values'
);
const itemDiscountType = (item) => (
    createForm.value[itemDiscountTypeMapKey(item.selection_type)]?.[String(item.id)] ?? 'percentage'
);
const itemDiscountValue = (item) => (
    createForm.value[itemDiscountValueMapKey(item.selection_type)]?.[String(item.id)] ?? '0'
);
const applyDiscount = (amount, discountType, discountValue) => {
    const numericAmount = Number(amount || 0);

    if (discountType === 'amount') {
        return Math.max(0, numericAmount - clampDiscountAmount(discountValue));
    }

    return numericAmount * (1 - (clampDiscountPercentage(discountValue) / 100));
};
const itemFinalPrice = (item) => formatMoney(applyDiscount(item.price_label, itemDiscountType(item), itemDiscountValue(item)));
const combinedOptionalItems = computed(() => [
    ...equipmentOptions.value.map((item) => ({
        ...item,
        selection_type: 'equipment',
        selection_key: 'equipment_ids',
        type_label: 'Equipment',
        details_label: item.category || 'Equipment',
        price_label: item.daily_rate,
        selected: (createForm.value.equipment_ids ?? []).includes(item.id),
    })),
    ...addOnOptions.value.map((item) => ({
        ...item,
        selection_type: 'add_on',
        selection_key: 'add_on_ids',
        type_label: 'Add-On',
        details_label: addOnSummary(item) || 'Add-On',
        price_label: item.price,
        selected: (createForm.value.add_on_ids ?? []).includes(item.id),
    })),
]);
const availableDiscountOptions = computed(() => {
    const packageId = Number(createForm.value.package_id ?? 0);
    const equipmentIds = new Set((createForm.value.equipment_ids ?? []).map((id) => Number(id)));

    return discountOptions.value.filter((discount) => {
        const packageMatch = discount.package_ids?.includes(packageId);
        const equipmentMatch = (discount.equipment_ids ?? []).some((id) => equipmentIds.has(Number(id)));

        return packageMatch || equipmentMatch;
    });
});
const selectedDiscount = computed(() =>
    availableDiscountOptions.value.find((entry) => String(entry.id) === String(createForm.value.discount_id ?? '')) ?? null,
);

const bookingKindLabelMap = {
    customer: 'Customer Booking',
    market_stall: 'Market Stall',
    sponsored: 'Sponsored',
};

const isEntryBooking = computed(() => createForm.value.booking_kind === 'market_stall' || createForm.value.booking_kind === 'sponsored');
const bookingKindLabel = (kind) => bookingKindLabelMap[kind] ?? 'Customer Booking';
const packageLabel = (entry) => `${entry.name} - $${entry.display_price}`;
const addOnSummary = (addOn) => [addOn.product_code, addOn.duration].filter(Boolean).join(' - ');
const setItemDiscountType = (item, value) => {
    const key = itemDiscountTypeMapKey(item.selection_type);
    const normalizedValue = value === 'amount' ? 'amount' : 'percentage';

    createForm.value[key] = {
        ...(createForm.value[key] ?? {}),
        [String(item.id)]: normalizedValue,
    };
};
const setItemDiscountValue = (item, value) => {
    const key = itemDiscountValueMapKey(item.selection_type);
    const normalizedValue = itemDiscountType(item) === 'amount'
        ? String(clampDiscountAmount(value).toFixed(2))
        : String(clampDiscountPercentage(value).toFixed(2));

    createForm.value[key] = {
        ...(createForm.value[key] ?? {}),
        [String(item.id)]: normalizedValue,
    };
};
const clearItemDiscountValue = (selectionType, id) => {
    const valueKey = itemDiscountValueMapKey(selectionType);
    const typeKey = itemDiscountTypeMapKey(selectionType);
    const currentValues = { ...(createForm.value[valueKey] ?? {}) };
    const currentTypes = { ...(createForm.value[typeKey] ?? {}) };

    delete currentValues[String(id)];
    delete currentTypes[String(id)];
    createForm.value[valueKey] = currentValues;
    createForm.value[typeKey] = currentTypes;
};
const itemDiscountLabel = (selectionType, id) => {
    const type = createForm.value[itemDiscountTypeMapKey(selectionType)]?.[String(id)] ?? 'percentage';
    const value = createForm.value[itemDiscountValueMapKey(selectionType)]?.[String(id)] ?? '0';

    return type === 'amount' ? `$${formatMoney(value)}` : `${formatMoney(value)}%`;
};
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
const createWizardSteps = [
    { number: 1, title: 'Customer', description: 'Details and booking type' },
    { number: 2, title: 'Package', description: 'Package and pricing' },
    { number: 3, title: 'Add-Ons', description: 'Equipment and extras' },
    { number: 4, title: 'Location', description: 'Address and notes' },
    { number: 5, title: 'Summary', description: 'Review and create' },
];

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
        equipment_discount_types: {},
        equipment_discount_values: {},
        add_on_discount_types: {},
        add_on_discount_values: {},
    };
    createErrors.value = {};
    createWizardStep.value = 1;

    syncBookingKindDefaults();
    syncPackageTimingDefaults();
    syncEndTime();
};

const syncBookingKindDefaults = () => {
    if (isEntryBooking.value) {
        createForm.value.event_type = 'Others';
    } else if (!props.data.eventTypes?.includes(createForm.value.event_type)) {
        createForm.value.event_type = props.data.eventTypes?.[0] ?? 'Wedding';
    }
};

const syncPackageTimingDefaults = () => {
    if (!selectedPackage.value || !selectedPackageHourlyPrices.value.length) {
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
    const selectionType = key === 'equipment_ids' ? 'equipment' : 'add_on';

    if (values.has(id)) {
        values.delete(id);
        clearItemDiscountValue(selectionType, id);
    } else {
        values.add(id);
    }

    createForm.value[key] = [...values];
};

const syncAddressAutocompleteVisibility = () => {
    const isLocationStep = createWizardStep.value === 4;

    document.querySelectorAll('.pac-container').forEach((element) => {
        element.style.display = isLocationStep ? '' : 'none';
    });
};

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

watch(createWizardStep, () => {
    nextTick(() => {
        autoAttachGoogleAddressInputs();
        syncAddressAutocompleteVisibility();
    });
});

onMounted(() => {
    resetCreateForm();
    nextTick(() => {
        autoAttachGoogleAddressInputs();
        syncAddressAutocompleteVisibility();
    });
});

const createBooking = async () => {
    const errors = {};

    if (isBlank(createForm.value.package_id)) {
        errors.package_id = requiredMessage('Package');
    }

    const subjectName = isEntryBooking.value ? createForm.value.entry_name : createForm.value.customer_name;
    const subjectLabel = isEntryBooking.value ? 'Name' : 'Customer name';

    if (isBlank(subjectName)) {
        errors[isEntryBooking.value ? 'entry_name' : 'customer_name'] = requiredMessage(subjectLabel);
    }

    if (isEntryBooking.value && isBlank(createForm.value.customer_name)) {
        errors.customer_name = requiredMessage('Invoice contact name');
    }

    if (isBlank(createForm.value.customer_email)) {
        errors.customer_email = requiredMessage('Email');
    }

    if (isBlank(createForm.value.customer_phone)) {
        errors.customer_phone = requiredMessage('Phone');
    }

    if (isBlank(createForm.value.event_date)) {
        errors.event_date = requiredMessage('Event date');
    }

    if (isBlank(createForm.value.start_time)) {
        errors.start_time = requiredMessage('Start hour');
    }

    if (isBlank(createForm.value.total_hours)) {
        errors.total_hours = requiredMessage('Hour duration');
    }

    if (isBlank(createForm.value.end_time)) {
        errors.end_time = requiredMessage('End hour');
    }

    if (isBlank(createForm.value.event_location)) {
        errors.event_location = requiredMessage('Location');
    }

    createErrors.value = errors;

    if (hasFieldErrors(errors)) {
        return;
    }

    try {
        const record = await submitForm({
            url: props.data.bookingCreateUrl,
            method: 'post',
            data: { ...createForm.value },
        });

        window.location.href = record.show_url;
    } catch {}
};

const setCreateWizardStep = (step) => {
    const activeElement = document.activeElement;

    if (activeElement instanceof HTMLElement) {
        activeElement.blur();
    }

    createWizardStep.value = Math.min(Math.max(step, 1), createWizardSteps.length);
};

const validateCreateWizardStep = (step) => {
    const errors = {};
    const subjectField = isEntryBooking.value ? 'entry_name' : 'customer_name';
    const subjectLabel = isEntryBooking.value ? 'Name' : 'Customer name';

    if (step === 1) {
        if (isBlank(createForm.value[subjectField])) {
            errors[subjectField] = requiredMessage(subjectLabel);
        }

        if (isEntryBooking.value && isBlank(createForm.value.customer_name)) {
            errors.customer_name = requiredMessage('Invoice contact name');
        }

        if (isBlank(createForm.value.customer_email)) {
            errors.customer_email = requiredMessage('Email');
        }

        if (isBlank(createForm.value.customer_phone)) {
            errors.customer_phone = requiredMessage('Phone');
        }

        if (isBlank(createForm.value.event_date)) {
            errors.event_date = requiredMessage('Event date');
        }

        if (isBlank(createForm.value.start_time)) {
            errors.start_time = requiredMessage('Start hour');
        }

        if (isBlank(createForm.value.total_hours)) {
            errors.total_hours = requiredMessage('Hour duration');
        }

        if (isBlank(createForm.value.end_time)) {
            errors.end_time = requiredMessage('End hour');
        }

        if (!isEntryBooking.value && isBlank(createForm.value.event_type)) {
            errors.event_type = requiredMessage('Event type');
        }
    }

    if (step === 2 && isBlank(createForm.value.package_id)) {
        errors.package_id = requiredMessage('Package');
    }

    if (step === 4 && isBlank(createForm.value.event_location)) {
        errors.event_location = requiredMessage('Location');
    }

    createErrors.value = errors;

    const stepOneFields = isEntryBooking.value
        ? [subjectField, 'customer_name', 'customer_email', 'customer_phone', 'event_date', 'start_time', 'total_hours', 'end_time']
        : [subjectField, 'customer_email', 'customer_phone', 'event_date', 'start_time', 'total_hours', 'end_time', 'event_type'];

    const relevantFieldsByStep = {
        1: stepOneFields,
        2: ['package_id'],
        4: ['event_location'],
    };

    return !(relevantFieldsByStep[step] ?? []).some((field) => firstError(errors, field) || firstError(fieldErrors.value, field));
};

const goToNextCreateStep = () => {
    if (!validateCreateWizardStep(createWizardStep.value)) {
        return;
    }

    setCreateWizardStep(createWizardStep.value + 1);
};

const goToPreviousCreateStep = () => setCreateWizardStep(createWizardStep.value - 1);
const blockCreateSubmit = () => {};
</script>

<template>
    <section class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <div>
            <p class="text-[11px] uppercase tracking-[0.35em] text-rose-200">New Booking</p>
            <h2 class="mt-1 text-sm font-bold italic text-white">Create booking, stall, or sponsored entry</h2>
            <p class="mt-1 text-sm text-stone-300">
                <span class="text-stone-500">{{ createBookingSummaryLabel }}:</span>
                <span class="font-semibold text-white">{{ createBookingSummaryName }}</span>
            </p>
        </div>
        <a :href="data.routes.bookings" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">
            Back to list
        </a>
    </section>

    <form class="mt-4 space-y-5" novalidate @submit.prevent="blockCreateSubmit">
        <nav class="rounded-2xl border border-white/10 bg-white/[0.03] p-3" aria-label="Create booking steps">
            <div class="grid gap-2 sm:grid-cols-5">
                <button
                    v-for="step in createWizardSteps"
                    :key="step.number"
                    type="button"
                    class="rounded-xl border px-3 py-3 text-left transition"
                    :class="createWizardStep === step.number ? 'border-rose-300/40 bg-rose-300/10 text-white' : createWizardStep > step.number ? 'border-emerald-300/30 bg-emerald-300/10 text-emerald-100' : 'border-white/10 bg-white/[0.02] text-stone-300 hover:bg-white/5'"
                    @click="setCreateWizardStep(step.number)"
                >
                    <div class="flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-lg border text-[11px] font-semibold" :class="createWizardStep === step.number ? 'border-rose-200/50' : 'border-white/10'">{{ step.number }}</span>
                        <span class="text-sm font-semibold">{{ step.title }}</span>
                    </div>
                    <p class="mt-1 text-[11px] text-stone-400">{{ step.description }}</p>
                </button>
            </div>
        </nav>

        <section v-if="createWizardStep === 1" class="space-y-3 rounded-2xl border border-white/10 bg-white/[0.03] p-3.5">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Booking Type</p>
                <span class="text-stone-600">•</span>
                <h4 class="text-sm font-semibold text-white">{{ entryHeading }}</h4>
            </div>

            <div class="grid gap-2 sm:grid-cols-3">
                <button
                    v-for="kind in bookingKinds"
                    :key="kind"
                    type="button"
                    class="rounded-lg border px-3 py-2 text-sm font-medium transition"
                    :class="createForm.booking_kind === kind ? 'border-rose-300/40 bg-rose-300/10 text-white' : 'border-white/10 text-stone-300 hover:bg-white/5'"
                    @click="createForm.booking_kind = kind"
                >
                    {{ bookingKindLabel(kind) }}
                </button>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">{{ isEntryBooking ? 'Name' : 'Full Name' }}</label>
                    <input v-if="isEntryBooking" v-model="createForm.entry_name" type="text" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'entry_name') ? 'border-rose-300/60' : ''">
                    <input v-else v-model="createForm.customer_name" type="text" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'customer_name') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(createValidationErrors, isEntryBooking ? 'entry_name' : 'customer_name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(createValidationErrors, isEntryBooking ? 'entry_name' : 'customer_name') }}</p>
                </div>

                <div v-if="isEntryBooking" class="sm:col-span-2">
                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Description</label>
                    <textarea v-model="createForm.entry_description" rows="2" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-rose-300/50" />
                </div>

                <template v-if="!isEntryBooking">
                    <div>
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Email</label>
                        <input v-model="createForm.customer_email" type="email" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'customer_email') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(createValidationErrors, 'customer_email')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(createValidationErrors, 'customer_email') }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Phone</label>
                        <input v-model="createForm.customer_phone" type="text" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'customer_phone') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(createValidationErrors, 'customer_phone')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(createValidationErrors, 'customer_phone') }}</p>
                    </div>
                </template>

                <div v-if="isEntryBooking" class="sm:col-span-2 xl:col-span-4 rounded-lg border border-white/10 bg-white/[0.03] p-3">
                    <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Invoice Contact</p>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Invoice Contact Name</label>
                            <input v-model="createForm.customer_name" type="text" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'customer_name') ? 'border-rose-300/60' : ''">
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Invoice Email</label>
                            <input v-model="createForm.customer_email" type="email" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'customer_email') ? 'border-rose-300/60' : ''">
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Invoice Phone</label>
                            <input v-model="createForm.customer_phone" type="text" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'customer_phone') ? 'border-rose-300/60' : ''">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Event Date</label>
                    <input v-model="createForm.event_date" type="date" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'event_date') ? 'border-rose-300/60' : ''" @click="openDatePicker" @keydown.prevent>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Start Hour</label>
                    <input v-model="createForm.start_time" type="time" step="1800" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'start_time') ? 'border-rose-300/60' : ''">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Hour Duration</label>
                    <input v-model="createForm.total_hours" :readonly="selectedPackageHourlyPrices.length > 0" type="number" min="0.5" step="0.5" class="w-full rounded-lg border border-white/10 px-3 py-2 text-sm text-white outline-none transition" :class="[selectedPackageHourlyPrices.length > 0 ? 'bg-slate-900/60' : 'bg-slate-950/70 focus:border-rose-300/50', firstError(createValidationErrors, 'total_hours') ? 'border-rose-300/60' : '']">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">End Hour</label>
                    <input :value="createForm.end_time" readonly type="text" class="w-full rounded-lg border border-white/10 bg-slate-900/60 px-3 py-2 text-sm text-white outline-none" :class="firstError(createValidationErrors, 'end_time') ? 'border-rose-300/60' : ''">
                </div>
                <div v-if="!isEntryBooking" class="sm:col-span-2 xl:col-span-4">
                    <label class="mb-1 block text-[11px] font-medium uppercase tracking-[0.2em] text-stone-400">Event Type</label>
                    <select v-model="createForm.event_type" class="w-full rounded-lg border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'event_type') ? 'border-rose-300/60' : ''">
                        <option v-for="eventType in data.eventTypes" :key="eventType" :value="eventType">{{ eventType }}</option>
                    </select>
                </div>
            </div>
        </section>

        <section v-else-if="createWizardStep === 2" class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <div class="flex flex-wrap items-center gap-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Choose Package</p>
                <span class="text-stone-600">•</span>
                <h4 class="text-sm font-semibold text-white">Package and pricing</h4>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Package</label>
                <select v-model="createForm.package_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'package_id') ? 'border-rose-300/60' : ''">
                    <option disabled value="">Select a package</option>
                    <option v-for="entry in packages" :key="entry.id" :value="String(entry.id)">{{ packageLabel(entry) }}</option>
                </select>
            </div>
            <div v-if="selectedPackage" class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                <div v-if="selectedPackageHourlyPrices.length">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Package Timing And Price</label>
                    <select v-model="createForm.package_hourly_price_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50">
                        <option v-for="option in selectedPackageHourlyPrices" :key="option.id" :value="String(option.id)">
                            {{ Number(option.hours).toFixed(2) }} hrs - ${{ option.price }}
                        </option>
                    </select>
                </div>
                <div class="mt-4">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Discount</label>
                    <select v-model="createForm.discount_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50">
                        <option value="">No discount</option>
                        <option v-for="discount in availableDiscountOptions" :key="discount.id" :value="String(discount.id)">
                            {{ discountLabel(discount) }} - {{ discountValueLabel(discount) }}
                        </option>
                    </select>
                </div>
            </div>
        </section>

        <section v-else-if="createWizardStep === 3" class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <div class="flex flex-wrap items-center gap-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Optional Extras</p>
                <span class="text-stone-600">-</span>
                <h4 class="text-sm font-semibold text-white">Equipment and add-ons</h4>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Assign Items</p>
                    <span class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] text-stone-300">{{ (createForm.equipment_ids?.length ?? 0) + (createForm.add_on_ids?.length ?? 0) }}</span>
                </div>
                <div class="mt-4 overflow-hidden rounded-xl border border-white/10">
                    <div class="grid grid-cols-[2.2rem_6.5rem_minmax(0,1.2fr)_9rem_6rem_9.5rem_7rem] gap-3 bg-white/[0.04] px-3 py-2 text-[11px] uppercase tracking-[0.18em] text-stone-500">
                        <span></span>
                        <span>Type</span>
                        <span>Item</span>
                        <span>Details</span>
                        <span>Price</span>
                        <span>Discount</span>
                        <span>Final</span>
                    </div>
                    <button
                        v-for="item in combinedOptionalItems"
                        :key="`${item.selection_type}-${item.id}`"
                        type="button"
                        class="grid w-full grid-cols-[2.2rem_6.5rem_minmax(0,1.2fr)_9rem_6rem_9.5rem_7rem] gap-3 border-t border-white/10 px-3 py-2.5 text-left text-sm transition"
                        :class="item.selected ? (item.selection_type === 'equipment' ? 'bg-cyan-300/10 text-white' : 'bg-rose-300/10 text-white') : 'text-stone-300 hover:bg-white/[0.03]'"
                        @click="toggleMultiSelect(item.selection_key, item.id)"
                    >
                        <span class="flex items-center justify-center">
                            <span class="flex h-5 w-5 items-center justify-center rounded-md border text-[11px] font-semibold" :class="item.selected ? (item.selection_type === 'equipment' ? 'border-cyan-300/40 bg-cyan-300/20 text-cyan-100' : 'border-rose-300/40 bg-rose-300/20 text-rose-100') : 'border-white/10 text-stone-500'">
                                {{ item.selected ? '✓' : '' }}
                            </span>
                        </span>
                        <span>{{ item.type_label }}</span>
                        <span class="truncate font-medium">{{ item.name }}</span>
                        <span>{{ item.details_label }}</span>
                        <span>${{ item.price_label }}</span>
                        <span class="flex gap-1">
                            <select
                                :value="itemDiscountType(item)"
                                class="w-[4.2rem] rounded-lg border border-white/10 bg-slate-950/70 px-2 py-1 text-xs text-white outline-none transition focus:border-rose-300/50 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="!item.selected"
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
                                :disabled="!item.selected"
                                @click.stop
                                @keydown.stop
                                @input="setItemDiscountValue(item, $event.target.value)"
                            >
                        </span>
                        <span>${{ itemFinalPrice(item) }}</span>
                    </button>
                    <p v-if="!combinedOptionalItems.length" class="px-3 py-3 text-sm text-stone-400">No equipment or add-ons available.</p>
                </div>
            </div>
        </section>

        <section v-else-if="createWizardStep === 4" class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <div class="flex flex-wrap items-center gap-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Location</p>
                <span class="text-stone-600">•</span>
                <h4 class="text-sm font-semibold text-white">Event address and notes</h4>
            </div>
            <div class="mb-14">
                <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Location</label>
                <input v-model="createForm.event_location" data-google-address="true" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50" :class="firstError(createValidationErrors, 'event_location') ? 'border-rose-300/60' : ''">
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Notes</label>
                <textarea v-model="createForm.notes" rows="4" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-rose-300/50" />
            </div>
        </section>

        <section v-else class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <div class="flex flex-wrap items-center gap-3">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Summary</p>
                <span class="text-stone-600">•</span>
                <h4 class="text-sm font-semibold text-white">Review before creating</h4>
            </div>
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">{{ createBookingSummaryLabel }}</p>
                    <div class="mt-3 space-y-2 text-sm text-stone-300">
                        <p><span class="text-stone-500">Name:</span> <span class="text-white">{{ createBookingSummaryName }}</span></p>
                        <p><span class="text-stone-500">Email:</span> <span class="text-white">{{ createForm.customer_email || 'Not entered' }}</span></p>
                        <p><span class="text-stone-500">Phone:</span> <span class="text-white">{{ createForm.customer_phone || 'Not entered' }}</span></p>
                        <p><span class="text-stone-500">Type:</span> <span class="text-white">{{ bookingKindLabel(createForm.booking_kind) }}</span></p>
                    </div>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Event</p>
                    <div class="mt-3 space-y-2 text-sm text-stone-300">
                        <p><span class="text-stone-500">Date:</span> <span class="text-white">{{ createForm.event_date || 'Not entered' }}</span></p>
                        <p><span class="text-stone-500">Start:</span> <span class="text-white">{{ createForm.start_time || 'Not entered' }}</span></p>
                        <p><span class="text-stone-500">End:</span> <span class="text-white">{{ createForm.end_time || 'Not entered' }}</span></p>
                        <p><span class="text-stone-500">Duration:</span> <span class="text-white">{{ createForm.total_hours || '0.00' }} hrs</span></p>
                    </div>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Package</p>
                    <div class="mt-3 space-y-2 text-sm text-stone-300">
                        <p><span class="text-stone-500">Selected:</span> <span class="text-white">{{ selectedPackage?.name || 'No package selected' }}</span></p>
                        <p v-if="selectedHourlyPrice"><span class="text-stone-500">Timing:</span> <span class="text-white">{{ Number(selectedHourlyPrice.hours).toFixed(2) }} hrs - ${{ selectedHourlyPrice.price }}</span></p>
                        <p><span class="text-stone-500">Discount:</span> <span class="text-white">{{ selectedDiscount ? `${discountLabel(selectedDiscount)} - ${discountValueLabel(selectedDiscount)}` : 'No discount' }}</span></p>
                    </div>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Location</p>
                    <div class="mt-3 space-y-2 text-sm text-stone-300">
                        <p class="text-white">{{ createForm.event_location || 'Not entered' }}</p>
                        <p><span class="text-stone-500">Notes:</span> <span class="text-white">{{ createForm.notes || 'No notes added' }}</span></p>
                    </div>
                </div>
            </div>
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Equipment</p>
                        <span class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] text-stone-300">{{ selectedEquipment.length }}</span>
                    </div>
                    <div class="mt-3 overflow-hidden rounded-xl border border-white/10">
                        <div class="grid grid-cols-[minmax(0,1fr)_8rem_6rem_7rem_7rem] gap-3 bg-white/[0.04] px-3 py-2 text-[11px] uppercase tracking-[0.18em] text-stone-500">
                            <span>Item</span>
                            <span>Category</span>
                            <span>Price</span>
                            <span>Discount</span>
                            <span>Final</span>
                        </div>
                        <div v-if="selectedEquipment.length">
                            <div v-for="item in selectedEquipment" :key="`summary-equipment-${item.id}`" class="grid grid-cols-[minmax(0,1fr)_8rem_6rem_7rem_7rem] gap-3 border-t border-white/10 px-3 py-2 text-sm text-stone-300">
                                <span class="truncate text-white">{{ item.name }}</span>
                                <span>{{ item.category || 'Equipment' }}</span>
                                <span>${{ item.daily_rate }}</span>
                                <span>{{ itemDiscountLabel('equipment', item.id) }}</span>
                                <span>${{ formatMoney(applyDiscount(item.daily_rate, createForm.equipment_discount_types?.[String(item.id)] ?? 'percentage', createForm.equipment_discount_values?.[String(item.id)] ?? 0)) }}</span>
                            </div>
                        </div>
                        <p v-else class="px-3 py-3 text-sm text-stone-400">No equipment selected.</p>
                    </div>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-[11px] uppercase tracking-[0.2em] text-stone-500">Add-Ons</p>
                        <span class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] text-stone-300">{{ selectedAddOns.length }}</span>
                    </div>
                    <div class="mt-3 overflow-hidden rounded-xl border border-white/10">
                        <div class="grid grid-cols-[minmax(0,1fr)_8rem_6rem_7rem_7rem] gap-3 bg-white/[0.04] px-3 py-2 text-[11px] uppercase tracking-[0.18em] text-stone-500">
                            <span>Item</span>
                            <span>Details</span>
                            <span>Price</span>
                            <span>Discount</span>
                            <span>Final</span>
                        </div>
                        <div v-if="selectedAddOns.length">
                            <div v-for="item in selectedAddOns" :key="`summary-addon-${item.id}`" class="grid grid-cols-[minmax(0,1fr)_8rem_6rem_7rem_7rem] gap-3 border-t border-white/10 px-3 py-2 text-sm text-stone-300">
                                <span class="truncate text-white">{{ item.name }}</span>
                                <span>{{ addOnSummary(item) || 'Add-On' }}</span>
                                <span>${{ item.price }}</span>
                                <span>{{ itemDiscountLabel('add_on', item.id) }}</span>
                                <span>${{ formatMoney(applyDiscount(item.price, createForm.add_on_discount_types?.[String(item.id)] ?? 'percentage', createForm.add_on_discount_values?.[String(item.id)] ?? 0)) }}</span>
                            </div>
                        </div>
                        <p v-else class="px-3 py-3 text-sm text-stone-400">No add-ons selected.</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="sticky bottom-0 z-20 flex items-center justify-between gap-3 rounded-2xl border border-white/10 bg-[#132035] px-5 py-4 shadow-[0_-10px_24px_-18px_rgba(0,0,0,0.7)]">
            <p v-if="!packages.length" class="text-sm text-amber-200">Add and activate a package first so admins can create bookings.</p>
            <span v-else class="text-sm text-stone-400">Step {{ createWizardStep }} of {{ createWizardSteps.length }}</span>
            <div class="flex items-center gap-3">
                <a v-if="createWizardStep === 1" :href="data.routes.bookings" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/5">
                    Cancel
                </a>
                <button v-else type="button" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/5" @click="goToPreviousCreateStep">
                    Back
                </button>
                <button v-if="createWizardStep < createWizardSteps.length" type="button" class="rounded-xl bg-rose-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-rose-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="!packages.length" @click="goToNextCreateStep">
                    Continue
                </button>
                <button v-else type="button" class="rounded-xl bg-rose-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-rose-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving || !packages.length" @click="createBooking">
                    {{ saving ? 'Saving...' : 'Save' }}
                </button>
            </div>
        </div>
    </form>
</template>
