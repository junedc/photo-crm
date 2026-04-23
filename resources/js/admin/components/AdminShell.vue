<script setup>
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

const navItems = computed(() => [
    { key: 'overview', label: 'Overview', href: props.data.routes.dashboard, accent: 'amber', icon: 'dashboard' },
    { key: 'calendar', label: 'Calendar', href: props.data.routes.calendar, accent: 'cyan', icon: 'calendar' },
    { key: 'bookings', label: 'Bookings', href: props.data.routes.bookings, accent: 'rose', icon: 'clipboard' },
    { key: 'invoices', label: 'Invoices', href: props.data.routes.invoices, accent: 'emerald', icon: 'receipt' },
    { key: 'leads', label: 'Leads', href: props.data.routes.leads, accent: 'violet', icon: 'spark' },
    { key: 'customers', label: 'Customers', href: props.data.routes.customers, accent: 'cyan', icon: 'users' },
    { key: 'campaigns', label: 'Campaigns', href: props.data.routes.campaigns, accent: 'rose', icon: 'megaphone' },
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

    if (props.page.startsWith('campaigns')) {
        return 'campaigns';
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
    }

    if (detail.type === 'error') {
        flashStatus.value = '';
        flashErrors.value = detail.errors?.length ? detail.errors : ['Something went wrong.'];
        clearErrorsLater();
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

onMounted(() => {
    window.addEventListener('admin-toast', onAdminToast);
});

onBeforeUnmount(() => {
    window.removeEventListener('admin-toast', onAdminToast);
    window.clearTimeout(statusTimeout);
    window.clearTimeout(errorTimeout);
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
                    <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto pr-1">
                        <a
                            v-for="item in navItems"
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
