<script setup>
import { computed, ref } from 'vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const { saving, submitForm, deleteRecord } = useWorkspaceCrud();
const users = ref([...(props.data.users ?? [])]);
const guestUsers = ref([...(props.data.guestUsers ?? [])]);
const roles = ref([...(props.data.roles ?? [])]);
const activeTab = ref('internal');
const guestForm = ref({
    email: '',
    role_id: '',
});
const guestToRemove = ref(null);
const showDeleteConfirm = ref(false);
const guestRoleOptions = computed(() => roles.value.filter((role) => (role.screen_access?.length ?? 0) > 0));
const guestLoginUrl = computed(() => props.data.routes.login ?? '');

const saveAccess = async () => {
    const payload = {
        users: users.value.map((user) => ({ id: user.id, role_id: user.role_id || null })),
        roles: roles.value.map((role) => ({ id: role.id, screen_access: role.screen_access ?? [] })),
    };

    const record = await submitForm({
        url: props.data.routes.update,
        method: 'post',
        data: payload,
    });

    users.value = [...(record.users ?? users.value)];
    guestUsers.value = [...(record.guestUsers ?? guestUsers.value)];
    roles.value = [...(record.roles ?? roles.value)];
};

const grantGuestAccess = async () => {
    const formData = new FormData();
    formData.append('email', guestForm.value.email ?? '');
    formData.append('role_id', guestForm.value.role_id ?? '');

    const record = await submitForm({
        url: props.data.routes.guestStore,
        method: 'post',
        data: formData,
    });

    const existingIndex = guestUsers.value.findIndex((entry) => entry.id === record.id);
    guestUsers.value = existingIndex >= 0
        ? guestUsers.value.map((entry) => (entry.id === record.id ? record : entry))
        : [record, ...guestUsers.value];

    guestForm.value = {
        email: '',
        role_id: '',
    };
};

const askRemoveGuestAccess = (guest) => {
    guestToRemove.value = guest;
    showDeleteConfirm.value = true;
};

const cancelRemoveGuestAccess = () => {
    guestToRemove.value = null;
    showDeleteConfirm.value = false;
};

const removeGuestAccess = async () => {
    if (!guestToRemove.value?.guest_delete_url) {
        return;
    }

    await deleteRecord({ url: guestToRemove.value.guest_delete_url });
    guestUsers.value = guestUsers.value.filter((entry) => entry.id !== guestToRemove.value.id);
    cancelRemoveGuestAccess();
};

const copyGuestLoginUrl = async () => {
    if (!guestLoginUrl.value || typeof navigator === 'undefined' || !navigator.clipboard?.writeText) {
        return;
    }

    await navigator.clipboard.writeText(guestLoginUrl.value);
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-amber-200">Access Control</p>
        <h2 class="text-sm font-bold italic text-white">Assign roles and screens</h2>
        <p class="text-sm text-stone-300">Choose each user role, then choose which screens each role can open.</p>
    </section>

    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
        <div class="flex items-center gap-2 border-b border-white/10 px-2 pb-3">
            <button type="button" class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition" :class="activeTab === 'internal' ? 'border-amber-300/40 bg-amber-300/10 text-white' : 'border-white/10 text-stone-300 hover:bg-white/5'" @click="activeTab = 'internal'">
                Internal Access
            </button>
            <button type="button" class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition" :class="activeTab === 'guests' ? 'border-cyan-300/40 bg-cyan-300/10 text-white' : 'border-white/10 text-stone-300 hover:bg-white/5'" @click="activeTab = 'guests'">
                Guest Access
            </button>
        </div>

        <form v-if="activeTab === 'internal'" class="mt-3 grid gap-4 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]" @submit.prevent="saveAccess">
            <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
                <div class="border-b border-white/10 px-2 pb-3">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">User Roles</p>
                    <h3 class="mt-1 text-sm font-semibold italic">Assign a role to each user</h3>
                </div>
                <div class="mt-3 space-y-2">
                    <label v-for="user in users" :key="user.id" class="grid gap-2 rounded-xl border border-white/10 bg-slate-950/50 p-3 sm:grid-cols-[minmax(0,1fr)_14rem] sm:items-center">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-white">{{ user.name }}</span>
                            <span class="mt-1 block truncate text-xs text-stone-400">{{ user.email }}</span>
                        </span>
                        <select v-model="user.role_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-amber-300/50">
                            <option :value="null">No role assigned</option>
                            <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                        </select>
                    </label>
                </div>
            </section>

            <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
                <div class="border-b border-white/10 px-2 pb-3">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Screen Access</p>
                    <h3 class="mt-1 text-sm font-semibold italic">Role permissions</h3>
                </div>
                <div class="mt-3 space-y-3">
                    <div v-for="role in roles" :key="role.id" class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-white">{{ role.name }}</p>
                                <p class="mt-1 text-xs text-stone-400">{{ role.screen_access?.length ?? 0 }} screen{{ role.screen_access?.length === 1 ? '' : 's' }}</p>
                            </div>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <label v-for="screen in data.screens" :key="`${role.id}-${screen.key}`" class="flex items-center gap-3 rounded-lg border border-white/10 px-3 py-2 text-sm text-stone-200">
                                <input v-model="role.screen_access" :value="screen.key" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-slate-950 text-amber-300">
                                <span>{{ screen.label }}</span>
                            </label>
                        </div>
                    </div>
                    <p v-if="!roles.length" class="rounded-xl border border-dashed border-white/15 px-4 py-5 text-sm text-stone-400">Create a role first, then assign screen access here.</p>
                </div>
            </section>

            <div class="xl:col-span-2 flex justify-end">
                <button type="submit" class="rounded-xl bg-amber-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-200 disabled:opacity-60" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Save access' }}
                </button>
            </div>
        </form>

        <div v-else class="mt-3 grid gap-4 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
                <div class="border-b border-white/10 px-2 pb-3">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Grant Guest Access</p>
                    <h3 class="mt-1 text-sm font-semibold italic">Allow a customer email to log in as a guest</h3>
                </div>
                <div class="mt-3 rounded-xl border border-cyan-300/20 bg-cyan-300/10 p-3">
                    <p class="text-[11px] uppercase tracking-[0.2em] text-cyan-100">Guest Login URL</p>
                    <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center">
                        <a :href="guestLoginUrl" class="min-w-0 flex-1 truncate rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white hover:border-cyan-300/40" target="_blank" rel="noopener noreferrer">
                            {{ guestLoginUrl }}
                        </a>
                        <button type="button" class="rounded-lg border border-cyan-300/30 px-3 py-2 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-300/10" @click="copyGuestLoginUrl">
                            Copy URL
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-stone-300">Send this tenant login link to the customer, then they can sign in with their granted email and receive the one-time code.</p>
                </div>
                <form class="mt-3 space-y-4" @submit.prevent="grantGuestAccess">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Customer Email</label>
                        <input v-model="guestForm.email" type="email" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-cyan-300/50">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-[0.2em] text-stone-400">Guest Role</label>
                        <select v-model="guestForm.role_id" class="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2.5 text-sm text-white outline-none focus:border-cyan-300/50">
                            <option value="">Select guest role</option>
                            <option v-for="role in guestRoleOptions" :key="role.id" :value="String(role.id)">{{ role.name }}</option>
                        </select>
                        <p class="mt-2 text-xs text-stone-400">Guests use the same email-login flow, but only see the screens allowed by the selected role.</p>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-xl bg-cyan-300 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200 disabled:opacity-60" :disabled="saving">
                            {{ saving ? 'Granting...' : 'Grant guest access' }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-3">
                <div class="border-b border-white/10 px-2 pb-3">
                    <p class="text-[11px] uppercase tracking-[0.3em] text-stone-400">Granted Guests</p>
                    <h3 class="mt-1 text-sm font-semibold italic">{{ guestUsers.length }} guest{{ guestUsers.length === 1 ? '' : 's' }}</h3>
                </div>
                <div class="mt-3 space-y-2">
                    <div v-for="guest in guestUsers" :key="guest.id" class="grid gap-2 rounded-xl border border-white/10 bg-slate-950/50 p-3 sm:grid-cols-[minmax(0,1fr)_12rem_8rem] sm:items-center">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-white">{{ guest.email }}</p>
                            <p class="mt-1 truncate text-xs text-stone-400">{{ guest.role_name }}</p>
                        </div>
                        <p class="text-sm text-stone-300">{{ guest.created_at }}</p>
                        <div class="flex justify-end">
                            <button type="button" class="rounded-lg border border-rose-400/30 px-3 py-1.5 text-xs font-semibold text-rose-100 transition hover:bg-rose-400/10" @click="askRemoveGuestAccess(guest)">
                                Remove
                            </button>
                        </div>
                    </div>
                    <p v-if="!guestUsers.length" class="rounded-xl border border-dashed border-white/15 px-4 py-5 text-sm text-stone-400">No guest access has been granted yet.</p>
                </div>
            </section>
        </div>
    </section>

    <ConfirmDialog
        :open="showDeleteConfirm"
        title="Remove guest access?"
        :message="`Are you sure you want to delete the record ${guestToRemove?.name || guestToRemove?.email || 'this guest access'}?`"
        confirm-label="Remove access"
        :loading="saving"
        @cancel="cancelRemoveGuestAccess"
        @confirm="removeGuestAccess"
    />
</template>
