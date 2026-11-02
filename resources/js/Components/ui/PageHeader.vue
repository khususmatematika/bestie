<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-ink">{{ title }}</h1>
            <p v-if="description" class="mt-1 max-w-2xl text-sm text-ink-soft">{{ description }}</p>
        </div>
        <div v-if="$slots.default" class="flex flex-wrap items-center gap-2"><slot /></div>
    </div>
</template>
