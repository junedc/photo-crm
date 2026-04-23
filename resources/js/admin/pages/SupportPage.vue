<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const copied = ref(false);
const tickets = computed(() => props.data.tickets ?? []);
const referred = computed(() => props.data.referral?.referred ?? []);

const copyReferralLink = async () => {
    const url = props.data.referral?.url ?? '';

    if (!url) {
        return;
    }

    try {
        await navigator.clipboard.writeText(url);
        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 2200);
    } catch {
        const input = document.createElement('input');
        input.value = url;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        copied.value = true;
    }
};

const statusClass = (status) => ({
    open: 'bg-emerald-400/15 text-emerald-200',
    in_progress: 'bg-cyan-400/15 text-cyan-200',
    resolved: 'bg-violet-400/15 text-violet-200',
    closed: 'bg-stone-400/15 text-stone-300',
}[status] ?? 'bg-stone-400/15 text-stone-300');

const priorityClass = (priority) => ({
    low: 'bg-stone-400/15 text-stone-300',
    normal: 'bg-sky-400/15 text-sky-200',
    high: 'bg-amber-400/15 text-amber-200',
    urgent: 'bg-rose-400/15 text-rose-200',
}[priority] ?? 'bg-sky-400/15 text-sky-200');
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-sky-200">Support Center</p>
        <h2 class="text-sm font-bold italic text-white">Tickets and platform referrals</h2>
        <p class="text-sm text-stone-300">
            Report bugs to the platform team and share your referral link with other photobooth businesses.
        </p>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <div class="mb-5">
                <p class="text-[11px] uppercase tracking-[0.3em] text-sky-200">New Ticket</p>
                <h3 class="mt-2 text-lg font-semibold italic text-white">Log a bug or support request</h3>
                <p class="mt-2 text-sm text-stone-400">Use urgent only when the issue blocks bookings, invoices, or payment collection.</p>
            </div>

            <form :action="data.routes.supportTicketsStore" method="POST" class="space-y-4">
                <input type="hidden" name="_token" :value="data.csrfToken">

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-2 block text-xs uppercase tracking-[0.22em] text-stone-400">Type</span>
                        <select name="type" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300/50">
                            <option v-for="(label, key) in data.ticketTypes" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-xs uppercase tracking-[0.22em] text-stone-400">Priority</span>
                        <select name="priority" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300/50">
                            <option v-for="(label, key) in data.ticketPriorities" :key="key" :value="key" :selected="key === 'normal'">{{ label }}</option>
                        </select>
                    </label>
                </div>

                <label class="block">
                    <span class="mb-2 block text-xs uppercase tracking-[0.22em] text-stone-400">Subject</span>
                    <input name="subject" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300/50" placeholder="Example: Booking calendar does not load">
                </label>

                <label class="block">
                    <span class="mb-2 block text-xs uppercase tracking-[0.22em] text-stone-400">Details</span>
                    <textarea name="description" rows="7" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-sky-300/50" placeholder="Tell us what happened, what page you were on, and what you expected instead."></textarea>
                </label>

                <button type="submit" class="w-full rounded-xl bg-sky-300 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-sky-200">
                    Submit ticket
                </button>
            </form>
        </div>

        <div class="space-y-4">
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                <p class="text-[11px] uppercase tracking-[0.3em] text-emerald-200">Referral Program</p>
                <div class="mt-3 grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                    <div>
                        <h3 class="text-lg font-semibold italic text-white">Refer another tenant to the platform</h3>
                        <p class="mt-2 text-sm leading-6 text-stone-400">
                            Share this link with another photobooth business. When they create a workspace, the referral is tracked here.
                        </p>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2">
                            <p class="text-lg font-semibold text-white">{{ data.referral?.count ?? 0 }}</p>
                            <p class="text-[10px] uppercase tracking-[0.2em] text-stone-500">Total</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2">
                            <p class="text-lg font-semibold text-cyan-100">{{ data.referral?.qualified_count ?? 0 }}</p>
                            <p class="text-[10px] uppercase tracking-[0.2em] text-stone-500">Qualified</p>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2">
                            <p class="text-lg font-semibold text-emerald-100">{{ data.referral?.rewarded_count ?? 0 }}</p>
                            <p class="text-[10px] uppercase tracking-[0.2em] text-stone-500">Rewarded</p>
                        </div>
                    </div>
                </div>

                <div class="mt-5 rounded-2xl border border-emerald-300/20 bg-emerald-300/10 p-4">
                    <p class="text-xs uppercase tracking-[0.25em] text-emerald-200">Referral code</p>
                    <p class="mt-2 text-2xl font-semibold text-white">{{ data.referral?.code }}</p>
                    <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                        <input :value="data.referral?.url" readonly class="min-w-0 flex-1 rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-stone-200 outline-none">
                        <button type="button" class="rounded-xl border border-emerald-300/30 px-4 py-2.5 text-sm font-semibold text-emerald-100 transition hover:bg-emerald-300/10" @click="copyReferralLink">
                            {{ copied ? 'Copied' : 'Copy link' }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
                <div class="border-b border-white/10 px-2 pb-3">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Referral History</p>
                    <h3 class="mt-2 text-sm font-semibold italic text-white">{{ referred.length }} referred workspace{{ referred.length === 1 ? '' : 's' }}</h3>
                </div>

                <div class="max-h-80 overflow-y-auto">
                    <div v-for="referral in referred" :key="referral.id" class="grid gap-3 border-b border-white/10 px-3 py-3 sm:grid-cols-[minmax(0,1fr)_120px] sm:items-center">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-white">{{ referral.workspace_name || 'Pending workspace' }}</p>
                            <p class="mt-1 truncate text-xs text-stone-400">{{ referral.owner_email || 'No owner email recorded' }} · {{ referral.created_at }}</p>
                        </div>
                        <span class="inline-flex h-8 items-center justify-center rounded-full bg-emerald-400/15 px-3 text-[11px] font-medium text-emerald-200">
                            {{ referral.status_label }}
                        </span>
                    </div>
                    <div v-if="!referred.length" class="rounded-2xl border border-dashed border-white/15 bg-slate-950/40 px-4 py-5 text-sm text-stone-400">
                        No referred tenants yet.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
        <div class="border-b border-white/10 px-2 pb-3">
            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Support Tickets</p>
            <h3 class="mt-2 text-sm font-semibold italic text-white">Ticket list <span class="font-normal text-stone-400">/ latest requests from this workspace.</span></h3>
        </div>

        <div class="max-h-[55vh] overflow-y-auto">
            <div v-for="ticket in tickets" :key="ticket.id" class="grid gap-3 border-b border-white/10 px-3 py-4 xl:grid-cols-[150px_minmax(0,1fr)_130px_130px] xl:items-center">
                <div>
                    <p class="text-sm font-semibold text-sky-100">{{ ticket.ticket_number }}</p>
                    <p class="mt-1 text-xs text-stone-500">{{ ticket.created_at }}</p>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-white">{{ ticket.subject }}</p>
                    <p class="mt-1 line-clamp-2 text-xs leading-5 text-stone-400">{{ ticket.description }}</p>
                </div>
                <span class="inline-flex h-8 w-fit items-center justify-center rounded-full px-3 text-[11px] font-medium" :class="priorityClass(ticket.priority)">
                    {{ ticket.priority_label }}
                </span>
                <span class="inline-flex h-8 w-fit items-center justify-center rounded-full px-3 text-[11px] font-medium" :class="statusClass(ticket.status)">
                    {{ ticket.status_label }}
                </span>
            </div>

            <div v-if="!tickets.length" class="rounded-2xl border border-dashed border-white/15 bg-slate-950/40 px-4 py-5 text-sm text-stone-400">
                No support tickets yet.
            </div>
        </div>
    </section>
</template>
