<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const vendorList = ref([...(props.data.vendors ?? [])]);
const vendors = computed(() => vendorList.value);
const search = ref('');
const requestId = ref(0);
const loading = ref(false);

const fetchVendors = async () => {
    const currentRequest = ++requestId.value;
    loading.value = true;

    try {
        const params = new URLSearchParams({
            search: search.value.trim(),
        });

        const response = await fetch(`${props.data.routes.vendors}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok || currentRequest !== requestId.value) {
            return;
        }

        const payload = await response.json();
        vendorList.value = [...(payload.records ?? [])];
    } finally {
        if (currentRequest === requestId.value) {
            loading.value = false;
        }
    }
};

watch(search, () => {
    fetchVendors();
});
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-cyan-200">Vendors Workspace</p>
        <h2 class="text-sm font-bold italic text-white">Manage workspace vendors</h2>
        <p class="text-sm text-stone-300">
            Maintain supplier and collaborator records for your workspace in one dedicated page.
        </p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-cyan-200">Vendors</p>
                <h3 class="mt-1 text-sm font-semibold italic">Create and maintain records</h3>
            </div>
            <span class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 text-xs text-stone-300">{{ vendors.length }}</span>
        </div>

        <section class="rounded-2xl border border-white/10 bg-slate-950/40 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.25em] text-stone-500">Vendor List</p>
                    <p class="mt-1 text-xs text-stone-400">Contacts and services available for booking tasks and coordination.</p>
                </div>
                <div class="flex w-full flex-wrap items-center justify-end gap-2 sm:w-auto">
                    <input v-model="search" type="text" placeholder="Search vendors" class="w-full min-w-[220px] rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm text-white outline-none transition focus:border-cyan-300/50 sm:w-64">
                    <a :href="data.routes.create" class="rounded-xl bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200">
                        Add vendor
                    </a>
                </div>
            </div>

            <div class="mt-4 overflow-hidden rounded-2xl border border-white/10">
                <div class="grid grid-cols-[4rem_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,1fr)_10rem_minmax(0,1fr)_8rem] gap-3 bg-white/[0.03] px-3 py-2 text-[11px] uppercase tracking-[0.2em] text-stone-500">
                    <span>ID</span>
                    <span>Contact</span>
                    <span>Company</span>
                    <span>Services</span>
                    <span>Address</span>
                    <span>Mobile</span>
                    <span>Email</span>
                    <span>Active</span>
                </div>

                <a
                    v-for="vendor in vendors"
                    :key="vendor.id"
                    :href="vendor.show_url"
                    class="grid w-full grid-cols-[4rem_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,1fr)_10rem_minmax(0,1fr)_8rem] items-center gap-3 border-t border-white/10 px-3 py-2.5 text-left transition hover:bg-white/[0.03]"
                >
                    <p class="truncate text-sm text-stone-300">{{ vendor.id }}</p>
                    <p class="truncate text-sm text-white">{{ vendor.name }}</p>
                    <p class="truncate text-sm text-stone-300">{{ vendor.company_name || 'No company' }}</p>
                    <p class="truncate text-sm text-stone-300">{{ vendor.services_offered_label || 'No services' }}</p>
                    <p class="truncate text-sm text-stone-300">{{ vendor.address || 'No address' }}</p>
                    <p class="truncate text-sm text-stone-300">{{ vendor.mobile_number || 'No mobile' }}</p>
                    <p class="truncate text-sm text-stone-300">{{ vendor.email || 'No email' }}</p>
                    <span class="inline-flex h-7 w-fit items-center rounded-full border px-2.5 text-[11px] font-medium" :class="vendor.is_active ? 'border-emerald-300/20 bg-emerald-300/10 text-emerald-100' : 'border-white/10 bg-white/5 text-stone-300'">
                        {{ vendor.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </a>

                <div v-if="loading" class="border-t border-white/10 px-3 py-3 text-sm text-stone-400">
                    Loading vendors...
                </div>
                <div v-else-if="!vendors.length" class="border-t border-white/10 px-3 py-3 text-sm text-stone-400">
                    No vendors added yet.
                </div>
            </div>
        </section>
    </section>
</template>
