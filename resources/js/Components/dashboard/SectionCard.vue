<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';

defineProps<{ title: string; subtitle?: string; href?: string; linkLabel?: string; count?: number | string; flush?: boolean }>();
</script>

<template>
    <section class="card overflow-hidden">
        <header class="flex items-start justify-between gap-3 border-b border-line px-5 py-4 sm:px-6">
            <div class="min-w-0">
                <h2 class="flex items-center gap-2 text-base font-bold text-ink">
                    {{ title }}
                    <span v-if="count !== undefined" class="badge bg-surface-2 tabular-nums text-ink-soft">{{ count }}</span>
                </h2>
                <p v-if="subtitle" class="mt-0.5 text-xs text-ink-faint">{{ subtitle }}</p>
            </div>
            <Link v-if="href" :href="href" class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-primary-600 hover:underline">
                {{ linkLabel ?? 'Lihat semua' }} <ArrowRight class="size-3.5" />
            </Link>
        </header>
        <div :class="flush ? '' : 'p-5 sm:p-6'"><slot /></div>
    </section>
</template>
