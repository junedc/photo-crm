<script setup>
defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Are you sure?',
    },
    message: {
        type: String,
        default: '',
    },
    confirmLabel: {
        type: String,
        default: 'Confirm',
    },
    cancelLabel: {
        type: String,
        default: 'Cancel',
    },
    tone: {
        type: String,
        default: 'danger',
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['cancel', 'confirm']);
</script>

<template>
    <Teleport to="body">
        <transition name="confirm-dialog">
            <div v-if="open" class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur-sm" @click.self="$emit('cancel')">
                <section class="w-full max-w-md overflow-hidden rounded-3xl border border-white/10 bg-[#132035] shadow-2xl shadow-black/40">
                    <div class="border-b border-white/10 px-5 py-4">
                        <p class="text-[11px] uppercase tracking-[0.3em]" :class="tone === 'danger' ? 'text-rose-200' : 'text-cyan-200'">
                            Please Confirm
                        </p>
                        <h3 class="mt-2 text-lg font-semibold text-white">{{ title }}</h3>
                    </div>

                    <div class="px-5 py-4">
                        <p class="text-sm leading-6 text-stone-300">{{ message }}</p>
                    </div>

                    <div class="flex flex-wrap justify-end gap-3 border-t border-white/10 bg-slate-950/30 px-5 py-4">
                        <button type="button" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loading" @click="$emit('cancel')">
                            {{ cancelLabel }}
                        </button>
                        <button type="button" class="rounded-xl px-5 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60" :class="tone === 'danger' ? 'bg-rose-300 text-slate-950 hover:bg-rose-200' : 'bg-cyan-300 text-slate-950 hover:bg-cyan-200'" :disabled="loading" @click="$emit('confirm')">
                            {{ loading ? 'Working...' : confirmLabel }}
                        </button>
                    </div>
                </section>
            </div>
        </transition>
    </Teleport>
</template>

<style scoped>
.confirm-dialog-enter-active,
.confirm-dialog-leave-active {
    transition: opacity 0.16s ease, transform 0.16s ease;
}

.confirm-dialog-enter-from,
.confirm-dialog-leave-to {
    opacity: 0;
    transform: scale(0.98);
}
</style>
