<script setup>
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    page: {
        type: String,
        required: true,
    },
    data: {
        type: Object,
        required: true,
    },
});

const notificationOpen = ref(false);
const notificationPanel = ref(null);
const notificationButton = ref(null);
const notifications = ref([...(props.data.notifications ?? [])]);
const dismissingNotifications = ref(new Set());
const notificationCount = computed(() => notifications.value.length);
let notificationRefreshTimeout = null;

const navItems = computed(() => [
    { key: 'overview', label: 'Overview', href: props.data.routes.dashboard, accent: 'amber', icon: 'dashboard' },
    { key: 'calendar', label: 'Calendar', href: props.data.routes.calendar, accent: 'cyan', icon: 'calendar' },
    { key: 'bookings', label: 'Bookings', href: props.data.routes.bookings, accent: 'rose', icon: 'clipboard' },
    { key: 'invoices', label: 'Invoices', href: props.data.routes.invoices, accent: 'emerald', icon: 'receipt' },
    { key: 'leads', label: 'Leads', href: props.data.routes.leads, accent: 'violet', icon: 'spark' },
    { key: 'customers', label: 'Customers', href: props.data.routes.customers, accent: 'cyan', icon: 'users' },
    { key: 'vendors', label: 'Vendors', href: props.data.routes.vendors ?? '/vendors', accent: 'sky', icon: 'users' },
    { key: 'campaigns', label: 'Campaigns', href: props.data.routes.campaigns, accent: 'rose', icon: 'megaphone' },
    { key: 'email_tracking', label: 'Email Tracking', href: props.data.routes.emailTracking ?? '/email-tracking', accent: 'violet', icon: 'receipt' },
    { key: 'tasks', label: 'Tasks', href: props.data.routes.tasks, accent: 'sky', icon: 'clipboard' },
    { key: 'packages', label: 'Packages', href: props.data.routes.packages, accent: 'amber', icon: 'box' },
    { key: 'equipment', label: 'Equipment', href: props.data.routes.equipment, accent: 'cyan', icon: 'camera' },
    { key: 'addons', label: 'Add-Ons', href: props.data.routes.addons, accent: 'emerald', icon: 'plus' },
    { key: 'discounts', label: 'Discounts', href: props.data.routes.discounts, accent: 'violet', icon: 'tag' },
    { key: 'users', label: 'Users', href: props.data.routes.users, accent: 'cyan', icon: 'users' },
    { key: 'roles', label: 'Roles', href: props.data.routes.roles, accent: 'violet', icon: 'shield' },
    { key: 'access', label: 'Access', href: props.data.routes.access, accent: 'amber', icon: 'key' },
    { key: 'support', label: 'Support', href: props.data.routes.support ?? '/support', accent: 'sky', icon: 'support' },
    { key: 'referrals', label: 'Referrals', href: props.data.routes.referrals ?? '/referrals', accent: 'emerald', icon: 'referral' },
].filter((item) => item.href && (!Array.isArray(props.data.allowedScreens) || props.data.allowedScreens.includes(item.key))));

const sectionDefinitions = [
    { key: 'daily', label: 'Daily Work', items: ['overview', 'calendar', 'bookings', 'quotes', 'invoices'] },
    { key: 'contacts', label: 'Contacts', items: ['leads', 'customers', 'vendors'] },
    { key: 'marketing', label: 'Marketing', items: ['campaigns', 'email_tracking'] },
    { key: 'planning', label: 'Planning', items: ['tasks'] },
    { key: 'catalog', label: 'Catalog', items: ['packages', 'equipment', 'addons', 'discounts'] },
    { key: 'admin', label: 'Admin', items: ['users', 'roles', 'access', 'support', 'referrals'] },
];

const navSections = computed(() => {
    const itemsByKey = new Map(navItems.value.map((item) => [item.key, item]));

    return sectionDefinitions
        .map((section) => ({
            ...section,
            items: section.items.map((key) => itemsByKey.get(key)).filter(Boolean),
        }))
        .filter((section) => section.items.length > 0);
});

const navIcons = {
    dashboard: ['M4 13h6V4H4v9Z', 'M14 20h6V4h-6v16Z', 'M4 20h6v-3H4v3Z'],
    calendar: ['M7 3v4M17 3v4', 'M4 9h16', 'M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z'],
    clipboard: ['M9 5h6', 'M9 12h6M9 16h4', 'M8 4h8a2 2 0 0 1 2 2v14H6V6a2 2 0 0 1 2-2Z'],
    receipt: ['M6 3h12v18l-3-2-3 2-3-2-3 2V3Z', 'M9 8h6M9 12h6M9 16h3'],
    spark: ['M12 3l1.7 5.1L19 10l-5.3 1.9L12 17l-1.7-5.1L5 10l5.3-1.9L12 3Z', 'M5 17l.8 2.2L8 20l-2.2.8L5 23l-.8-2.2L2 20l2.2-.8L5 17Z'],
    users: ['M16 19a4 4 0 0 0-8 0', 'M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z', 'M20 19a3 3 0 0 0-3-3M4 19a3 3 0 0 1 3-3'],
    megaphone: ['M4 13h3l9 4V7L7 11H4v2Z', 'M7 13v5a2 2 0 0 0 2 2h1', 'M18 9a4 4 0 0 1 0 6'],
    box: ['M4 8l8-4 8 4-8 4-8-4Z', 'M4 8v8l8 4 8-4V8', 'M12 12v8'],
    camera: ['M5 8h3l1.5-2h5L16 8h3a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2Z', 'M12 17a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z'],
    plus: ['M12 5v14M5 12h14', 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z'],
    tag: ['M4 12V5h7l9 9-7 7-9-9Z', 'M8 8h.01'],
    shield: ['M12 3l7 3v5c0 5-3.5 8.5-7 10-3.5-1.5-7-5-7-10V6l7-3Z', 'M9 12l2 2 4-4'],
    key: ['M15 7a4 4 0 1 0-2.8 6.8L10 16H7v3H4v3', 'M16.5 7.5h.01'],
    support: ['M5 19v-5a7 7 0 1 1 14 0v5', 'M5 19h4v-6H5v6ZM15 19h4v-6h-4v6Z', 'M9 20h3a3 3 0 0 0 3-3'],
    referral: ['M7 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M17 21a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M10.5 8.5l3 4.5M10.5 15.5l3-1.5'],
    settings: ['M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z', 'M19.4 15a1.8 1.8 0 0 0 .36 1.98l.04.04-1.8 3.12-.05-.02a1.8 1.8 0 0 0-2 .36l-.3.24a1.8 1.8 0 0 0-.66 1.5V22h-6v-.08a1.8 1.8 0 0 0-.66-1.5l-.3-.24a1.8 1.8 0 0 0-2-.36l-.05.02-1.8-3.12.04-.04A1.8 1.8 0 0 0 4.6 15l-.06-.38A1.8 1.8 0 0 0 3.2 13.2L3 13.14V9.86l.2-.06a1.8 1.8 0 0 0 1.34-1.42L4.6 8a1.8 1.8 0 0 0-.36-1.98l-.04-.04 1.8-3.12.05.02a1.8 1.8 0 0 0 2-.36l.3-.24A1.8 1.8 0 0 0 9 1.08V1h6v.08a1.8 1.8 0 0 0 .66 1.5l.3.24a1.8 1.8 0 0 0 2 .36l.05-.02 1.8 3.12-.04.04A1.8 1.8 0 0 0 19.4 8l.06.38A1.8 1.8 0 0 0 20.8 9.8l.2.06v3.28l-.2.06a1.8 1.8 0 0 0-1.34 1.42l-.06.38Z'],
};

const activeSection = computed(() => {
    if (props.page === 'overview') {
        return 'overview';
    }

    if (props.page.startsWith('packages')) {
        return 'packages';
    }

    if (props.page.startsWith('equipment')) {
        return 'equipment';
    }

    if (props.page.startsWith('addons')) {
        return 'addons';
    }

    if (props.page.startsWith('discounts')) {
        return 'discounts';
    }

    if (props.page.startsWith('leads')) {
        return 'leads';
    }

    if (props.page.startsWith('customers')) {
        return 'customers';
    }

    if (props.page.startsWith('vendors')) {
        return 'vendors';
    }

    if (props.page.startsWith('campaigns')) {
        return 'campaigns';
    }

    if (props.page.startsWith('email-tracking')) {
        return 'email_tracking';
    }

    if (props.page.startsWith('tasks')) {
        return 'tasks';
    }

    if (props.page.startsWith('calendar')) {
        return 'calendar';
    }

    if (props.page.startsWith('bookings')) {
        return 'bookings';
    }

    if (props.page.startsWith('quotes')) {
        return 'quotes';
    }

    if (props.page.startsWith('invoices')) {
        return 'invoices';
    }

    if (props.page.startsWith('support')) {
        return 'support';
    }

    if (props.page.startsWith('referrals')) {
        return 'referrals';
    }

    if (props.page.startsWith('settings')) {
        return 'settings';
    }

    if (props.page.startsWith('users')) {
        return 'users';
    }

    if (props.page.startsWith('roles')) {
        return 'roles';
    }

    if (props.page.startsWith('access')) {
        return 'access';
    }

    return props.page;
});

const activeGroup = computed(() => navSections.value.find((section) => section.items.some((item) => item.key === activeSection.value))?.key ?? 'daily');
const collapsedGroups = ref(new Set());

const isGroupCollapsed = (groupKey) => collapsedGroups.value.has(groupKey) && activeGroup.value !== groupKey;

const toggleGroup = (groupKey) => {
    const next = new Set(collapsedGroups.value);

    if (next.has(groupKey)) {
        next.delete(groupKey);
    } else {
        next.add(groupKey);
    }

    collapsedGroups.value = next;
};

const accentClass = (item) => {
    if (activeSection.value !== item.key) {
        return 'border-transparent text-stone-300 hover:bg-stone-800/80 hover:text-white';
    }

    return {
        amber: 'border-amber-400/30 bg-amber-400/10 text-white shadow-[inset_3px_0_0_0_rgba(251,191,36,0.8)]',
        cyan: 'border-cyan-400/30 bg-cyan-400/10 text-white shadow-[inset_3px_0_0_0_rgba(34,211,238,0.8)]',
        emerald: 'border-emerald-400/30 bg-emerald-400/10 text-white shadow-[inset_3px_0_0_0_rgba(52,211,153,0.8)]',
        sky: 'border-sky-400/30 bg-sky-400/10 text-white shadow-[inset_3px_0_0_0_rgba(56,189,248,0.8)]',
        violet: 'border-violet-400/30 bg-violet-400/10 text-white shadow-[inset_3px_0_0_0_rgba(167,139,250,0.8)]',
        rose: 'border-rose-400/30 bg-rose-400/10 text-white shadow-[inset_3px_0_0_0_rgba(251,113,133,0.8)]',
        slate: 'border-slate-400/30 bg-slate-400/10 text-white shadow-[inset_3px_0_0_0_rgba(148,163,184,0.8)]',
    }[item.accent];
};

const flashStatus = ref(props.data.flash?.status ?? '');
const flashErrors = ref(props.data.flash?.errors ?? []);
let statusTimeout = null;
let errorTimeout = null;

const clearStatusLater = () => {
    window.clearTimeout(statusTimeout);
    statusTimeout = window.setTimeout(() => {
        flashStatus.value = '';
    }, 3000);
};

const clearErrorsLater = () => {
    window.clearTimeout(errorTimeout);
    errorTimeout = window.setTimeout(() => {
        flashErrors.value = [];
    }, 4500);
};

const onAdminToast = (event) => {
    const detail = event.detail ?? {};

    if (detail.type === 'success' && detail.message) {
        flashErrors.value = [];
        flashStatus.value = detail.message;
        clearStatusLater();
        queueNotificationRefresh();
    }

    if (detail.type === 'error') {
        flashStatus.value = '';
        flashErrors.value = detail.errors?.length ? detail.errors : ['Something went wrong.'];
        clearErrorsLater();
    }
};

const refreshNotifications = async () => {
    const url = props.data.notificationRoutes?.index;

    if (!url) {
        return;
    }

    try {
        const response = await axios.get(url, {
            headers: {
                Accept: 'application/json',
            },
        });

        notifications.value = [...(response.data?.notifications ?? [])];
    } catch {
        // Keep the current bell state if refresh fails.
    }
};

const queueNotificationRefresh = () => {
    window.clearTimeout(notificationRefreshTimeout);
    notificationRefreshTimeout = window.setTimeout(() => {
        refreshNotifications();
    }, 150);
};

const toggleNotifications = () => {
    notificationOpen.value = !notificationOpen.value;
};

const closeNotifications = () => {
    notificationOpen.value = false;
};

const dismissNotification = async (notification) => {
    if (!notification?.dismiss_url || dismissingNotifications.value.has(notification.id)) {
        return;
    }

    const previousNotifications = [...notifications.value];
    notifications.value = notifications.value.filter((entry) => entry.id !== notification.id);
    dismissingNotifications.value = new Set([...dismissingNotifications.value, notification.id]);

    try {
        await axios.post(notification.dismiss_url, {
            _token: props.data.csrfToken,
        }, {
            headers: {
                Accept: 'application/json',
            },
        });
    } catch (error) {
        notifications.value = previousNotifications;
        window.dispatchEvent(new CustomEvent('admin-toast', {
            detail: {
                type: 'error',
                errors: [error.response?.data?.message ?? 'Could not remove that task notification.'],
            },
        }));
    } finally {
        const next = new Set(dismissingNotifications.value);
        next.delete(notification.id);
        dismissingNotifications.value = next;
    }
};

const onDocumentClick = (event) => {
    const target = event.target;

    if (notificationPanel.value?.contains(target) || notificationButton.value?.contains(target)) {
        return;
    }

    closeNotifications();
};

const onEscape = (event) => {
    if (event.key === 'Escape') {
        closeNotifications();
    }
};

watch(
    () => props.data.flash?.status,
    (status) => {
        flashStatus.value = status ?? '';

        if (flashStatus.value) {
            clearStatusLater();
        }
    },
    { immediate: true },
);

watch(
    () => props.data.flash?.errors,
    (errors) => {
        flashErrors.value = errors ?? [];

        if (flashErrors.value.length) {
            clearErrorsLater();
        }
    },
    { immediate: true },
);

watch(
    () => props.data.notifications,
    (nextNotifications) => {
        notifications.value = [...(nextNotifications ?? [])];
    },
);

onMounted(() => {
    window.addEventListener('admin-toast', onAdminToast);
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onEscape);
    refreshNotifications();
});

onBeforeUnmount(() => {
    window.removeEventListener('admin-toast', onAdminToast);
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onEscape);
    window.clearTimeout(statusTimeout);
    window.clearTimeout(errorTimeout);
    window.clearTimeout(notificationRefreshTimeout);
});
</script>

<template>
    <div class="min-h-screen bg-[#0f172a] text-stone-50">
        <div class="fixed right-4 top-20 z-[60] flex w-full max-w-sm flex-col gap-3 sm:right-6">
            <transition name="toast">
                <div v-if="flashStatus" class="rounded-2xl border border-emerald-400/30 bg-emerald-500/95 px-5 py-4 text-sm text-white shadow-2xl shadow-emerald-950/40 backdrop-blur">
                    {{ flashStatus }}
                </div>
            </transition>

            <transition name="toast">
                <div v-if="flashErrors.length" class="rounded-2xl border border-rose-400/30 bg-rose-500/95 px-5 py-4 text-sm text-white shadow-2xl shadow-rose-950/40 backdrop-blur">
                    <p class="font-semibold">Please fix the highlighted form details.</p>
                    <ul class="mt-2 list-disc pl-5">
                        <li v-for="error in flashErrors" :key="error">{{ error }}</li>
                    </ul>
                </div>
            </transition>
        </div>

        <header class="fixed inset-x-0 top-0 z-50 border-b border-white/10 bg-slate-950/95 backdrop-blur-xl">
            <div class="flex h-16 w-full items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <img
                        v-if="data.tenant?.logo_url"
                        :src="data.tenant.logo_url"
                        :alt="data.tenant?.name || 'Workspace logo'"
                        class="h-10 w-10 rounded-2xl object-cover"
                    >
                    <div v-else class="flex h-10 w-10 items-center justify-center rounded-2xl border border-white/10 bg-white/[0.04] text-xs font-semibold text-stone-200">
                        {{ (data.tenant?.name || 'M').slice(0, 1) }}
                    </div>
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.32em] text-amber-200">MemoShot Admin</p>
                        <h1 class="mt-1 text-lg font-semibold tracking-tight sm:text-xl">{{ data.tenant?.name }}</h1>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="relative">
                        <button
                            ref="notificationButton"
                            type="button"
                            class="relative flex h-10 w-10 items-center justify-center rounded-2xl border border-white/10 bg-white/[0.04] text-stone-200 transition hover:border-cyan-300/40 hover:text-white"
                            @click.stop="toggleNotifications"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M15 18H5l1.6-1.8A2 2 0 0 0 7 14.9V11a5 5 0 1 1 10 0v3.9a2 2 0 0 0 .4 1.3L19 18h-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M10 20a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <span
                                v-if="notificationCount"
                                class="absolute -right-1 -top-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-amber-400 px-1.5 text-[11px] font-semibold text-slate-950"
                            >
                                {{ notificationCount > 99 ? '99+' : notificationCount }}
                            </span>
                        </button>

                        <div
                            v-if="notificationOpen"
                            ref="notificationPanel"
                            class="absolute right-0 top-12 z-[70] w-[24rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-white/10 bg-slate-950/98 shadow-2xl shadow-black/40 backdrop-blur-xl"
                        >
                            <div class="border-b border-white/10 px-4 py-3">
                                <p class="text-[11px] uppercase tracking-[0.3em] text-cyan-200">Notifications</p>
                                <div class="mt-1 flex items-center justify-between gap-3">
                                    <h3 class="text-sm font-semibold text-white">Assigned to {{ data.currentUser?.name || 'you' }}</h3>
                                    <a :href="data.routes.tasks ?? '/tasks'" class="text-xs font-semibold text-cyan-200 transition hover:text-white">Open tasks</a>
                                </div>
                            </div>

                            <div v-if="notifications.length" class="max-h-[26rem] overflow-y-auto p-2">
                                <div
                                    v-for="notification in notifications"
                                    :key="notification.id"
                                    class="group flex items-start gap-2 rounded-xl border border-transparent px-3 py-3 transition hover:border-white/10 hover:bg-white/[0.04]"
                                >
                                    <a
                                        :href="notification.booking_url || notification.task_url"
                                        class="min-w-0 flex-1"
                                        @click="closeNotifications"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <p class="text-sm font-semibold text-white">{{ notification.title }}</p>
                                            <span class="shrink-0 rounded-full bg-cyan-300/10 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-cyan-100">
                                                {{ notification.status }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-xs text-stone-400">Due {{ notification.due_date_label }}</p>
                                        <p v-if="notification.booking_label" class="mt-1 truncate text-xs text-stone-300">{{ notification.booking_label }}</p>
                                    </a>
                                    <button
                                        type="button"
                                        class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-white/10 text-stone-400 transition hover:border-rose-300/40 hover:bg-rose-400/10 hover:text-rose-100"
                                        :aria-label="`Remove ${notification.title} from notifications`"
                                        @click.stop="dismissNotification(notification)"
                                    >
                                        <span aria-hidden="true" class="text-sm leading-none">&times;</span>
                                    </button>
                                </div>
                            </div>

                            <div v-else class="px-4 py-6 text-sm text-stone-400">
                                No notifications assigned to you right now.
                            </div>
                        </div>
                    </div>

                    <div class="hidden rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2 text-right text-xs text-stone-300 sm:block">
                        <span class="block text-[11px] uppercase tracking-[0.3em] text-stone-500">Workspace</span>
                        <span class="font-medium text-white">{{ data.tenant?.slug }}</span>
                    </div>
                    <form :action="data.routes.logout" method="POST">
                        <input type="hidden" name="_token" :value="data.csrfToken">
                        <button type="submit" class="rounded-xl border border-white/10 px-3 py-2 text-sm font-medium text-stone-200 transition hover:border-amber-300/40 hover:text-white">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <div class="flex w-full pt-16">
            <aside class="hidden w-64 shrink-0 overflow-hidden border-r border-white/10 bg-slate-950/90 lg:fixed lg:bottom-0 lg:top-16 lg:block">
                <div class="flex h-full min-h-0 flex-col px-3 py-4">
                    <nav class="min-h-0 flex-1 space-y-3 overflow-y-auto pr-1">
                        <section v-for="section in navSections" :key="section.key" class="rounded-2xl border border-white/5 bg-white/[0.02] p-1.5">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded-xl px-2 py-1.5 text-left text-[10px] font-semibold uppercase tracking-[0.24em] text-stone-500 transition hover:text-stone-300"
                                @click="toggleGroup(section.key)"
                            >
                                <span>{{ section.label }}</span>
                                <svg class="h-3.5 w-3.5 transition" :class="isGroupCollapsed(section.key) ? '-rotate-90' : 'rotate-0'" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>

                            <div v-show="!isGroupCollapsed(section.key)" class="mt-1 space-y-1">
                                <a
                                    v-for="item in section.items"
                                    :key="item.key"
                                    :href="item.href"
                                    class="flex items-center gap-3 rounded-xl border px-3 py-2 text-sm transition"
                                    :class="accentClass(item)"
                                >
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] text-stone-200">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path
                                                v-for="path in navIcons[item.icon]"
                                                :key="path"
                                                :d="path"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                    </span>
                                    <span>{{ item.label }}</span>
                                </a>
                            </div>
                        </section>
                    </nav>

                    <div v-if="!Array.isArray(data.allowedScreens) || data.allowedScreens.includes('settings')" class="mt-3 shrink-0 border-t border-white/10 pt-3">
                        <a
                            :href="data.routes.settings"
                            class="flex items-center gap-3 rounded-xl border px-3 py-2 text-sm transition"
                            :class="accentClass({ key: 'settings', accent: 'slate' })"
                        >
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04] text-stone-200">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path
                                        v-for="path in navIcons.settings"
                                        :key="path"
                                        :d="path"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </span>
                            <span>Settings</span>
                        </a>
                    </div>
                </div>
            </aside>

            <main class="min-w-0 flex-1 px-4 py-5 sm:px-6 lg:ml-64 lg:px-8">
                <div class="w-full space-y-4">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.25s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-10px) scale(0.98);
}
</style>
