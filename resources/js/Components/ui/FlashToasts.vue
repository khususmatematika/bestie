<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, X } from '@lucide/vue';
import { ref, watch } from 'vue';

interface Toast {
    id: number;
    kind: 'success' | 'error';
    text: string;
}

const page = usePage();
const toasts = ref<Toast[]>([]);
let counter = 0;

function push(kind: Toast['kind'], text: string): void {
    const id = ++counter;
    toasts.value.push({ id, kind, text });
    setTimeout(() => dismiss(id), kind === 'error' ? 8000 : 5000);
}

function dismiss(id: number): void {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

// Dipicu setiap respons baru (objek flash selalu baru), bukan hanya saat teks berubah.
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) push('success', flash.success);
        if (flash?.error) push('error', flash.error);
    },
    { immediate: true },
);
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex flex-col items-center gap-2 px-4" aria-live="polite">
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                role="status"
                class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-xl border bg-surface px-4 py-3 shadow-pop"
                :class="toast.kind === 'success' ? 'border-success-500/40' : 'border-danger-500/40'"
            >
                <CircleCheck v-if="toast.kind === 'success'" class="mt-0.5 size-5 shrink-0 text-success-500" />
                <CircleAlert v-else class="mt-0.5 size-5 shrink-0 text-danger-500" />
                <p class="flex-1 text-sm font-medium text-ink">{{ toast.text }}</p>
                <button type="button" class="btn-icon -m-1 size-6" aria-label="Tutup pemberitahuan" @click="dismiss(toast.id)">
                    <X class="size-3.5" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
