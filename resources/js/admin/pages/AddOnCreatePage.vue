<script setup>
import { computed, ref, watch } from 'vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';
import { firstError, hasFieldErrors, isBlank, mergeFieldErrors, requiredMessage } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const photoInput = ref(null);
const { saving, fieldErrors, submitForm } = useWorkspaceCrud();
const clientErrors = ref({});
const form = ref({
    sku: '',
    name: '',
    type: 'Items',
    inventory_item_category_id: '',
    is_publicly_displayed: false,
    description: '',
    unit_price: '',
    discount_percentage: '',
    duration: '',
    due_days_before_event: '',
});
const validationErrors = computed(() => mergeFieldErrors(clientErrors.value, fieldErrors.value));
const addOnTypes = computed(() => props.data.addOnTypes ?? []);
const inventoryItemCategoryOptions = computed(() => props.data.inventoryItemCategoryOptions ?? []);
const isActionCategory = computed(() => form.value.type === 'Action');

watch(() => form.value.type, (value) => {
    if (value !== 'Action') {
        form.value.due_days_before_event = '';
    }
});

const validateAddOnForm = () => {
    const errors = {};

    if (isBlank(form.value.sku)) {
        errors.sku = requiredMessage('Product code');
    }

    if (isBlank(form.value.name)) {
        errors.name = requiredMessage('Name');
    }

    if (!addOnTypes.value.includes(form.value.type)) {
        errors.type = 'Choose Action or Items.';
    }

    if (isBlank(form.value.unit_price)) {
        errors.unit_price = requiredMessage('Price');
    } else if (Number(form.value.unit_price) < 0) {
        errors.unit_price = 'Price must be zero or greater.';
    }

    if (!isBlank(form.value.discount_percentage) && (Number(form.value.discount_percentage) < 0 || Number(form.value.discount_percentage) > 100)) {
        errors.discount_percentage = 'Discount must be between 0 and 100.';
    }

    clientErrors.value = errors;

    return !hasFieldErrors(errors);
};

const createAddOn = async () => {
    if (!validateAddOnForm()) {
        return;
    }

    const formData = new FormData();

    ['sku', 'name', 'type', 'inventory_item_category_id', 'description', 'unit_price', 'discount_percentage', 'duration', 'due_days_before_event'].forEach((key) => {
        formData.append(key, form.value[key] ?? '');
    });
    formData.append('is_publicly_displayed', form.value.is_publicly_displayed ? '1' : '0');

    const file = photoInput.value?.files?.[0];
    if (file) {
        formData.append('photo', file);
    }

    try {
        const record = await submitForm({
            url: props.data.routes.store,
            data: formData,
        });

        window.setTimeout(() => {
            window.location.href = record.show_url;
        }, 300);
    } catch {}
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-emerald-200">Add-Ons Workspace</p>
        <h2 class="text-sm font-bold italic text-white">Create add-on</h2>
        <p class="text-sm text-stone-300">
            Add a new package add-on with a product code, image, price, and duration.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-emerald-200">New Add-On</p>
                <h3 class="mt-1 text-sm font-semibold italic">Create record</h3>
            </div>
            <a :href="data.routes.addons" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/5">
                Back to list
            </a>
        </div>

        <form class="space-y-4" novalidate @submit.prevent="createAddOn">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Product Code</label>
                    <input v-model="form.sku" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50" :class="firstError(validationErrors, 'sku') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'sku')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'sku') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Name</label>
                    <input v-model="form.name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50" :class="firstError(validationErrors, 'name') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'name') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Type</label>
                    <select
                        v-model="form.type"
                        class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50"
                        :class="firstError(validationErrors, 'type') ? 'border-rose-300/60' : ''"
                    >
                        <option value="">Select type</option>
                        <option v-for="type in addOnTypes" :key="type" :value="type">{{ type }}</option>
                    </select>
                    <p v-if="firstError(validationErrors, 'type')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'type') }}</p>
                    <p v-else class="mt-1 text-xs text-stone-500">Controls whether this add-on behaves like an action item or a normal item.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Add-On Category</label>
                    <select v-model="form.inventory_item_category_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50">
                        <option value="">Select category</option>
                        <option v-for="category in inventoryItemCategoryOptions" :key="category.id" :value="String(category.id)">{{ category.name }}</option>
                    </select>
                    <p class="mt-1 text-xs text-stone-500">Managed from Settings > Maintenance > Inventory Item Category.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Price</label>
                    <input v-model="form.unit_price" type="number" min="0" step="0.01" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50" :class="firstError(validationErrors, 'unit_price') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'unit_price')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'unit_price') }}</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Discount %</label>
                    <input v-model="form.discount_percentage" type="number" min="0" max="100" step="0.01" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50" :class="firstError(validationErrors, 'discount_percentage') ? 'border-rose-300/60' : ''" placeholder="0.00">
                    <p v-if="firstError(validationErrors, 'discount_percentage')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'discount_percentage') }}</p>
                    <p v-else class="mt-1 text-xs text-stone-500">Applied to this add-on price during booking.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Duration</label>
                    <input v-model="form.duration" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50" placeholder="e.g. 2 hours">
                </div>
                <div v-if="isActionCategory">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Due Date Before the Event</label>
                    <input v-model="form.due_days_before_event" type="number" min="0" step="1" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50" placeholder="Number of days">
                    <p class="mt-1 text-xs text-stone-500">Creates a booking task due this many days before the event date.</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Public Display</label>
                    <label class="flex min-h-[42px] items-center gap-3 rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-stone-200">
                        <input v-model="form.is_publicly_displayed" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-slate-950 text-emerald-300 focus:ring-emerald-300">
                        <span>Show in booking create</span>
                    </label>
                    <p class="mt-1 text-xs text-stone-500">Off by default for new add-ons.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Description</label>
                    <textarea v-model="form.description" rows="4" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none transition focus:border-emerald-300/50" />
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Image</label>
                    <input ref="photoInput" type="file" accept="image/*" class="block w-full rounded-xl border border-dashed border-white/15 bg-slate-950/70 px-3 py-2.5 text-sm text-stone-300 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-300 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-stone-950" :class="firstError(validationErrors, 'photo') ? 'border-rose-300/60' : ''">
                    <p v-if="firstError(validationErrors, 'photo')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(validationErrors, 'photo') }}</p>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-xl bg-emerald-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-emerald-200 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Create add-on' }}
                </button>
            </div>
        </form>
    </section>
</template>
