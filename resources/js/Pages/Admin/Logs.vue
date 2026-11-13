<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, ChevronRight, ScrollText, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { currentPerPage } from '@/Composables/perPage';
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

interface LogUser {
    id: string;
    name: string;
    total: number;
    last_at: string | null;
    last_event: string | null;
}

const props = defineProps<{ logs: Paginated<Log> | null; users: LogUser[]; filters: { search: string; user: string } }>();

const selected = computed(() => props.users.find((u) => u.id === props.filters.user) ?? null);
const userSearch = ref('');
const search = ref(props.filters.search);

const shownUsers = computed(() => {
    const q = userSearch.value.trim().toLowerCase();

    return q === '' ? props.users : props.users.filter((u) => u.name.toLowerCase().includes(q));
});

function open(user: string): void {
    router.get('/admin/activity-logs', { user, per_page: currentPerPage() }, { preserveState: false });
}

function back(): void {
    router.get('/admin/activity-logs');
}

let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get('/admin/activity-logs', { user: props.filters.user, search: value || undefined, per_page: currentPerPage() }, { preserveState: true, replace: true }), 350);
});

/** Rincian dipecah menjadi pasangan kunci–nilai agar dapat dibungkus dan terbaca seluruhnya. */
function details(properties: Record<string, unknown> | null): { k: string; v: string }[] {
    if (!properties) return [];

    return Object.entries(properties).map(([k, v]) => ({ k, v: typeof v === 'object' && v !== null ? JSON.stringify(v) : String(v) }));
}
</script>

<template>
    <Head title="Log Aktivitas" />
    <PageHeader title="Log aktivitas" :description="logs ? 'Seluruh aktivitas pengguna terpilih. Log tidak dapat diubah dari aplikasi.' : 'Pilih nama pengguna untuk melihat seluruh aktivitasnya. Log tidak dapat diubah dari aplikasi.'" />

    <!-- Ringkasan per pengguna -->
    <section v-if="!logs" class="card min-w-0 overflow-hidden">
        <div class="border-b border-line p-4">
            <div class="relative max-w-sm">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-faint" />
                <input v-model="userSearch" type="search" class="input pl-10" placeholder="Cari pengguna…" aria-label="Cari pengguna" />
            </div>
        </div>
        <ul v-if="shownUsers.length" class="divide-y divide-line">
            <li v-for="u in shownUsers" :key="u.id">
                <button type="button" class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-surface-2" @click="open(u.id)">
                    <span class="min-w-0 flex-1">
                        <span class="block break-words text-sm font-bold text-ink">{{ u.name }}</span>
                        <span class="mt-0.5 block text-xs text-ink-soft">
                            Terakhir: <code v-if="u.last_event" class="break-all rounded bg-surface-2 px-1 py-0.5 font-mono text-[11px]">{{ u.last_event }}</code>
                            <span class="whitespace-nowrap text-ink-faint"> · {{ formatDateTime(u.last_at) }}</span>
                        </span>
                    </span>
                    <span class="shrink-0 rounded-full bg-surface-2 px-2.5 py-0.5 text-xs font-semibold text-ink-soft">{{ u.total }} aktivitas</span>
                    <ChevronRight class="size-4 shrink-0 text-ink-faint" />
                </button>
            </li>
        </ul>
        <EmptyState v-else title="Belum ada aktivitas" description="Aksi seperti login, pembuatan akun, dan perubahan semester akan tercatat di sini.">
            <template #icon><ScrollText class="size-7" /></template>
        </EmptyState>
    </section>

    <!-- Rincian satu pengguna -->
    <section v-else class="card min-w-0 overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-line p-4 sm:flex-row sm:items-center">
            <button type="button" class="btn-secondary btn-sm self-start" @click="back"><ArrowLeft class="size-4" /> Semua pengguna</button>
            <p class="min-w-0 flex-1 break-words text-sm"><strong class="text-base text-ink">{{ selected?.name ?? 'Pengguna' }}</strong><span v-if="selected" class="text-ink-soft"> · {{ selected.total }} aktivitas</span></p>
            <div class="relative min-w-0 sm:w-64">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-faint" />
                <input v-model="search" type="search" class="input pl-10" placeholder="Cari kejadian…" aria-label="Cari log" />
            </div>
        </div>

        <template v-if="logs.data.length">
            <div class="hidden md:block">
                <table class="w-full table-fixed text-sm">
                    <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-ink-faint">
                        <tr>
                            <th class="w-36 px-4 py-2.5 font-semibold">Waktu (WIB)</th>
                            <th class="w-48 px-3 py-2.5 font-semibold">Kejadian</th>
                            <th class="w-28 px-3 py-2.5 font-semibold">Objek</th>
                            <th class="px-3 py-2.5 font-semibold">Rincian</th>
                            <th class="w-28 px-4 py-2.5 font-semibold">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line align-top">
                        <tr v-for="l in logs.data" :key="l.id">
                            <td class="px-4 py-2.5 text-xs text-ink-soft">{{ formatDateTime(l.created_at) }}</td>
                            <td class="px-3 py-2.5"><code class="break-all rounded-md bg-surface-2 px-1.5 py-0.5 font-mono text-xs">{{ l.event }}</code></td>
                            <td class="break-words px-3 py-2.5 text-xs text-ink-soft">{{ l.subject ?? '—' }}</td>
                            <td class="px-3 py-2.5">
                                <div v-if="details(l.properties).length" class="flex flex-wrap gap-1">
                                    <span v-for="d in details(l.properties)" :key="d.k" class="max-w-full break-all rounded-md bg-surface-2 px-1.5 py-0.5 text-[11px] text-ink-soft"><span class="font-semibold text-ink">{{ d.k }}</span>: {{ d.v }}</span>
                                </div>
                                <span v-else class="text-xs text-ink-faint">—</span>
                            </td>
                            <td class="break-all px-4 py-2.5 font-mono text-xs text-ink-faint">{{ l.ip ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul class="divide-y divide-line md:hidden">
                <li v-for="l in logs.data" :key="l.id" class="space-y-1.5 px-4 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                        <code class="break-all rounded-md bg-surface-2 px-1.5 py-0.5 font-mono text-xs">{{ l.event }}</code>
                        <span class="text-[11px] text-ink-faint">{{ formatDateTime(l.created_at) }}</span>
                    </div>
                    <p v-if="l.subject" class="text-xs text-ink-soft">Objek: {{ l.subject }}</p>
                    <div v-if="details(l.properties).length" class="flex flex-wrap gap-1">
                        <span v-for="d in details(l.properties)" :key="d.k" class="max-w-full break-all rounded-md bg-surface-2 px-1.5 py-0.5 text-[11px] text-ink-soft"><span class="font-semibold text-ink">{{ d.k }}</span>: {{ d.v }}</span>
                    </div>
                    <p v-if="l.ip" class="font-mono text-[11px] text-ink-faint">IP {{ l.ip }}</p>
                </li>
            </ul>
        </template>
        <EmptyState v-else title="Tidak ada aktivitas" description="Tidak ada aktivitas yang cocok dengan pencarian ini.">
            <template #icon><ScrollText class="size-7" /></template>
        </EmptyState>
        <Pagination :meta="logs" per-page-control />
    </section>
</template>
