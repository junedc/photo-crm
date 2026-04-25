<script setup>
import { computed } from 'vue';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const tickets = computed(() => props.data.tickets ?? []);

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
        <h2 class="text-sm font-bold italic text-white">Ticket list</h2>
        <p class="text-sm text-stone-300">
            Review support tickets, open the conversation, and mark tickets as ongoing or resolved.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
        <div class="border-b border-white/10 px-2 pb-3">
            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Support Tickets</p>
            <h3 class="mt-2 text-sm font-semibold italic text-white">Listing only <span class="font-normal text-stone-400">/ click ticket number or subject to reply.</span></h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                <thead class="text-[11px] uppercase tracking-[0.24em] text-stone-500">
                    <tr>
                        <th class="px-3 py-3 font-medium">Ticket</th>
                        <th class="px-3 py-3 font-medium">Subject</th>
                        <th class="px-3 py-3 font-medium">Priority</th>
                        <th class="px-3 py-3 font-medium">Status</th>
                        <th class="px-3 py-3 font-medium">Replies</th>
                        <th class="px-3 py-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    <tr v-for="ticket in tickets" :key="ticket.id" class="align-middle">
                        <td class="px-3 py-4">
                            <a :href="ticket.show_url" class="font-semibold text-sky-100 transition hover:text-sky-200">
                                {{ ticket.ticket_number }}
                            </a>
                            <p class="mt-1 text-xs text-stone-500">{{ ticket.created_at }}</p>
                        </td>
                        <td class="min-w-72 px-3 py-4">
                            <a :href="ticket.show_url" class="block truncate font-semibold text-white transition hover:text-sky-100">
                                {{ ticket.subject }}
                            </a>
                            <p class="mt-1 line-clamp-1 text-xs leading-5 text-stone-400">{{ ticket.description }}</p>
                        </td>
                        <td class="px-3 py-4">
                            <span class="inline-flex h-8 w-fit items-center justify-center rounded-full px-3 text-[11px] font-medium" :class="priorityClass(ticket.priority)">
                                {{ ticket.priority_label }}
                            </span>
                        </td>
                        <td class="px-3 py-4">
                            <span class="inline-flex h-8 w-fit items-center justify-center rounded-full px-3 text-[11px] font-medium" :class="statusClass(ticket.status)">
                                {{ ticket.status_label }}
                            </span>
                        </td>
                        <td class="px-3 py-4 text-stone-300">
                            {{ ticket.replies_count }}
                        </td>
                        <td class="px-3 py-4">
                            <div class="flex flex-wrap justify-end gap-2">
                                <form v-for="(label, statusId) in data.tenantTicketStatuses" :key="`${ticket.id}-${statusId}`" :action="ticket.status_update_url" method="POST">
                                    <input type="hidden" name="_token" :value="data.csrfToken">
                                    <input type="hidden" name="_method" value="PUT">
                                    <input type="hidden" name="support_status_id" :value="statusId">
                                    <button type="submit" class="rounded-xl border px-3 py-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-40" :class="String(label).toLowerCase() === 'resolved' ? 'border-violet-300/30 text-violet-100 hover:bg-violet-300/10' : 'border-cyan-300/30 text-cyan-100 hover:bg-cyan-300/10'" :disabled="String(ticket.status_id) === String(statusId)">
                                        {{ label }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!tickets.length" class="rounded-2xl border border-dashed border-white/15 bg-slate-950/40 px-4 py-5 text-sm text-stone-400">
                No support tickets yet.
            </div>
        </div>
    </section>
</template>
