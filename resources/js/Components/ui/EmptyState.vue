<script setup lang="ts">
import { Inbox } from '@lucide/vue';

defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
        <div class="mb-4 flex size-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-500/15 dark:text-primary-300">
            <slot name="icon"><Inbox class="size-7" /></slot>
        </div>
        <h3 class="text-base font-bold text-ink">{{ title }}</h3>
        <p v-if="description" class="mt-1 max-w-sm text-sm text-ink-soft">{{ description }}</p>
        <div v-if="$slots.default" class="mt-5"><slot /></div>
    </div>
</template>
