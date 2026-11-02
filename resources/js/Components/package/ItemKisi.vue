<script setup lang="ts">
import type { ItemDetail } from '@/types';

defineProps<{ item: ItemDetail }>();

const rows = (item: ItemDetail): { label: string; value: string }[] => [
    { label: 'Capaian pembelajaran', value: item.blueprint.cp },
    { label: 'Materi', value: item.blueprint.materi },
    { label: 'Submateri', value: item.blueprint.submateri },
    { label: 'Indikator soal', value: item.blueprint.indikator },
    { label: 'Level kognitif', value: item.blueprint.level },
    { label: 'Bentuk soal', value: item.type_label },
    { label: 'Bobot', value: item.blueprint.bobot === null ? '' : String(item.blueprint.bobot) },
];
</script>

<template>
    <dl class="space-y-3 text-sm">
        <div v-for="row in rows(item)" :key="row.label">
            <dt class="text-xs font-semibold uppercase tracking-wide text-ink-faint">{{ row.label }}</dt>
            <dd class="mt-0.5 whitespace-pre-line text-ink" :class="{ 'text-ink-faint italic': !row.value }">{{ row.value || 'Belum diisi' }}</dd>
        </div>
    </dl>
</template>
