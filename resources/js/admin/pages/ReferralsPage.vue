<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const copied = ref(false);
const referred = computed(() => props.data.referral?.referred ?? []);

const copyReferralLink = async () => {
    const url = props.data.referral?.url ?? '';

    if (!url) {
        return;
    }

    try {
        await navigator.clipboard.writeText(url);
    } catch {
        const input = document.createElement('input');
        input.value = url;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
    }

    copied.value = true;
    window.setTimeout(() => {
        copied.value = false;
    }, 2200);
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-emerald-200">Referral Program</p>
        <h2 class="text-sm font-bold italic text-white">Refer tenants to the platform</h2>
        <p class="text-sm text-stone-300">
            Share your referral link and track referred workspaces separately from support tickets.
        </p>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <p class="text-[11px] uppercase tracking-[0.3em] text-emerald-200">Share Link</p>
            <div class="mt-3 grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div>
                    <h3 class="text-lg font-semibold italic text-white">Invite another photobooth business</h3>
                    <p class="mt-2 text-sm leading-6 text-stone-400">
                        When they create a workspace through your link, we record the referral for platform admin review.
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

        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">How It Works</p>
            <div class="mt-4 space-y-3 text-sm leading-6 text-stone-300">
                <p>1. Copy the referral link and send it to another business owner.</p>
                <p>2. They create a workspace using the link.</p>
                <p>3. Platform admin can review and mark the referral as qualified or rewarded.</p>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
        <div class="border-b border-white/10 px-2 pb-3">
            <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Referral History</p>
            <h3 class="mt-2 text-sm font-semibold italic text-white">{{ referred.length }} referred workspace{{ referred.length === 1 ? '' : 's' }}</h3>
        </div>

        <div class="max-h-[55vh] overflow-y-auto">
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
    </section>
</template>
