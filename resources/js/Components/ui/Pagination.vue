<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import type { Paginated } from '@/types';

const props = defineProps<{ meta: Pick<Paginated<unknown>, 'links' | 'from' | 'to' | 'total' | 'last_page'> }>();

// Tautan pertama = "Sebelumnya", terakhir = "Berikutnya"; sisanya nomor halaman.
const prev = computed(() => props.meta.links[0]);
const next = computed(() => props.meta.links[props.meta.links.length - 1]);
const numbers = computed(() => props.meta.links.slice(1, -1));

function go(url: string | null): void {
    if (url) router.visit(url, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <div v-if="meta.last_page > 1 || meta.total > 0" class="flex flex-col items-center justify-between gap-3 border-t border-line px-4 py-3 sm:flex-row">
        <p class="text-xs text-ink-faint">
            Menampilkan <span class="font-semibold text-ink">{{ meta.from ?? 0 }}–{{ meta.to ?? 0 }}</span> dari
            <span class="font-semibold text-ink">{{ meta.total }}</span> data
        </p>
        <nav v-if="meta.last_page > 1" class="flex items-center gap-1" aria-label="Halaman">
            <button type="button" class="btn-icon" :disabled="!prev?.url" aria-label="Halaman sebelumnya" @click="go(prev?.url ?? null)">
                <ChevronLeft class="size-4" />
            </button>
            <button
                v-for="(link, i) in numbers"
                :key="i"
                type="button"
                :disabled="!link.url"
                :aria-current="link.active ? 'page' : undefined"
                class="inline-flex size-8 items-center justify-center rounded-lg text-xs font-semibold transition"
                :class="link.active ? 'bg-primary-600 text-white' : 'text-ink-soft hover:bg-surface-2'"
                @click="go(link.url)"
                v-text="link.label"
            />
            <button type="button" class="btn-icon" :disabled="!next?.url" aria-label="Halaman berikutnya" @click="go(next?.url ?? null)">
                <ChevronRight class="size-4" />
            </button>
        </nav>
    </div>
</template>
