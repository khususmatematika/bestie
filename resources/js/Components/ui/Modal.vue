<script setup lang="ts">
import { X } from '@lucide/vue';
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description?: string;
        size?: 'sm' | 'md' | 'lg' | 'xl';
        closeable?: boolean;
    }>(),
    { description: undefined, size: 'md', closeable: true },
);

const emit = defineEmits<{ close: [] }>();

const panel = ref<HTMLElement | null>(null);
let previouslyFocused: HTMLElement | null = null;

const widths = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl' } as const;

function close(): void {
    if (props.closeable) emit('close');
}

function onKey(event: KeyboardEvent): void {
    if (event.key === 'Escape') close();
}

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            previouslyFocused = document.activeElement as HTMLElement | null;
            document.addEventListener('keydown', onKey);
            document.body.style.overflow = 'hidden';
            await nextTick();
            const focusable = panel.value?.querySelector<HTMLElement>('input, select, textarea, button:not([data-modal-close])');
            (focusable ?? panel.value)?.focus();
        } else {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
            previouslyFocused?.focus?.();
        }
    },
);

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4">
                <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" @click="close" />
                <div
                    ref="panel"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title"
                    tabindex="-1"
                    class="card relative max-h-[92vh] w-full overflow-y-auto rounded-b-none shadow-pop focus:outline-none sm:rounded-b-[1rem]"
                    :class="widths[size]"
                >
                    <div class="flex items-start justify-between gap-4 border-b border-line px-6 py-4">
                        <div>
                            <h2 class="text-base font-bold text-ink">{{ title }}</h2>
                            <p v-if="description" class="mt-0.5 text-sm text-ink-soft">{{ description }}</p>
                        </div>
                        <button v-if="closeable" type="button" class="btn-icon -mr-2" data-modal-close aria-label="Tutup" @click="close">
                            <X class="size-4" />
                        </button>
                    </div>
                    <div class="px-6 py-5"><slot /></div>
                    <div v-if="$slots.footer" class="flex flex-wrap items-center justify-end gap-2 border-t border-line px-6 py-4">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
