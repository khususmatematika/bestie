<script setup lang="ts">
import { computed } from 'vue';
import MathInline from './MathInline.vue';

/**
 * Menampilkan teks soal: rumus \( LaTeX \) dirender KaTeX, penanda ⟦img:ID⟧ menjadi gambar pada posisinya.
 * Tidak memakai v-html; sisa teks selalu teks polos.
 */
const props = defineProps<{ text: string; images?: Record<string, string>; alt?: string }>();

type Part = { kind: 'text'; value: string; b?: boolean; i?: boolean; u?: boolean; sub?: boolean; sup?: boolean } | { kind: 'math'; value: string } | { kind: 'img'; value: string } | { kind: 'ruby'; value: string; rt: string };

const parts = computed<Part[]>(() => {
    const out: Part[] = [];
    const re = /\\\(([\s\S]+?)\\\)|⟦img:(\d+)⟧|<(\/?)(b|i|u|sub|sup)>|⟦rb:([^|⟧]*)\|([^⟧]*)⟧/gi;
    let last = 0;
    let m: RegExpExecArray | null;
    const src = props.text ?? '';
    const fmt = { b: false, i: false, u: false, sub: false, sup: false };
    const pushText = (value: string) => {
        if (value !== '') out.push({ kind: 'text', value, b: fmt.b, i: fmt.i, u: fmt.u, sub: fmt.sub, sup: fmt.sup });
    };

    while ((m = re.exec(src)) !== null) {
        pushText(src.slice(last, m.index));
        if (m[1] !== undefined) out.push({ kind: 'math', value: m[1] });
        else if (m[5] !== undefined) out.push({ kind: 'ruby', value: m[5], rt: m[6] ?? '' });
        else if (m[4] !== undefined) fmt[m[4].toLowerCase() as 'b' | 'i' | 'u' | 'sub' | 'sup'] = m[3] !== '/';
        else {
            const url = props.images?.[m[2]];
            if (url) out.push({ kind: 'img', value: url });
        }
        last = m.index + m[0].length;
    }

    pushText(src.slice(last));

    return out;
});
</script>

<template>
    <span class="whitespace-pre-line">
        <template v-for="(p, i) in parts" :key="i">
            <MathInline v-if="p.kind === 'math'" :latex="p.value" />
            <ruby v-else-if="p.kind === 'ruby'" class="ruby-text">{{ p.value }}<rp>(</rp><rt class="font-sans text-[0.55em] leading-none text-ink-soft">{{ p.rt }}</rt><rp>)</rp></ruby>
            <img v-else-if="p.kind === 'img'" :src="p.value" :alt="alt ?? 'Gambar pada soal'" class="mx-1 my-1 inline-block max-h-72 max-w-full rounded border border-line bg-white align-middle" loading="lazy" />
            <span v-else :class="[p.b && 'font-bold', p.i && 'italic', p.u && 'underline', p.sub && 'align-sub text-[0.75em]', p.sup && 'align-super text-[0.75em]']">{{ p.value }}</span>
        </template>
    </span>
</template>
