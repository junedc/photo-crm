<script setup>
import { computed, ref } from 'vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';
import { firstError, hasFieldErrors, isBlank, mergeFieldErrors, requiredMessage } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const { saving, fieldErrors, submitForm } = useWorkspaceCrud();
const clientErrors = ref({});
const form = ref({
    template_id: '',
    subject: '',
    preheader: '',
    headline: '',
    body: '',
    button_text: '',
    button_url: '',
    group_ids: [],
});

const templates = computed(() => props.data.templates ?? []);
const groups = computed(() => props.data.groups ?? []);
const selectedGroups = computed(() => groups.value.filter((group) => form.value.group_ids.includes(group.id)));
const selectedAudienceCount = computed(() => selectedGroups.value.reduce((total, group) => total + (group.customers_count ?? 0), 0));
const validationErrors = computed(() => mergeFieldErrors(clientErrors.value, fieldErrors.value));

const applyTemplate = () => {
    const template = templates.value.find((entry) => entry.id === Number(form.value.template_id));
    if (!template) {
        return;
    }

    form.value.subject = template.subject ?? '';
    form.value.preheader = template.preheader ?? '';
    form.value.headline = template.headline ?? '';
    form.value.body = template.html_body ?? '';
    form.value.button_text = template.button_text ?? '';
    form.value.button_url = template.button_url ?? '';
};

const submitCampaign = async () => {
    const errors = {};

    if (isBlank(form.value.template_id)) {
        errors.template_id = requiredMessage('Template');
    }

    if (isBlank(form.value.subject)) {
        errors.subject = requiredMessage('Email subject');
    }

    if (isBlank(form.value.body)) {
        errors.body = requiredMessage('Rich email body');
    }

    if (!isBlank(form.value.button_text) && isBlank(form.value.button_url)) {
        errors.button_url = 'Button URL is required when button text is entered.';
    }

    clientErrors.value = errors;

    if (hasFieldErrors(errors)) {
        return;
    }

    try {
        const record = await submitForm({
            url: props.data.routes.store,
            data: form.value,
        });

        window.setTimeout(() => {
            window.location.href = record.show_url;
        }, 300);
    } catch {}
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-pink-200">Campaign Studio</p>
        <h2 class="text-sm font-bold italic text-white">Create campaign</h2>
        <p class="text-sm text-stone-300">
            Choose a rich template, refine the campaign copy, then select subscriber groups for the audience.
        </p>
    </section>

    <form class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]" novalidate @submit.prevent="submitCampaign">
        <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Template</label>
                    <select v-model="form.template_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-pink-300/50" :class="firstError(validationErrors, 'template_id') ? 'border-rose-300/60' : ''" @change="applyTemplate">
                        <option value="">Choose a template</option>
                        <option v-for="template in templates" :key="template.id" :value="template.id">{{ template.name }}</option>
                    </select>
                    <p v-if="firstError(validationErrors, 'template_id')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'template_id') }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Email subject</label>
                    <input v-model="form.subject" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-pink-300/50" :class="firstError(validationErrors, 'subject') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'subject')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'subject') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Header headline</label>
                    <input v-model="form.headline" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-pink-300/50">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Preview text</label>
                    <input v-model="form.preheader" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-pink-300/50">
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Rich email body</label>
                    <div contenteditable="true" class="min-h-72 rounded-xl border border-white/10 bg-white px-4 py-3 text-sm leading-6 text-slate-900 outline-none focus:border-pink-300/70" :class="firstError(validationErrors, 'body') ? 'border-rose-300/60' : ''" @input="form.body = $event.target.innerHTML" v-html="form.body" />
                    <p v-if="firstError(validationErrors, 'body')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'body') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Button text</label>
                    <input v-model="form.button_text" type="text" placeholder="Book now" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-pink-300/50">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Button URL</label>
                    <input v-model="form.button_url" type="url" placeholder="https://..." class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-pink-300/50" :class="firstError(validationErrors, 'button_url') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'button_url')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'button_url') }}</p>
                </div>
            </div>
        </section>

        <aside class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Audience</p>
                    <h3 class="mt-1 text-sm font-semibold italic">{{ selectedAudienceCount }} possible recipients</h3>
                </div>
                <a :href="data.routes.campaigns" class="rounded-xl border border-white/10 px-3 py-2 text-sm font-semibold text-white transition hover:bg-white/5">Back</a>
            </div>

            <div class="max-h-[520px] space-y-2 overflow-y-auto pr-1">
                <label v-for="group in groups" :key="group.id" class="flex items-start gap-3 rounded-xl border border-white/10 px-3 py-2.5 text-sm text-stone-200 transition hover:bg-white/[0.03]">
                    <input v-model="form.group_ids" :value="group.id" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-white/20 bg-slate-950 text-pink-300">
                    <span class="min-w-0">
                        <span class="block truncate font-medium text-white">{{ group.name }}</span>
                        <span class="block truncate text-xs text-stone-400">{{ group.customers_count }} users</span>
                    </span>
                </label>
                <p v-if="!groups.length" class="rounded-xl border border-dashed border-white/15 px-3 py-4 text-sm text-stone-400">
                    Create subscriber groups first so campaigns have an audience.
                </p>
            </div>

            <button type="submit" class="mt-5 w-full rounded-xl bg-pink-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-pink-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                {{ saving ? 'Saving...' : 'Save campaign draft' }}
            </button>
        </aside>
    </form>
</template>
