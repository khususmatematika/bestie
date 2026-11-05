<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { daysUntil } from '@/Composables/useFormat';

const props = defineProps<{ deadlines: { label: string; date: string | null }[]; href?: string; emptyText?: string }>();

const MONTHS = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];

const rows = computed(() =>
    props.deadlines.map((d) => {
        const left = daysUntil(d.date);
        const [, m, day] = (d.date ?? '').slice(0, 10).split('-').map(Number);
        const tone = left === null ? 'idle' : left < 0 ? 'late' : left <= 3 ? 'soon' : 'ok';
        const hint = left === null ? 'Belum diatur' : left < 0 ? `Lewat ${Math.abs(left)} hari` : left === 0 ? 'Hari ini' : `${left} hari lagi`;

        return { label: d.label, tone, hint, day: day ? String(day) : '–', month: m ? MONTHS[m - 1] : '' };
    }),
);

const toneClass = {
    idle: 'bg-surface-2 text-ink-faint',
    ok: 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300',
    soon: 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-500',
    late: 'bg-danger-50 text-danger-700 dark:bg-danger-500/15 dark:text-danger-500',
} as const;
</script>

<template>
    <ul v-if="rows.length" class="space-y-2.5">
        <li v-for="r in rows" :key="r.label">
            <component :is="href ? Link : 'div'" :href="href" class="flex items-center gap-3 rounded-xl border border-line p-2.5 transition" :class="href ? 'hover:bg-surface-2' : ''">
                <span class="flex size-12 shrink-0 flex-col items-center justify-center rounded-lg leading-none" :class="toneClass[r.tone as keyof typeof toneClass]">
                    <span class="text-lg font-extrabold tabular-nums">{{ r.day }}</span>
                    <span class="mt-0.5 text-[10px] font-bold tracking-wide">{{ r.month }}</span>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-ink">{{ r.label }}</span>
                    <span class="block text-xs font-semibold" :class="r.tone === 'late' ? 'text-danger-700 dark:text-danger-500' : r.tone === 'soon' ? 'text-warning-700 dark:text-warning-500' : 'text-ink-faint'">{{ r.hint }}</span>
                </span>
            </component>
        </li>
    </ul>
    <p v-else class="text-sm text-ink-soft">{{ emptyText ?? 'Belum ada periode asesmen.' }}</p>
</template>
