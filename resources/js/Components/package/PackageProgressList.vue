<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import EmptyState from '@/Components/ui/EmptyState.vue';
import ProgressBar from '@/Components/ui/ProgressBar.vue';
import StatusBadge from '@/Components/ui/StatusBadge.vue';

export interface ProgressPackage {
    id: number;
    title: string;
    subject: string;
    grade: string;
    teacher: string;
    status: string;
    total: number;
    approved: number;
    pending: number;
    change: number;
}

const props = defineProps<{
    packages: ProgressPackage[];
    role: 'teacher' | 'validator';
    emptyTitle: string;
    emptyDescription: string;
}>();

function href(p: ProgressPackage): string {
    return props.role === 'teacher' ? `/teacher/packages/${p.id}` : `/validator/packages/${p.id}/review`;
}

function percent(p: ProgressPackage): number {
    return p.total > 0 ? (p.approved / p.total) * 100 : 0;
}
</script>

<template>
    <ul v-if="packages.length" class="divide-y divide-line">
        <li v-for="p in packages" :key="p.id">
            <Link :href="href(p)" class="block px-4 py-3 transition hover:bg-surface-2 sm:px-6">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <p class="min-w-0 flex-1 truncate font-semibold text-ink">{{ p.subject }} · Kelas {{ p.grade }}</p>
                    <StatusBadge :status="p.status" />
                </div>
                <p class="mt-0.5 truncate text-xs text-ink-faint">{{ role === 'validator' ? p.teacher : p.title }} · {{ p.approved }}/{{ p.total }} nomor disetujui<span v-if="p.pending > 0 && role === 'validator'"> · {{ p.pending }} menunggu telaah</span><span v-if="p.change > 0"> · {{ p.change }} perlu diganti</span></p>
                <ProgressBar class="mt-2" :value="percent(p)" :label="`Progres ${p.subject}`" />
            </Link>
        </li>
    </ul>
    <EmptyState v-else :title="emptyTitle" :description="emptyDescription" />
</template>
