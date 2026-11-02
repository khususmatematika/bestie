<script setup lang="ts">
import { computed } from 'vue';
import MathInline from './MathInline.vue';

/**
 * Menampilkan teks soal: rumus \( LaTeX \) dirender KaTeX, penanda ⟦img:ID⟧ menjadi gambar pada posisinya.
 * Tidak memakai v-html; sisa teks selalu teks polos.
 */
const props = defineProps<{ text: string; images?: Record<string, string>; alt?: string }>();

type Part = { kind: 'text'; value: string } | { kind: 'math'; value: string } | { kind: 'img'; value: string };

const parts = computed<Part[]>(() => {
    const out: Part[] = [];
    const re = /\\\(([\s\S]+?)\\\)|⟦img:(\d+)⟧/g;
    let last = 0;
    let m: RegExpExecArray | null;
    const src = props.text ?? '';

    while ((m = re.exec(src)) !== null) {
        if (m.index > last) out.push({ kind: 'text', value: src.slice(last, m.index) });
        if (m[1] !== undefined) out.push({ kind: 'math', value: m[1] });
        else {
            const url = props.images?.[m[2]];
            if (url) out.push({ kind: 'img', value: url });
        }
        last = m.index + m[0].length;
    }

    if (last < src.length) out.push({ kind: 'text', value: src.slice(last) });

    return out;
});
</script>

<template>
    <span class="whitespace-pre-line">
        <template v-for="(p, i) in parts" :key="i">
            <MathInline v-if="p.kind === 'math'" :latex="p.value" />
            <img v-else-if="p.kind === 'img'" :src="p.value" :alt="alt ?? 'Gambar pada soal'" class="mx-1 my-1 inline-block max-h-72 max-w-full rounded border border-line bg-white align-middle" loading="lazy" />
            <template v-else>{{ p.value }}</template>
        </template>
    </span>
</template>
