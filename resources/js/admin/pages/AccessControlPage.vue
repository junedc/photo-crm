<script setup>
import { ref } from 'vue';
import { useWorkspaceCrud } from '../useWorkspaceCrud';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});

const { saving, submitForm } = useWorkspaceCrud();
const users = ref([...(props.data.users ?? [])]);
const roles = ref([...(props.data.roles ?? [])]);

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
    roles.value = [...(record.roles ?? roles.value)];
};
</script>

<template>
    <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-white/10 bg-white/[0.03] px-5 py-3 shadow-lg shadow-black/10">
        <p class="text-[11px] uppercase tracking-[0.35em] text-amber-200">Access Control</p>
        <h2 class="text-sm font-bold italic text-white">Assign roles and screens</h2>
        <p class="text-sm text-stone-300">Choose each user role, then choose which screens each role can open.</p>
    </section>

    <form class="grid gap-4 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]" @submit.prevent="saveAccess">
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
</template>
