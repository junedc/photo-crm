<script setup>
import { computed, ref } from 'vue';
import { VueCal } from 'vue-cal';
import 'vue-cal/style';
import { formatDateLabel } from '../../dateFormatter';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const calendar = ref(null);
const today = new Date();
const bookings = computed(() => props.data.bookings ?? []);
const viewOptions = [
    { value: 'day', label: 'Daily' },
    { value: 'week', label: 'Weekly' },
    { value: 'month', label: 'Monthly' },
    { value: 'year', label: 'Yearly' },
];
const monthOptions = [
    { value: 0, label: 'January' },
    { value: 1, label: 'February' },
    { value: 2, label: 'March' },
    { value: 3, label: 'April' },
    { value: 4, label: 'May' },
    { value: 5, label: 'June' },
    { value: 6, label: 'July' },
    { value: 7, label: 'August' },
    { value: 8, label: 'September' },
    { value: 9, label: 'October' },
    { value: 10, label: 'November' },
    { value: 11, label: 'December' },
];
const parseMonthFromUrl = () => {
    const url = new URL(window.location.href);
    const month = url.searchParams.get('month');

    if (!month || !/^\d{4}-\d{2}-\d{2}$/.test(month)) {
        return new Date(today.getFullYear(), today.getMonth(), 1);
    }

    const [year, monthNumber] = month.split('-').map(Number);

    if (!year || !monthNumber) {
        return new Date(today.getFullYear(), today.getMonth(), 1);
    }

    return new Date(year, monthNumber - 1, 1);
};
const visibleMonth = ref(parseMonthFromUrl());
const parseViewFromUrl = () => {
    const url = new URL(window.location.href);
    const view = url.searchParams.get('view');

    if (viewOptions.some((option) => option.value === view)) {
        return view;
    }

    return 'month';
};
const selectedView = ref(parseViewFromUrl());
const isLightTheme = computed(() => props.data.tenant?.theme === 'light');
const useDarkCalendar = computed(() => !isLightTheme.value);
const tenantTimezone = computed(() => props.data.tenant?.timezone ?? 'UTC');

const monthLabel = computed(() =>
    formatDateLabel(visibleMonth.value, tenantTimezone.value, {
        month: 'long',
        year: 'numeric',
    }),
);
const showTimeGrid = computed(() => ['day', 'week'].includes(selectedView.value));

const bookingCountForMonth = computed(() =>
    bookings.value.filter((booking) => {
        if (!booking.event_date) {
            return false;
        }

        const [year, month] = booking.event_date.split('-').map(Number);

        return year === visibleMonth.value.getFullYear() && month === visibleMonth.value.getMonth() + 1;
    }).length,
);
const yearOptions = computed(() => {
    const years = bookings.value
        .map((booking) => Number((booking.event_date || '').slice(0, 4)))
        .filter((year) => Number.isInteger(year) && year > 0);
    const minYear = years.length ? Math.min(...years, today.getFullYear() - 10) : today.getFullYear() - 10;
    const maxYear = years.length ? Math.max(...years, today.getFullYear() + 5) : today.getFullYear() + 5;
    const options = [];

    for (let year = minYear; year <= maxYear; year += 1) {
        options.push(year);
    }

    return options;
});
const selectedMonthValue = computed({
    get: () => visibleMonth.value.getMonth(),
    set: (value) => {
        visibleMonth.value = new Date(visibleMonth.value.getFullYear(), Number(value), 1);
        syncCalendarUrl();
    },
});
const selectedYearValue = computed({
    get: () => visibleMonth.value.getFullYear(),
    set: (value) => {
        visibleMonth.value = new Date(Number(value), visibleMonth.value.getMonth(), 1);
        syncCalendarUrl();
    },
});

const statusLabel = (status) => (status || '').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
const calendarBadge = (booking) => {
    if (booking.booking_kind === 'market_stall' || booking.booking_kind === 'sponsored') {
        return booking.booking_kind_label;
    }

    return statusLabel(booking.status);
};

const eventTime = (date, time, fallbackHour) => {
    const safeTime = time && /^\d{2}:\d{2}$/.test(time) ? time : fallbackHour;
    return `${date} ${safeTime}`;
};

const calendarEvents = computed(() =>
    bookings.value
        .filter((booking) => booking.event_date)
        .map((booking) => ({
            start: eventTime(booking.event_date, booking.start_time, '09:00'),
            end: eventTime(booking.event_date, booking.end_time, '10:00'),
            title: booking.display_name || booking.customer_name,
            content: calendarBadge(booking),
            class: `booking-status-${booking.status ?? 'pending'}`,
            id: String(booking.id),
            allDay: false,
            booking,
        })),
);

const previousMonth = () => {
    visibleMonth.value = new Date(visibleMonth.value.getFullYear(), visibleMonth.value.getMonth() - 1, 1);
    syncCalendarUrl();
};

const nextMonth = () => {
    visibleMonth.value = new Date(visibleMonth.value.getFullYear(), visibleMonth.value.getMonth() + 1, 1);
    syncCalendarUrl();
};

const resetToToday = () => {
    visibleMonth.value = new Date(today.getFullYear(), today.getMonth(), 1);
    syncCalendarUrl();
};

const handleViewDateUpdate = (value) => {
    visibleMonth.value = new Date(value.getFullYear(), value.getMonth(), 1);
    syncCalendarUrl();
};

const syncCalendarUrl = () => {
    const url = new URL(window.location.href);
    const month = `${visibleMonth.value.getFullYear()}-${String(visibleMonth.value.getMonth() + 1).padStart(2, '0')}-01`;
    url.searchParams.set('month', month);
    url.searchParams.set('view', selectedView.value);
    window.history.replaceState({}, '', url.toString());
};

const openBooking = (booking) => {
    if (!booking?.show_url) {
        return;
    }

    const returnUrl = new URL(window.location.href);
    returnUrl.searchParams.set('month', `${visibleMonth.value.getFullYear()}-${String(visibleMonth.value.getMonth() + 1).padStart(2, '0')}-01`);
    returnUrl.searchParams.set('view', selectedView.value);
    const bookingUrl = new URL(booking.show_url, window.location.origin);
    bookingUrl.searchParams.set('return_to', returnUrl.toString());
    window.location.href = bookingUrl.toString();
};
</script>

<template>
    <section class="rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-sm text-stone-300">
            <span class="text-[11px] uppercase tracking-[0.35em] text-cyan-200">Calendar Workspace</span>
            <span class="mx-2 text-stone-500">•</span>
            <span class="font-semibold tracking-tight text-white">Monthly booking schedule</span>
            <span class="mx-2 text-stone-500">•</span>
            Review all bookings in a true calendar view with the customer name and booking status on each scheduled event.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
        <div class="flex flex-col gap-4 border-b border-white/10 pb-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Visible Month</p>
                <h3 class="mt-1 text-sm font-semibold italic text-white">{{ monthLabel }}</h3>
                <p class="mt-1 text-sm text-stone-400">{{ bookingCountForMonth }} bookings in this month</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="view in viewOptions"
                        :key="view.value"
                        type="button"
                        class="rounded-xl border px-4 py-2 text-sm font-medium transition"
                        :class="selectedView === view.value ? 'border-cyan-300/40 bg-cyan-300/10 text-white' : 'border-white/10 text-stone-200 hover:bg-white/5'"
                        @click="selectedView = view.value; syncCalendarUrl()"
                    >
                        {{ view.label }}
                    </button>
                </div>
                <select v-model="selectedMonthValue" class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-2 text-sm font-medium text-stone-200 outline-none transition focus:border-cyan-300/50">
                    <option v-for="month in monthOptions" :key="month.value" :value="month.value">{{ month.label }}</option>
                </select>
                <select v-model="selectedYearValue" class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-2 text-sm font-medium text-stone-200 outline-none transition focus:border-cyan-300/50">
                    <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
                </select>
                <button type="button" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-medium text-stone-200 transition hover:bg-white/5" @click="previousMonth">
                    Previous
                </button>
                <button type="button" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-medium text-stone-200 transition hover:bg-white/5" @click="resetToToday">
                    Today
                </button>
                <button type="button" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-medium text-stone-200 transition hover:bg-white/5" @click="nextMonth">
                    Next
                </button>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <span class="rounded-full border border-amber-300/30 bg-amber-300/10 px-3 py-1 text-xs font-medium text-amber-100">Pending</span>
            <span class="rounded-full border border-emerald-300/30 bg-emerald-300/10 px-3 py-1 text-xs font-medium text-emerald-100">Confirmed</span>
            <span class="rounded-full border border-cyan-300/30 bg-cyan-300/10 px-3 py-1 text-xs font-medium text-cyan-100">Completed</span>
            <span class="rounded-full border border-rose-300/30 bg-rose-300/10 px-3 py-1 text-xs font-medium text-rose-100">Cancelled</span>
        </div>

        <div
            class="calendar-shell mt-4 overflow-hidden rounded-2xl border border-white/10 bg-slate-950/60 p-3"
            :class="isLightTheme ? 'calendar-shell-light' : 'calendar-shell-dark'"
        >
            <VueCal
                ref="calendar"
                class="memoshot-calendar"
                locale="en-gb"
                :dark="useDarkCalendar"
                :view="selectedView"
                :views="['day', 'week', 'month', 'year']"
                :view-date="visibleMonth"
                :events="calendarEvents"
                :time="showTimeGrid"
                :title-bar="false"
                :views-bar="false"
                :today-button="false"
                :event-count="false"
                events-on-month-view="short"
                @update:view-date="handleViewDateUpdate"
            >
                <template #event="{ event }">
                    <button
                        type="button"
                        class="booking-chip block w-full rounded-lg border px-2.5 py-2 text-left shadow-sm transition hover:brightness-105"
                        :class="event.class"
                        @click.stop="openBooking(event.booking)"
                    >
                        <span class="block truncate text-xs font-semibold leading-4">
                            {{ event.title }}
                        </span>
                        <span class="mt-0.5 block truncate text-[11px] font-medium leading-3 opacity-90">
                            {{ event.content }}
                        </span>
                    </button>
                </template>
            </VueCal>
        </div>
    </section>
</template>

<style scoped>
.calendar-shell :deep(.vuecal) {
    --vuecal-primary-color: #67e8f9;
    --vuecal-secondary-color: #0f172a;
    --vuecal-base-border-color: rgba(255, 255, 255, 0.08);
    --vuecal-cell-border-color: rgba(255, 255, 255, 0.08);
    --vuecal-bg-color: transparent;
    --vuecal-text-color: #e7e5e4;
    --vuecal-header-color: #0b1220;
    --vuecal-header-text-color: #cbd5e1;
    --vuecal-today-bg-color: rgba(103, 232, 249, 0.12);
    --vuecal-today-color: #cffafe;
    --vuecal-event-border-radius: 10px;
    background: transparent;
    color: #e7e5e4;
}

.calendar-shell :deep(.vuecal__weekdays-headings),
.calendar-shell :deep(.vuecal__heading) {
    background: rgba(15, 23, 42, 0.9);
}

.calendar-shell :deep(.vuecal__cell) {
    background: rgba(15, 23, 42, 0.45);
    min-height: 140px;
}

.calendar-shell :deep(.vuecal__cell--out-of-scope) {
    background: rgba(15, 23, 42, 0.2);
    opacity: 0.55;
}

.calendar-shell :deep(.vuecal__cell-date) {
    color: #e7e5e4;
    font-weight: 600;
}

.calendar-shell :deep(.vuecal__event) {
    background: transparent;
    border: 0;
    box-shadow: none;
    padding: 0;
}

.booking-chip.booking-status-pending {
    border-color: rgba(252, 211, 77, 0.35);
    background: rgba(252, 211, 77, 0.14);
    color: #fef3c7;
}

.booking-chip.booking-status-confirmed {
    border-color: rgba(110, 231, 183, 0.35);
    background: rgba(110, 231, 183, 0.14);
    color: #d1fae5;
}

.booking-chip.booking-status-completed {
    border-color: rgba(103, 232, 249, 0.35);
    background: rgba(103, 232, 249, 0.14);
    color: #cffafe;
}

.booking-chip.booking-status-cancelled {
    border-color: rgba(251, 113, 133, 0.35);
    background: rgba(251, 113, 133, 0.14);
    color: #ffe4e6;
}

:global([data-theme='light']) .calendar-shell {
    background: #ffffff !important;
    border-color: rgba(204, 197, 185, 0.72) !important;
}

:global([data-theme='light']) .calendar-shell-light,
.calendar-shell-light {
    background: #ffffff !important;
    border-color: rgba(204, 197, 185, 0.72) !important;
}

:global([data-theme='light']) .calendar-shell :deep(.vuecal) {
    --vuecal-primary-color: #51cbce;
    --vuecal-secondary-color: #ffffff;
    --vuecal-base-border-color: rgba(204, 197, 185, 0.55);
    --vuecal-cell-border-color: rgba(204, 197, 185, 0.45);
    --vuecal-bg-color: #ffffff;
    --vuecal-text-color: #252422;
    --vuecal-header-color: #ede8df;
    --vuecal-header-text-color: #252422;
    --vuecal-today-bg-color: rgba(81, 203, 206, 0.18);
    --vuecal-today-color: #252422;
    color: #252422;
}

:global([data-theme='light']) .calendar-shell-light :deep(.vuecal),
.calendar-shell-light :deep(.vuecal) {
    --vuecal-primary-color: #51cbce;
    --vuecal-secondary-color: #ffffff;
    --vuecal-base-color: #252422;
    --vuecal-contrast-color: #ffffff;
    --vuecal-base-border-color: rgba(204, 197, 185, 0.55);
    --vuecal-cell-border-color: rgba(204, 197, 185, 0.45);
    --vuecal-border-color: rgba(154, 139, 118, 0.3);
    --vuecal-bg-color: #ffffff;
    --vuecal-text-color: #252422;
    --vuecal-header-color: #ede8df;
    --vuecal-header-text-color: #252422;
    --vuecal-today-bg-color: rgba(81, 203, 206, 0.18);
    --vuecal-today-color: #252422;
    color: #252422 !important;
}

:global([data-theme='light']) .calendar-shell :deep(.vuecal__body),
:global([data-theme='light']) .calendar-shell :deep(.vuecal__flex),
:global([data-theme='light']) .calendar-shell :deep(.vuecal__bg),
:global([data-theme='light']) .calendar-shell :deep(.vuecal__week),
:global([data-theme='light']) .calendar-shell :deep(.vuecal__cell-content) {
    background: #fffaf2 !important;
    color: #252422 !important;
}

:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__body),
:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__flex),
:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__bg),
:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__week),
:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__scrollable-wrap),
:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__scrollable),
:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__cell-content),
.calendar-shell-light :deep(.vuecal__body),
.calendar-shell-light :deep(.vuecal__flex),
.calendar-shell-light :deep(.vuecal__bg),
.calendar-shell-light :deep(.vuecal__week),
.calendar-shell-light :deep(.vuecal__scrollable-wrap),
.calendar-shell-light :deep(.vuecal__scrollable),
.calendar-shell-light :deep(.vuecal__cell-content) {
    background: #fffaf2 !important;
    color: #252422 !important;
}

:global([data-theme='light']) .calendar-shell :deep(.vuecal__weekdays-headings),
:global([data-theme='light']) .calendar-shell :deep(.vuecal__heading) {
    background: #252422;
    color: #f8f5ef;
    border-color: rgba(204, 197, 185, 0.55);
}

:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__weekdays-headings),
:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__heading),
.calendar-shell-light :deep(.vuecal__weekdays-headings),
.calendar-shell-light :deep(.vuecal__heading) {
    background: #111827 !important;
    color: #ffffff !important;
    border-color: rgba(204, 197, 185, 0.55) !important;
}

:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__weekday),
.calendar-shell-light :deep(.vuecal__weekday) {
    background: #111827 !important;
    color: #ffffff !important;
    font-weight: 700;
}

:global([data-theme='light']) .calendar-shell :deep(.vuecal__heading span),
:global([data-theme='light']) .calendar-shell :deep(.vuecal__heading button) {
    color: #f8f5ef;
}

:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__heading span),
:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__heading button),
.calendar-shell-light :deep(.vuecal__heading span),
.calendar-shell-light :deep(.vuecal__heading button) {
    color: #ffffff !important;
}

:global([data-theme='light']) .calendar-shell :deep(.vuecal__cell) {
    background: #fffaf2 !important;
    border-color: rgba(154, 139, 118, 0.28) !important;
}

:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__cell),
.calendar-shell-light :deep(.vuecal__cell) {
    background: #fffaf2 !important;
    border-color: rgba(154, 139, 118, 0.28) !important;
    color: #252422 !important;
    opacity: 1 !important;
}

:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__cell::before),
.calendar-shell-light :deep(.vuecal__cell::before) {
    background: transparent !important;
    filter: none !important;
}

:global([data-theme='light']) .calendar-shell :deep(.vuecal__cell:nth-child(even)) {
    background: #fff7eb !important;
}

:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__cell:nth-child(even)),
.calendar-shell-light :deep(.vuecal__cell:nth-child(even)) {
    background: #fff7eb !important;
}

:global([data-theme='light']) .calendar-shell :deep(.vuecal__cell--out-of-scope) {
    background: #eee8dd !important;
    opacity: 1;
}

:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__cell--out-of-scope),
:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__cell--out-of-range),
.calendar-shell-light :deep(.vuecal__cell--out-of-scope),
.calendar-shell-light :deep(.vuecal__cell--out-of-range) {
    background: #f3eee6 !important;
    color: #57534e !important;
    opacity: 1 !important;
}

:global([data-theme='light']) .calendar-shell :deep(.vuecal__cell--selected),
:global([data-theme='light']) .calendar-shell :deep(.vuecal__cell--current),
:global([data-theme='light']) .calendar-shell :deep(.vuecal__cell--today) {
    background: #f4fbfb !important;
}

:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__cell--selected),
:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__cell--current),
:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__cell--today),
.calendar-shell-light :deep(.vuecal__cell--selected),
.calendar-shell-light :deep(.vuecal__cell--current),
.calendar-shell-light :deep(.vuecal__cell--today) {
    background: #f4fbfb !important;
}

:global([data-theme='light']) .calendar-shell :deep(.vuecal__cell-date) {
    background: #d7f0f1;
    color: #252422;
    font-weight: 700;
}

:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__cell-date),
.calendar-shell-light :deep(.vuecal__cell-date) {
    background: #d7f0f1 !important;
    color: #252422 !important;
    font-weight: 800 !important;
    opacity: 1 !important;
}

:global([data-theme='light']) .calendar-shell :deep(.vuecal__cell--today .vuecal__cell-date) {
    background: #51cbce;
    color: #ffffff;
}

:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__cell--today .vuecal__cell-date),
.calendar-shell-light :deep(.vuecal__cell--today .vuecal__cell-date) {
    background: #0f9da1 !important;
    color: #ffffff !important;
}

:global([data-theme='light']) .calendar-shell :deep(.vuecal__no-event) {
    color: #9a9a9a;
}

:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__no-event),
.calendar-shell-light :deep(.vuecal__no-event) {
    color: #78716c !important;
}

:global([data-theme='light']) .calendar-shell :deep(.vuecal__event) {
    background: transparent !important;
    color: inherit !important;
}

:global([data-theme='light']) .calendar-shell-light :deep(.vuecal__event),
.calendar-shell-light :deep(.vuecal__event) {
    background: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
    color: inherit !important;
    opacity: 1 !important;
}

:global([data-theme='light']) .calendar-shell-light .booking-chip,
.calendar-shell-light .booking-chip {
    color: #111827 !important;
}

:global([data-theme='light']) .calendar-shell-light .booking-chip span,
.calendar-shell-light .booking-chip span {
    color: inherit !important;
    opacity: 1 !important;
}

:global([data-theme='light']) .booking-chip.booking-status-pending {
    border-color: #d49b2a;
    background: #fff4d6;
    color: #5f4307;
}

:global([data-theme='light']) .booking-chip.booking-status-confirmed {
    border-color: #2d9d61;
    background: #ddf8e8;
    color: #145c36;
}

:global([data-theme='light']) .booking-chip.booking-status-completed {
    border-color: #23979a;
    background: #dff8f9;
    color: #115f62;
}

:global([data-theme='light']) .booking-chip.booking-status-cancelled {
    border-color: #d35b38;
    background: #ffe5dc;
    color: #8c321d;
}

:global([data-theme='light']) .calendar-shell-light .booking-chip.booking-status-pending,
.calendar-shell-light .booking-chip.booking-status-pending {
    color: #111827 !important;
}

:global([data-theme='light']) .calendar-shell-light .booking-chip.booking-status-confirmed,
.calendar-shell-light .booking-chip.booking-status-confirmed {
    color: #111827 !important;
}

:global([data-theme='light']) .calendar-shell-light .booking-chip.booking-status-completed,
.calendar-shell-light .booking-chip.booking-status-completed {
    color: #111827 !important;
}

:global([data-theme='light']) .calendar-shell-light .booking-chip.booking-status-cancelled,
.calendar-shell-light .booking-chip.booking-status-cancelled {
    color: #111827 !important;
}
</style>
