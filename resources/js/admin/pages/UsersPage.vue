<script setup>
import { computed, ref } from 'vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';
import { firstError } from '../validation';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const { saving, fieldErrors, submitForm, deleteRecord } = useWorkspaceCrud();
const users = ref([...(props.data.users ?? [])]);
const roles = computed(() => props.data.roles ?? []);
const editingUser = ref(null);
const showModal = ref(false);
const form = ref({ name: '', email: '', role_id: '' });
const userToDelete = ref(null);
const showDeleteConfirm = ref(false);

const openCreate = () => {
    editingUser.value = null;
    form.value = { name: '', email: '', role_id: '' };
    showModal.value = true;
};

const openEdit = (user) => {
    editingUser.value = user;
    form.value = {
        name: user.name ?? '',
        email: user.email ?? '',
        role_id: user.role_id ? String(user.role_id) : '',
    };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingUser.value = null;
};

const saveUser = async () => {
    const formData = new FormData();
    formData.append('name', form.value.name ?? '');
    formData.append('email', form.value.email ?? '');
    formData.append('role_id', form.value.role_id ?? '');

    if (editingUser.value) {
        formData.append('_method', 'PUT');
    }

    const record = await submitForm({
        url: editingUser.value?.update_url ?? props.data.routes.store,
        method: 'post',
        data: formData,
    });

    const index = users.value.findIndex((user) => user.id === record.id);
    users.value = index >= 0
        ? users.value.map((user) => (user.id === record.id ? record : user))
        : [record, ...users.value];
    closeModal();
};

const askRemoveUser = (user) => {
    userToDelete.value = user;
    showDeleteConfirm.value = true;
};

const cancelRemoveUser = () => {
    userToDelete.value = null;
    showDeleteConfirm.value = false;
};

const removeUser = async () => {
    if (!userToDelete.value?.delete_url) {
        return;
    }

    await deleteRecord({ url: userToDelete.value.delete_url });
    users.value = users.value.filter((entry) => entry.id !== userToDelete.value.id);
    cancelRemoveUser();
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-cyan-200">Users Workspace</p>
        <h2 class="text-sm font-bold italic text-white">Manage workspace users</h2>
        <p class="text-sm text-stone-300">Add users, update their details, and assign their role.</p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
        <div class="mb-3 flex items-center justify-between gap-3 border-b border-white/10 px-2 pb-3">
            <div>
                <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Users</p>
                <h3 class="mt-1 text-sm font-semibold italic">{{ users.length }} user{{ users.length === 1 ? '' : 's' }}</h3>
            </div>
            <button type="button" class="rounded-xl bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200" @click="openCreate">
                Create user
            </button>
        </div>

        <div class="overflow-x-auto">
            <div class="min-w-[760px]">
                <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_10rem_8rem_10rem] gap-3 px-3 py-2 text-[11px] uppercase tracking-[0.2em] text-stone-500">
                    <span>Name</span>
                    <span>Email</span>
                    <span>Role</span>
                    <span>Joined</span>
                    <span>Actions</span>
                </div>
                <div v-for="user in users" :key="user.id" class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_10rem_8rem_10rem] items-center gap-3 border-t border-white/10 px-3 py-2">
                    <p class="truncate text-sm font-medium text-white">{{ user.name }}</p>
                    <p class="truncate text-sm text-stone-300">{{ user.email }}</p>
                    <span class="truncate text-sm text-cyan-100">{{ user.role_name }}</span>
                    <span class="text-sm text-stone-400">{{ user.created_at }}</span>
                    <div class="flex items-center gap-2">
                        <button type="button" class="rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/5" @click="openEdit(user)">Edit</button>
                        <button type="button" class="rounded-lg border border-rose-400/30 px-3 py-1.5 text-xs font-semibold text-rose-100 transition hover:bg-rose-400/10" @click="askRemoveUser(user)">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <transition name="modal">
        <div v-if="showModal" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm" @click.self="closeModal">
            <form class="w-full max-w-2xl rounded-2xl border border-white/10 bg-[#132035] shadow-2xl shadow-black/30" novalidate @submit.prevent="saveUser">
                <div class="flex items-center justify-between border-b border-white/10 px-5 py-4">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.3em] text-cyan-200">{{ editingUser ? 'Edit User' : 'New User' }}</p>
                        <h3 class="mt-1 text-sm font-semibold italic">User details</h3>
                    </div>
                    <button type="button" class="rounded-lg border border-white/10 px-3 py-2 text-sm text-stone-300 transition hover:bg-white/5" @click="closeModal">Close</button>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Name</label>
                        <input v-model="form.name" type="text" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-cyan-300/50" :class="firstError(fieldErrors, 'name') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(fieldErrors, 'name')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(fieldErrors, 'name') }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Email</label>
                        <input v-model="form.email" type="email" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-cyan-300/50" :class="firstError(fieldErrors, 'email') ? 'border-rose-300/60' : ''">
                        <p v-if="firstError(fieldErrors, 'email')" class="mt-1 text-xs font-medium text-rose-300">{{ firstError(fieldErrors, 'email') }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Role</label>
                        <select v-model="form.role_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-cyan-300/50">
                            <option value="">No role assigned</option>
                            <option v-for="role in roles" :key="role.id" :value="String(role.id)">{{ role.name }}</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-white/10 px-5 py-4">
                    <button type="button" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/5" @click="closeModal">Cancel</button>
                    <button type="submit" class="rounded-xl bg-cyan-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200 disabled:opacity-60" :disabled="saving">
                        {{ saving ? 'Saving...' : 'Save user' }}
                    </button>
                </div>
            </form>
        </div>
    </transition>

    <ConfirmDialog
        :open="showDeleteConfirm"
        title="Delete user?"
        :message="`Are you sure you want to delete the record ${userToDelete?.name || userToDelete?.email || 'this user'}?`"
        confirm-label="Delete user"
        :loading="saving"
        @cancel="cancelRemoveUser"
        @confirm="removeUser"
    />
</template>
