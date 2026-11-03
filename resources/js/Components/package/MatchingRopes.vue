<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import MathText from './MathText.vue';

/**
 * Soal menjodohkan: pernyataan (kiri) dihubungkan dengan jawaban benarnya (kanan) memakai "tali".
 * Pilihan pengecoh tidak bertali. Garis digambar SVG dan dihitung ulang saat ukuran/gambar berubah.
 */
const props = defineProps<{
    statements: [string, string][];
    choices: string[];
    pairs: Record<string, string>;
    distractors: string[];
    images?: Record<string, string>;
}>();

const COLORS = ['#2563eb', '#db2777', '#059669', '#d97706', '#7c3aed'];

const root = ref<HTMLElement | null>(null);
const leftEls = ref<Record<string, HTMLElement>>({});
const rightEls = ref<Record<string, HTMLElement>>({});
const ropes = ref<{ letter: string; d: string; color: string; x1: number; y1: number; x2: number; y2: number }[]>([]);
const size = ref({ w: 0, h: 0 });
let observer: ResizeObserver | null = null;

function setLeft(letter: string, el: unknown): void {
    if (el instanceof HTMLElement) leftEls.value[letter] = el;
}

function setRight(choice: string, el: unknown): void {
    if (el instanceof HTMLElement) rightEls.value[choice] = el;
}

function draw(): void {
    const box = root.value?.getBoundingClientRect();
    if (!box) return;

    size.value = { w: box.width, h: box.height };
    const out: typeof ropes.value = [];

    props.statements.forEach(([letter], index) => {
        const value = props.pairs[letter];
        const a = leftEls.value[letter]?.getBoundingClientRect();
        const b = value !== undefined ? rightEls.value[value]?.getBoundingClientRect() : undefined;
        if (!a || !b) return;

        const x1 = a.right - box.left;
        const y1 = a.top + a.height / 2 - box.top;
        const x2 = b.left - box.left;
        const y2 = b.top + b.height / 2 - box.top;
        const mid = (x1 + x2) / 2;
        out.push({ letter, d: `M ${x1} ${y1} C ${mid} ${y1}, ${mid} ${y2}, ${x2} ${y2}`, color: COLORS[index % COLORS.length], x1, y1, x2, y2 });
    });

    ropes.value = out;
}

function schedule(): void {
    void nextTick(draw);
}

onMounted(() => {
    schedule();
    if (root.value && typeof ResizeObserver !== 'undefined') {
        observer = new ResizeObserver(schedule);
        observer.observe(root.value);
    }
    window.addEventListener('resize', schedule);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    window.removeEventListener('resize', schedule);
});

watch(() => [props.statements, props.choices, props.pairs], schedule, { deep: true });
</script>

<template>
    <div ref="root" class="relative rounded-xl border border-line p-3" @load.capture="schedule">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-faint">Pasangan jawaban (tali menghubungkan pernyataan dengan jawaban benar)</p>
        <svg class="pointer-events-none absolute inset-0 h-full w-full" :viewBox="`0 0 ${size.w} ${size.h}`" aria-hidden="true">
            <g v-for="r in ropes" :key="r.letter" :stroke="r.color" :fill="r.color">
                <path :d="r.d" fill="none" stroke-width="2.5" stroke-linecap="round" />
                <circle :cx="r.x1" :cy="r.y1" r="4.5" stroke="none" />
                <circle :cx="r.x2" :cy="r.y2" r="4.5" stroke="none" />
            </g>
        </svg>
        <div class="relative grid grid-cols-[1fr_minmax(2.5rem,22%)_1fr] items-center gap-y-2">
            <ul class="col-start-1 row-start-1 space-y-2">
                <li v-for="[letter, text] in statements" :key="letter" class="flex min-h-10 items-center">
                    <span :ref="(el) => setLeft(letter, el)" class="flex w-full gap-2 rounded-lg border border-line bg-surface px-2.5 py-1.5 font-serif text-sm">
                        <b class="font-sans text-primary-700 dark:text-primary-300">{{ letter }}.</b>
                        <MathText :text="String(text)" :images="images" :alt="`Gambar pernyataan ${letter}`" />
                    </span>
                </li>
            </ul>
            <ul class="col-start-3 row-start-1 space-y-2">
                <li v-for="c in choices" :key="c" class="flex min-h-10 items-center">
                    <span
                        :ref="(el) => setRight(c, el)"
                        class="w-full rounded-lg border px-2.5 py-1.5 font-serif text-sm"
                        :class="distractors.includes(c) ? 'border-dashed border-warning-500/60 bg-warning-50 dark:bg-warning-500/10' : 'border-success-500/60 bg-success-50 dark:bg-success-500/10'"
                    >
                        <MathText :text="c" :images="images" alt="Gambar pilihan jawaban" />
                        <span v-if="distractors.includes(c)" class="ml-1 font-sans text-[10px] font-bold uppercase text-warning-700 dark:text-warning-500">pengecoh</span>
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
