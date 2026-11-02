<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{ value: number; label?: string; showValue?: boolean }>(), { label: 'Progres', showValue: true });

const pct = computed(() => Math.max(0, Math.min(100, Math.round(props.value))));
const tone = computed(() => (pct.value >= 100 ? 'bg-success-500' : pct.value >= 50 ? 'bg-primary-600' : 'bg-primary-400'));
</script>

<template>
    <div class="flex items-center gap-3">
        <div
            class="h-2 flex-1 overflow-hidden rounded-full bg-surface-2"
            role="progressbar"
            :aria-valuenow="pct"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="label"
        >
            <div class="h-full rounded-full transition-all duration-300 motion-reduce:transition-none" :class="tone" :style="{ width: `${pct}%` }" />
        </div>
        <span v-if="showValue" class="w-10 text-right text-xs font-bold tabular-nums text-ink-soft">{{ pct }}%</span>
    </div>
</template>
