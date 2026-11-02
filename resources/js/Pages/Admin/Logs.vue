<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ScrollText, Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import { formatDateTime } from '@/Composables/useFormat';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import type { Paginated } from '@/types';

interface Log {
    id: number;
    event: string;
    causer: string;
    subject: string | null;
    properties: Record<string, unknown> | null;
    ip: string | null;
    created_at: string | null;
}

const props = defineProps<{ logs: Paginated<Log>; filters: { search: string } }>();

const search = ref(props.filters.search);

let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get('/admin/activity-logs', { search: value || undefined }, { preserveState: true, replace: true }), 350);
});

function summary(properties: Record<string, unknown> | null): string {
    if (!properties || !Object.keys(properties).length) return '—';
    return Object.entries(properties)
        .map(([k, v]) => `${k}: ${typeof v === 'object' ? JSON.stringify(v) : String(v)}`)
        .join(' · ');
}
</script>

<template>
    <Head title="Log Aktivitas" />
    <PageHeader title="Log aktivitas" description="Jejak audit seluruh aksi penting. Log tidak dapat diubah dari aplikasi." />

    <section class="card overflow-hidden">
        <div class="border-b border-line p-4">
            <div class="relative max-w-sm">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-faint" />
                <input v-model="search" type="search" class="input pl-10" placeholder="Cari kejadian, mis. teacher.created" aria-label="Cari log" />
            </div>
        </div>

        <div v-if="logs.data.length" class="table-wrap">
            <table class="table">
                <thead><tr><th>Waktu (WIB)</th><th>Pelaku</th><th>Kejadian</th><th>Objek</th><th>Rincian</th><th>IP</th></tr></thead>
                <tbody>
                    <tr v-for="l in logs.data" :key="l.id">
                        <td class="whitespace-nowrap text-xs text-ink-soft">{{ formatDateTime(l.created_at) }}</td>
                        <td class="font-semibold">{{ l.causer }}</td>
                        <td><code class="rounded-md bg-surface-2 px-1.5 py-0.5 font-mono text-xs">{{ l.event }}</code></td>
                        <td class="text-xs text-ink-soft">{{ l.subject ?? '—' }}</td>
                        <td class="max-w-xs truncate text-xs text-ink-soft" :title="summary(l.properties)">{{ summary(l.properties) }}</td>
                        <td class="font-mono text-xs text-ink-faint">{{ l.ip ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="Belum ada aktivitas" description="Aksi seperti login, pembuatan akun, dan perubahan semester akan tercatat di sini.">
            <template #icon><ScrollText class="size-7" /></template>
        </EmptyState>
        <Pagination :meta="logs" />
    </section>
</template>
