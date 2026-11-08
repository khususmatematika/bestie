<script setup lang="ts">
import katex from 'katex';
import 'katex/dist/katex.min.css';
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{ latex: string; display?: boolean }>();
const el = ref<HTMLElement | null>(null);

/**
 * Agar rumus panjang (mis. himpunan pasangan berurutan) turun ke baris berikutnya dan tidak terpotong:
 * tanda kurung \left/\right dilepas bila isinya tidak tinggi (tanpa pecahan/matriks/akar), lalu titik patah
 * disisipkan setelah "),". Rumus tinggi tetap utuh dan dapat digulir lewat pembungkusnya.
 */
function breakable(latex: string): string {
    if (props.display) return latex;
    let out = latex;
    if (!/\\(?:begin|d?frac|sqrt|sum|int|binom|prod|lim)/.test(out)) {
        const plain = out.replace(/\\(?:left|right)\s*(\\?[(){}[\]|])/g, '$1');
        if (!/\\(?:left|right)(?![A-Za-z])/.test(plain)) out = plain;
    }
    return out.replace(/([)}\]])\s*,\s*(?=[({\[\\])/g, '$1,\\allowbreak ');
}

function draw(): void {
    if (!el.value) return;
    try {
        katex.render(breakable(props.latex), el.value, { throwOnError: false, displayMode: props.display ?? false, strict: 'ignore', trust: false, output: 'htmlAndMathml' });
    } catch {
        el.value.textContent = props.latex;
    }
}

onMounted(draw);
watch(() => props.latex, draw);
</script>

<template>
    <span ref="el" class="math-inline" />
</template>
