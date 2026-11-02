<script setup lang="ts">
import { computed } from 'vue';
import type { ItemDetail } from '@/types';
import MathText from './MathText.vue';

const props = defineProps<{ item: ItemDetail }>();

const options = computed(() => Object.entries(props.item.options));
const matching = computed(() => {
    if (props.item.type !== 'menjodohkan') return null;
    const pairs: Record<string, string> = {};
    const distractors: string[] = [];
    for (const raw of props.item.answer_key.split(/\r?\n/)) {
        const line = raw.trim();
        const d = /^(?:pilihan\s+)?pengecoh\s*[:：]\s*(.*)$/i.exec(line);
        if (d) {
            distractors.push(...d[1].split(';').map((x) => x.trim()).filter(Boolean));
            continue;
        }
        for (const part of line.split(';')) {
            const m = /^([A-Ea-e])\s*[=:]\s*(.+)$/s.exec(part.trim());
            if (m) pairs[m[1].toUpperCase()] = m[2].trim();
        }
    }
    const choices = [...new Set([...Object.values(pairs), ...distractors])].sort((a, b) => a.localeCompare(b, 'id', { numeric: true, sensitivity: 'base' }));
    return { pairs, distractors, choices };
});
// Benar/Salah beberapa pernyataan: kunci "A=B; B=S".
const tfKey = computed<Record<string, string>>(() => {
    if (props.item.type !== 'benar_salah') return {};
    const out: Record<string, string> = {};
    for (const m of props.item.answer_key.matchAll(/([A-E])\s*=\s*([BS])/g)) out[m[1]] = m[2];
    return out;
});
const keyText = computed(() => {
    if (props.item.type !== 'benar_salah') return props.item.answer_key;
    if (Object.keys(tfKey.value).length) return Object.entries(tfKey.value).map(([l, v]) => `${l} = ${v === 'B' ? 'Benar' : 'Salah'}`).join('\n');
    return ({ B: 'Benar', S: 'Salah' } as Record<string, string>)[props.item.answer_key] ?? props.item.answer_key;
});
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
                <span v-if="tfKey[letter]" class="ml-auto h-fit shrink-0 rounded-md px-2 py-0.5 font-sans text-xs font-bold" :class="tfKey[letter] === 'B' ? 'bg-success-50 text-success-700 dark:bg-success-500/15' : 'bg-danger-50 text-danger-700 dark:bg-danger-500/15'">{{ tfKey[letter] === 'B' ? 'Benar' : 'Salah' }}</span>
            </li>
        </ol>

        <!-- Menjodohkan: pilihan jawaban = pasangan benar + pengecoh -->
        <div v-if="matching && matching.choices.length" class="rounded-xl border border-line p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Pilihan jawaban ({{ matching.choices.length }}, termasuk {{ matching.distractors.length }} pengecoh)</p>
            <ul class="mt-2 flex flex-wrap gap-2">
                <li
                    v-for="c in matching.choices"
                    :key="c"
                    class="rounded-lg border px-2.5 py-1 font-serif text-sm"
                    :class="matching.distractors.includes(c) ? 'border-dashed border-warning-500/60 bg-warning-50 text-ink dark:bg-warning-500/10' : 'border-success-500/60 bg-success-50 text-ink dark:bg-success-500/10'"
                >
                    {{ c }}<span v-if="matching.distractors.includes(c)" class="ml-1 font-sans text-[10px] font-bold uppercase text-warning-700 dark:text-warning-500">pengecoh</span>
                </li>
            </ul>
        </div>

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
