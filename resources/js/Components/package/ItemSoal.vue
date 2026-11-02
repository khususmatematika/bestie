<script setup lang="ts">
import { computed } from 'vue';
import type { ItemDetail } from '@/types';
import MathText from './MathText.vue';

const props = defineProps<{ item: ItemDetail }>();

const options = computed(() => Object.entries(props.item.options));
const keyText = computed(() => (props.item.type === 'benar_salah' ? ({ B: 'Benar', S: 'Salah' } as Record<string, string>)[props.item.answer_key] ?? props.item.answer_key : props.item.answer_key));
</script>

<template>
    <div class="space-y-4">
        <!-- Rumus dirender KaTeX dan gambar inline dari penanda; sisa teks polos (tanpa v-html) -->
        <p class="font-serif text-[1.0625rem] leading-relaxed text-ink">
            <MathText v-if="item.stem" :text="item.stem" :images="item.image_map" :alt="`Gambar soal nomor ${item.number}`" />
            <template v-else>Teks soal belum diisi.</template>
        </p>

        <!-- Gambar soal (disajikan dari penyimpanan privat lewat controller terotorisasi) -->
        <div v-if="item.images.length" class="flex flex-wrap gap-3">
            <img v-for="(src, i) in item.images" :key="src" :src="src" :alt="`Gambar soal nomor ${item.number}, ${i + 1}`" class="max-h-72 max-w-full rounded-lg border border-line bg-white object-contain" loading="lazy" />
        </div>

        <ol v-if="options.length" class="space-y-2">
            <li
                v-for="[letter, text] in options"
                :key="letter"
                class="flex gap-3 rounded-xl border px-3.5 py-2.5 font-serif text-[1rem] leading-relaxed"
                :class="item.answer_key.split(',').includes(letter) ? 'border-success-500/60 bg-success-50 dark:bg-success-500/10' : 'border-line'"
            >
                <span class="font-sans text-sm font-bold text-primary-700 dark:text-primary-300">{{ letter }}.</span>
                <span class="text-ink"><MathText :text="String(text)" :images="item.image_map" :alt="`Gambar pilihan ${letter}`" /></span>
            </li>
        </ol>

        <div class="grid gap-3 sm:grid-cols-2">
            <div v-if="item.answer_key" class="rounded-xl bg-surface-2 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Kunci jawaban</p>
                <p class="mt-0.5 whitespace-pre-line text-sm font-semibold text-ink">{{ keyText }}</p>
            </div>
            <div v-if="item.scoring_guide" class="rounded-xl bg-surface-2 p-3 sm:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Pedoman penskoran</p>
                <p class="mt-0.5 whitespace-pre-line text-sm text-ink">{{ item.scoring_guide }}</p>
            </div>
        </div>
    </div>
</template>
