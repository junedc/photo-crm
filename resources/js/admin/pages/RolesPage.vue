<script setup>
import { ref } from 'vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';
import { firstError } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const { saving, fieldErrors, submitForm, deleteRecord } = useWorkspaceCrud();
const roles = ref([...(props.data.roles ?? [])]);
const editingRole = ref(null);
const showModal = ref(false);
const form = ref({ name: '', description: '', screen_access: [] });

const openCreate = () => {
    editingRole.value = null;
    form.value = { name: '', description: '', screen_access: props.data.screens.map((screen) => screen.key) };
    showModal.value = true;
};

const openEdit = (role) => {
    editingRole.value = role;
    form.value = {
        name: role.name ?? '',
        description: role.description ?? '',
        screen_access: [...(role.screen_access ?? [])],
    };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingRole.value = null;
};

const saveRole = async () => {
    const formData = new FormData();
    formData.append('name', form.value.name ?? '');
    formData.append('description', form.value.description ?? '');
    form.value.screen_access.forEach((screen) => formData.append('screen_access[]', screen));

    if (editingRole.value) {
        formData.append('_method', 'PUT');
    }

    const record = await submitForm({
        url: editingRole.value?.update_url ?? props.data.routes.store,
        method: 'post',
        data: formData,
    });

    const index = roles.value.findIndex((role) => role.id === record.id);
    roles.value = index >= 0
        ? roles.value.map((role) => (role.id === record.id ? record : role))
        : [record, ...roles.value];
    closeModal();
};

const removeRole = async (role) => {
    await deleteRecord({ url: role.delete_url });
    roles.value = roles.value.filter((entry) => entry.id !== role.id);
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-violet-200">Roles Workspace</p>
        <h2 class="text-sm font-bold italic text-white">Manage access roles</h2>
        <p class="text-sm text-stone-300">Create roles and define which screens each role can access.</p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
        <div class="mb-3 flex items-center justify-between gap-3 border-b border-white/10 px-2 pb-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Roles</p>
                <h3 class="mt-1 text-sm font-semibold italic">{{ roles.length }} role{{ roles.length === 1 ? '' : 's' }}</h3>
            </div>
            <button type="button" class="rounded-xl bg-violet-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-violet-200" @click="openCreate">
                Create role
            </button>
        </div>

        <div class="overflow-x-auto">
            <div class="min-w-[760px]">
                <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)_8rem_8rem_10rem] gap-3 px-3 py-2 text-[11px] uppercase tracking-[0.2em] text-stone-500">
                    <span>Role</span>
                    <span>Description</span>
                    <span>Screens</span>
                    <span>Users</span>
                    <span>Actions</span>
                </div>
                <div v-for="role in roles" :key="role.id" class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)_8rem_8rem_10rem] items-center gap-3 border-t border-white/10 px-3 py-2">
                    <p class="truncate text-sm font-medium text-white">{{ role.name }}</p>
                    <p class="truncate text-sm text-stone-300">{{ role.description || 'No description' }}</p>
                    <span class="text-sm text-violet-100">{{ role.screen_access?.length ?? 0 }}</span>
                    <span class="text-sm text-stone-300">{{ role.users_count }}</span>
                    <div class="flex items-center gap-2">
                        <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/5" @click="openEdit(role)">Edit</button>
                        <button type="button" class="rounded-lg border border-rose-400/30 px-3 py-1.5 text-xs font-semibold text-rose-100 transition hover:bg-rose-400/10" @click="removeRole(role)">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <transition name="modal">
        <div v-if="showModal" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm" @click.self="closeModal">
            <form class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-white/10 bg-[#132035] shadow-2xl shadow-black/30" novalidate @submit.prevent="saveRole">
                <div class="sticky top-0 z-20 flex items-center justify-between border-b border-white/10 bg-[#132035] px-5 py-4">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.3em] text-violet-200">{{ editingRole ? 'Edit Role' : 'New Role' }}</p>
                        <h3 class="mt-1 text-sm font-semibold italic">Role details</h3>
                    </div>
                    <button type="button" class="rounded-lg border border-white/10 px-3 py-2 text-sm text-stone-300 transition hover:bg-white/5" @click="closeModal">Close</button>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Role name</label>
                        <input v-model="form.name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-violet-300/50" :class="firstError(fieldErrors, 'name') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(fieldErrors, 'name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(fieldErrors, 'name') }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Description</label>
                        <input v-model="form.description" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-violet-300/50">
                    </div>
                    <div class="sm:col-span-2 rounded-xl border border-white/10 bg-slate-950/50 p-3">
                        <p class="mb-3 text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Screen access</p>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <label v-for="screen in data.screens" :key="screen.key" class="flex items-center gap-3 rounded-lg border border-white/10 px-3 py-2 text-sm text-stone-200">
                                <input v-model="form.screen_access" :value="screen.key" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-slate-950 text-violet-300">
                                <span>{{ screen.label }}</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="sticky bottom-0 flex justify-end gap-3 border-t border-white/10 bg-[#132035] px-5 py-4">
                    <button type="button" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/5" @click="closeModal">Cancel</button>
                    <button type="submit" class="rounded-xl bg-violet-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-violet-200 disabled:opacity-60" :disabled="saving">
                        {{ saving ? 'Saving...' : 'Save role' }}
                    </button>
                </div>
            </form>
        </div>
    </transition>
</template>
