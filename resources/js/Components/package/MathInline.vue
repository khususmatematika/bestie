<script setup lang="ts">
import katex from 'katex';
import 'katex/dist/katex.min.css';
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{ latex: string; display?: boolean }>();
const el = ref<HTMLElement | null>(null);

function draw(): void {
    if (!el.value) return;
    try {
        katex.render(props.latex, el.value, { throwOnError: false, displayMode: props.display ?? false, strict: 'ignore', trust: false, output: 'htmlAndMathml' });
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
