<script setup>
const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const statusClass = (status) => ({
    open: 'bg-emerald-400/15 text-emerald-200',
    in_progress: 'bg-cyan-400/15 text-cyan-200',
    resolved: 'bg-violet-400/15 text-violet-200',
    closed: 'bg-stone-400/15 text-stone-300',
}[status] ?? 'bg-stone-400/15 text-stone-300');
</script>

<template>
    <section class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <p class="text-[11px] uppercase tracking-[0.35em] text-sky-200">Support Ticket</p>
            <h2 class="text-sm font-bold italic text-white">{{ data.ticket.ticket_number }}</h2>
            <span class="inline-flex h-8 w-fit items-center justify-center rounded-full px-3 text-[11px] font-medium" :class="statusClass(data.ticket.status)">
                {{ data.ticket.status_label }}
            </span>
        </div>
        <a :href="data.routes.support" class="rounded-xl border border-white/10 px-3 py-2 text-sm font-semibold text-stone-200 transition hover:bg-white/5">
            Back to tickets
        </a>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-4">
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">{{ data.ticket.type_label }} / {{ data.ticket.priority_label }}</p>
                <h3 class="mt-2 text-2xl font-semibold text-white">{{ data.ticket.subject }}</h3>
                <p class="mt-4 whitespace-pre-line text-sm leading-6 text-stone-300">{{ data.ticket.description }}</p>
                <div class="mt-5 grid gap-3 text-xs text-stone-500 sm:grid-cols-3">
                    <p>Created by <span class="text-stone-300">{{ data.ticket.created_by || 'Workspace user' }}</span></p>
                    <p>Created <span class="text-stone-300">{{ data.ticket.created_at }}</span></p>
                    <p>Updated <span class="text-stone-300">{{ data.ticket.updated_at }}</span></p>
                </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Replies</p>
                <div class="mt-4 space-y-3">
                    <article v-for="reply in data.ticket.replies" :key="reply.id" class="rounded-2xl border border-white/10 bg-slate-950/50 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-white">{{ reply.created_by }}</p>
                            <p class="text-xs text-stone-500">{{ reply.created_at }}</p>
                        </div>
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-stone-300">{{ reply.message }}</p>
                    </article>

                    <div v-if="!data.ticket.replies?.length" class="rounded-2xl border border-dashed border-white/15 bg-slate-950/40 px-4 py-5 text-sm text-stone-400">
                        No replies yet.
                    </div>
                </div>
            </div>
        </div>

        <aside class="space-y-4">
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                <p class="text-[11px] uppercase tracking-[0.3em] text-sky-200">Status</p>
                <div class="mt-4 grid gap-2">
                    <form v-for="(label, status) in data.tenantTicketStatuses" :key="status" :action="data.ticket.status_update_url" method="POST">
                        <input type="hidden" name="_token" :value="data.csrfToken">
                        <input type="hidden" name="_method" value="PUT">
                        <input type="hidden" name="status" :value="status">
                        <button type="submit" class="w-full rounded-xl border px-4 py-3 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-40" :class="status === 'resolved' ? 'border-violet-300/30 text-violet-100 hover:bg-violet-300/10' : 'border-cyan-300/30 text-cyan-100 hover:bg-cyan-300/10'" :disabled="data.ticket.status === status">
                            Mark as {{ label }}
                        </button>
                    </form>
                </div>
            </div>

            <form :action="data.ticket.reply_store_url" method="POST" class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                <input type="hidden" name="_token" :value="data.csrfToken">
                <p class="text-[11px] uppercase tracking-[0.3em] text-emerald-200">Reply</p>
                <label class="mt-4 block">
                    <span class="mb-2 block text-xs uppercase tracking-[0.22em] text-stone-400">Message</span>
                    <textarea name="message" rows="8" required class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50" placeholder="Write an update or reply for this support ticket."></textarea>
                </label>
                <button type="submit" class="mt-4 w-full rounded-xl bg-emerald-300 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-emerald-200">
                    Add reply
                </button>
            </form>
        </aside>
    </section>
</template>
