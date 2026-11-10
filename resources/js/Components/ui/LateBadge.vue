<script setup lang="ts">
import { Clock } from '@lucide/vue';
import { computed } from 'vue';
import type { PackageSummary } from '@/types';

/**
 * Penanda keterlambatan paket (tenggat unggah / revisi). Tenggat tidak menutup unggahan; hanya ditandai.
 */
const props = defineProps<{ late?: PackageSummary['late'] | null }>();

const tags = computed(() => {
    const out: { text: string; title: string }[] = [];
    const l = props.late;

    if (l?.upload === 'terlambat') out.push({ text: 'Terlambat unggah', title: 'Paket dikirim setelah tenggat unggah naskah.' });
    else if (l?.upload === 'lewat_tenggat') out.push({ text: 'Lewat tenggat unggah', title: 'Tenggat unggah naskah sudah lewat dan paket belum dikirim.' });

    if (l?.revision === 'terlambat') out.push({ text: `Revisi terlambat${l.revision_items > 1 ? ` (${l.revision_items} nomor)` : ''}`, title: 'Ada nomor yang diunggah ulang setelah tenggat revisi.' });
    else if (l?.revision === 'lewat_tenggat') out.push({ text: 'Lewat tenggat revisi', title: 'Tenggat revisi sudah lewat dan masih ada nomor yang belum diunggah ulang.' });

    return out;
});
</script>

<template>
    <span v-for="t in tags" :key="t.text" class="badge bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-500" :title="t.title"><Clock class="size-3.5" /> {{ t.text }}</span>
</template>
