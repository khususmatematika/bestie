<script setup lang="ts">
import { Archive, BadgeCheck, CalendarClock, CircleCheck, CircleDashed, CircleSlash, Eye, FileText, Lock, LockOpen, RefreshCw, RotateCcw, Send, ShieldCheck, UserX } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Lencana status: selalu warna + ikon + teks (rules.md §8.2, tidak mengandalkan warna saja).
 */
const props = defineProps<{
    status: string;
    label?: string;
}>();

const map: Record<string, { text: string; classes: string; icon: unknown }> = {
    aktif: { text: 'Aktif', classes: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500', icon: CircleCheck },
    rencana: { text: 'Direncanakan', classes: 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300', icon: CalendarClock },
    arsip: { text: 'Arsip', classes: 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300', icon: Archive },
    buka: { text: 'Dibuka', classes: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500', icon: LockOpen },
    ditutup: { text: 'Ditutup', classes: 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-500', icon: Lock },
    validator: { text: 'Validator', classes: 'bg-accent-50 text-accent-700 dark:bg-accent-500/15 dark:text-accent-100', icon: ShieldCheck },
    nonaktif: { text: 'Nonaktif', classes: 'bg-danger-50 text-danger-700 dark:bg-danger-500/15 dark:text-danger-500', icon: UserX },
    created: { text: 'Dibuat', classes: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500', icon: CircleCheck },
    updated: { text: 'Diperbarui', classes: 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300', icon: CircleCheck },
    skipped: { text: 'Dilewati', classes: 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-500', icon: CircleSlash },
    failed: { text: 'Gagal', classes: 'bg-danger-50 text-danger-700 dark:bg-danger-500/15 dark:text-danger-500', icon: CircleSlash },
    pending: { text: 'Menunggu', classes: 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300', icon: CalendarClock },
    processing: { text: 'Diproses', classes: 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300', icon: CalendarClock },
    draft: { text: 'Draft', classes: 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300', icon: FileText },
    terkirim: { text: 'Terkirim', classes: 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300', icon: Send },
    ditelaah: { text: 'Ditelaah', classes: 'bg-accent-50 text-accent-700 dark:bg-accent-500/15 dark:text-accent-100', icon: Eye },
    perlu_revisi: { text: 'Perlu revisi', classes: 'bg-danger-50 text-danger-700 dark:bg-danger-500/15 dark:text-danger-500', icon: RotateCcw },
    fix: { text: 'Fix', classes: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500', icon: BadgeCheck },
    terkunci: { text: 'Terkunci', classes: 'bg-slate-200 text-slate-700 dark:bg-slate-500/25 dark:text-slate-200', icon: Lock },
    belum_ditelaah: { text: 'Belum ditelaah', classes: 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300', icon: CircleDashed },
    disetujui: { text: 'Disetujui', classes: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500', icon: CircleCheck },
    perlu_ganti: { text: 'Perlu ganti', classes: 'bg-danger-50 text-danger-700 dark:bg-danger-500/15 dark:text-danger-500', icon: CircleSlash },
    direvisi: { text: 'Direvisi', classes: 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-500', icon: RefreshCw },
    done: { text: 'Selesai', classes: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500', icon: CircleCheck },
};

const meta = computed(
    () => map[props.status] ?? { text: props.status, classes: 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300', icon: CircleCheck },
);
</script>

<template>
    <span class="badge" :class="meta.classes">
        <component :is="meta.icon" class="size-3.5" aria-hidden="true" />
        {{ label ?? meta.text }}
    </span>
</template>
