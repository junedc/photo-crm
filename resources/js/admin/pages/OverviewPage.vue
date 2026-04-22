<script setup>
const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const statusClass = (status) => {
    if (status === 'confirmed') {
        return 'bg-emerald-400/15 text-emerald-200';
    }

    if (status === 'pending') {
        return 'bg-amber-300/15 text-amber-200';
    }

    if (status === 'completed') {
        return 'bg-cyan-300/15 text-cyan-200';
    }

    return 'bg-rose-400/15 text-rose-200';
};

const statusLabel = (status) => (status ?? '').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
</script>

<template>
    <section class="rounded-3xl border border-white/10 bg-gradient-to-br from-amber-300/10 via-slate-950 to-rose-300/10 p-5 shadow-xl shadow-black/20">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-[11px] uppercase tracking-[0.35em] text-amber-200">Overview</p>
                <h2 class="mt-1 text-sm font-semibold italic tracking-tight">Workspace snapshot <span class="font-normal text-stone-300">/ Quick totals and the next 3 upcoming events.</span></h2>
            </div>

            <div class="grid gap-3 sm:grid-cols-4">
                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Active Packages</p>
                    <p class="mt-1 text-2xl font-semibold">{{ data.counts.activePackages }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Bookings</p>
                    <p class="mt-1 text-2xl font-semibold">{{ data.counts.bookings }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Leads</p>
                    <p class="mt-1 text-2xl font-semibold">{{ data.counts.leads }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-500">Equipment</p>
                    <p class="mt-1 text-2xl font-semibold">{{ data.counts.equipment }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(0,0.75fr)]">
        <div class="rounded-3xl border border-white/10 bg-white/5 p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.3em] text-rose-200">Upcoming Events</p>
                    <h3 class="mt-1 text-sm font-semibold italic">Next 3 bookings</h3>
                </div>
                <a :href="data.routes.bookings" class="text-sm font-medium text-rose-200 transition hover:text-rose-100">
                    View all
                </a>
            </div>

            <div v-if="data.upcomingBookings?.length" class="mt-4 space-y-3">
                <a
                    v-for="booking in data.upcomingBookings"
                    :key="booking.id"
                    :href="booking.show_url"
                    class="flex items-center justify-between gap-3 rounded-2xl border border-white/10 bg-slate-950/50 px-4 py-3 transition hover:border-rose-300/30 hover:bg-white/[0.04]"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-white">{{ booking.customer_name }}</p>
                        <p class="mt-1 truncate text-xs text-stone-400">
                            {{ booking.event_date_label }}<span v-if="booking.start_time_label"> • {{ booking.start_time_label }}</span><span v-if="booking.package_name"> • {{ booking.package_name }}</span>
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-medium" :class="statusClass(booking.status)">
                        {{ statusLabel(booking.status) }}
                    </span>
                </a>
            </div>

            <div v-else class="mt-4 rounded-2xl border border-dashed border-white/15 bg-slate-950/40 px-4 py-5 text-sm text-stone-400">
                No upcoming bookings yet.
            </div>
        </div>

        <div class="grid gap-4">
            <a :href="data.routes.bookings" class="rounded-3xl border border-white/10 bg-white/5 p-4 transition hover:border-rose-300/40 hover:bg-white/10">
                <p class="text-[11px] uppercase tracking-[0.3em] text-rose-200">Bookings</p>
                <h3 class="mt-1 text-sm font-semibold italic">Manage bookings <span class="font-normal text-stone-300">/ Review requests, update status, and send invoices.</span></h3>
            </a>

            <a :href="data.routes.leads" class="rounded-3xl border border-white/10 bg-white/5 p-4 transition hover:border-violet-300/40 hover:bg-white/10">
                <p class="text-[11px] uppercase tracking-[0.3em] text-violet-200">Leads</p>
                <h3 class="mt-1 text-sm font-semibold italic">Follow up prospects <span class="font-normal text-stone-300">/ Track enquiries, statuses, and booked conversions.</span></h3>
            </a>

            <a :href="data.routes.packages" class="rounded-3xl border border-white/10 bg-white/5 p-4 transition hover:border-amber-300/40 hover:bg-white/10">
                <p class="text-[11px] uppercase tracking-[0.3em] text-amber-200">Catalog</p>
                <h3 class="mt-1 text-sm font-semibold italic">Packages and inventory <span class="font-normal text-stone-300">/ Maintain packages, equipment, and add-ons from one place.</span></h3>
            </a>
        </div>
    </section>
</template>
