<script setup lang="ts">
import { ChevronDown, CircleCheck, CopyCheck } from '@lucide/vue';
import { ref } from 'vue';
import MathText from './MathText.vue';

export interface SimilarMatch {
    score: number;
    level: 'tinggi' | 'sedang' | 'rendah';
    source: 'arsip' | 'fix_lama' | 'periode_ini';
    label: string;
    number: number;
    type: string | null;
    stem: string;
    options: Record<string, string>;
}

defineProps<{ matches: SimilarMatch[] }>();

const open = ref<number | null>(null);

const tone = {
    tinggi: 'bg-danger-50 text-danger-700 dark:bg-danger-500/15 dark:text-danger-500',
    sedang: 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-500',
    rendah: 'bg-surface-2 text-ink-soft',
} as const;
</script>

<template>
    <section class="card p-6" aria-labelledby="similar-title">
        <div class="flex items-center gap-2">
            <CopyCheck class="size-5 text-primary-600" />
            <h2 id="similar-title" class="text-base font-bold text-ink">Kemiripan dengan arsip &amp; paket lain</h2>
        </div>
        <p class="mt-1 text-xs text-ink-faint">Dibandingkan dengan arsip tahun lalu, paket Fix semester lama, dan paket lain periode ini (mapel yang sama). Hanya penanda bantu.</p>

        <p v-if="!matches.length" class="mt-4 flex items-center gap-2 text-sm font-semibold text-success-700 dark:text-success-500">
            <CircleCheck class="size-4" /> Tidak ditemukan soal yang mirip (di bawah 40%).
        </p>

        <ul v-else class="mt-4 space-y-2">
            <li v-for="(m, i) in matches" :key="i" class="rounded-xl border border-line">
                <button type="button" class="flex w-full items-center gap-3 px-3 py-2.5 text-left" :aria-expanded="open === i" @click="open = open === i ? null : i">
                    <span class="rounded-full px-2.5 py-1 text-xs font-extrabold tabular-nums" :class="tone[m.level]">{{ m.score }}%</span>
                    <span class="min-w-0 flex-1 text-sm text-ink"><span class="font-semibold">{{ m.label }}</span> · No. {{ m.number }}</span>
                    <ChevronDown class="size-4 shrink-0 text-ink-faint transition" :class="{ 'rotate-180': open === i }" />
                </button>
                <div v-if="open === i" class="space-y-2 border-t border-line bg-surface-2/40 p-3 font-serif text-sm text-ink">
                    <p><MathText :text="m.stem" /></p>
                    <ol v-if="Object.keys(m.options).length" class="space-y-1">
                        <li v-for="(t, letter) in m.options" :key="letter"><span class="mr-1.5 font-sans font-bold text-primary-700 dark:text-primary-300">{{ letter }}.</span><MathText :text="t" /></li>
                    </ol>
                </div>
            </li>
        </ul>
    </section>
</template>
