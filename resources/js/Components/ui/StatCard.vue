<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    label: string;
    value: number | string;
    tone?: 'primary' | 'accent' | 'warning' | 'success';
    /** Bila diisi, seluruh kartu menjadi tautan. */
    href?: string;
    /** Keterangan kecil di bawah label. */
    hint?: string;
}>();

const tones = {
    primary: 'bg-primary-50 text-primary-600 dark:bg-primary-500/15 dark:text-primary-300',
    accent: 'bg-accent-50 text-accent-600 dark:bg-accent-500/15 dark:text-accent-100',
    warning: 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-500',
    success: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
} as const;
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="card flex items-center gap-4 p-5"
        :class="href ? 'transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-pop focus-visible:outline-2 focus-visible:outline-primary-500' : ''"
    >
        <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl" :class="tones[tone ?? 'primary']">
            <slot />
        </div>
        <div class="min-w-0">
            <p class="text-2xl font-extrabold tabular-nums text-ink">{{ value }}</p>
            <p class="truncate text-sm text-ink-soft">{{ label }}</p>
            <p v-if="hint" class="mt-0.5 truncate text-xs text-ink-faint">{{ hint }}</p>
        </div>
    </component>
</template>
