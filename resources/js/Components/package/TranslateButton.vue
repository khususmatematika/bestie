<script setup lang="ts">
import { Languages } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { ItemDetail } from '@/types';

/**
 * Bantu validator memahami soal berbahasa lain: membuka Google Terjemahan pada tab baru dengan teks butir ini.
 * Teks baru dikirim ke layanan luar ketika tombol diklik (tidak ada pengiriman otomatis).
 */
const props = defineProps<{ item: ItemDetail }>();

const target = ref('id');
const languages = [
    { code: 'id', label: 'Indonesia' },
    { code: 'en', label: 'Inggris' },
    { code: 'ar', label: 'Arab' },
    { code: 'zh-CN', label: 'Mandarin' },
    { code: 'ja', label: 'Jepang' },
    { code: 'de', label: 'Jerman' },
];

const text = computed(() => {
    const clean = (t: string): string => t.replace(/⟦rb:([^|⟧]*)\|[^⟧]*⟧/g, '$1').replace(/⟦[^⟧]*⟧/g, ' ').replace(/<\/?(?:b|i|u|sub|sup)>/gi, '').replace(/\\\(([\s\S]+?)\\\)/g, '$1').replace(/\s+\n/g, '\n').trim();
    const lines = [clean(props.item.stem)];

    for (const [letter, opt] of Object.entries(props.item.options)) {
        if (String(opt).trim() !== '') lines.push(`${letter}. ${clean(String(opt))}`);
    }

    return lines.join('\n').slice(0, 1800);
});

const url = computed(() => `https://translate.google.com/?sl=auto&tl=${encodeURIComponent(target.value)}&text=${encodeURIComponent(text.value)}&op=translate`);
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <label class="sr-only" :for="`tl-${item.id}`">Bahasa tujuan terjemahan</label>
        <select :id="`tl-${item.id}`" v-model="target" class="input !w-auto !py-1 !text-xs">
            <option v-for="l in languages" :key="l.code" :value="l.code">{{ l.label }}</option>
        </select>
        <a :href="url" target="_blank" rel="noopener noreferrer" class="btn-secondary btn-sm" title="Membuka Google Terjemahan di tab baru dengan teks soal ini">
            <Languages class="size-3.5" /> Terjemahkan
        </a>
    </div>
</template>
