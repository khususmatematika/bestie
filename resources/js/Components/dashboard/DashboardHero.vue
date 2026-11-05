<script setup lang="ts">
defineProps<{ eyebrow?: string; title: string; description?: string }>();
</script>

<template>
    <section class="relative isolate mb-6 overflow-hidden rounded-3xl bg-gradient-to-br from-primary-600 via-primary-700 to-primary-900 p-6 text-white shadow-soft sm:p-8">
        <!-- dekorasi lembut, tidak membawa informasi -->
        <div class="pointer-events-none absolute -right-16 -top-20 -z-10 size-64 rounded-full bg-white/10" aria-hidden="true" />
        <div class="pointer-events-none absolute -bottom-24 right-24 -z-10 size-56 rounded-full bg-accent-500/25" aria-hidden="true" />

        <div class="flex flex-col gap-6 @3xl:flex-row @3xl:items-center @3xl:justify-between">
            <div class="min-w-0 max-w-2xl">
                <p v-if="eyebrow" class="text-xs font-bold uppercase tracking-widest text-white/70">{{ eyebrow }}</p>
                <h1 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">{{ title }}</h1>
                <p v-if="description" class="mt-2 text-sm leading-relaxed text-white/80">{{ description }}</p>
                <div v-if="$slots.default" class="mt-5 flex flex-wrap items-center gap-2.5"><slot /></div>
            </div>
            <div v-if="$slots.aside" class="shrink-0 rounded-2xl bg-white/10 p-4 ring-1 ring-white/20 backdrop-blur-sm @3xl:min-w-56"><slot name="aside" /></div>
        </div>
    </section>
</template>
