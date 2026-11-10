<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, BookOpen, CalendarDays, FilePlus2, FolderOpen, Plus, ShieldCheck, UserCheck } from '@lucide/vue';
import { computed } from 'vue';
import { formatDateTime } from '@/Composables/useFormat';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import ProgressBar from '@/Components/ui/ProgressBar.vue';
import StatusBadge from '@/Components/ui/StatusBadge.vue';
import LateBadge from '@/Components/ui/LateBadge.vue';
import type { PackageSummary, Paginated } from '@/types';

const props = defineProps<{
    packages: Paginated<PackageSummary>;
    pending: { id: number; subject: string; code: string; grade: string }[];
    can_create: boolean;
    filters: { status: string };
    statuses: { value: string; label: string }[];
}>();

const showFilter = computed(() => props.packages.total > 2 || !!props.filters.status);
const showPending = computed(() => props.can_create && props.pending.length > 0 && !props.filters.status);

function filter(status: string): void {
    router.get('/teacher/packages', { status: status || undefined }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Paket Soal Saya" />

    <div class="@container">
        <PageHeader title="Paket soal saya" description="Satu paket = satu mapel dan tingkat pada periode asesmen terpilih. Unggah kisi-kisi dan naskah, lalu kirim ke validator.">
            <Link v-if="can_create && pending.length" href="/teacher/packages/create" class="btn-primary"><FilePlus2 class="size-4" /> Buat paket</Link>
        </PageHeader>

        <!-- Filter status: hanya muncul bila paket cukup banyak -->
        <nav v-if="showFilter" class="-mx-1 mb-5 flex gap-2 overflow-x-auto px-1 pb-1" aria-label="Filter status paket">
            <button
                v-for="s in [{ value: '', label: 'Semua' }, ...statuses]"
                :key="s.value"
                type="button"
                class="shrink-0 rounded-full border px-3.5 py-1.5 text-sm font-semibold transition"
                :class="(filters.status || '') === s.value ? 'border-primary-600 bg-primary-600 text-white shadow-sm' : 'border-line bg-surface text-ink-soft hover:bg-surface-2'"
                :aria-pressed="(filters.status || '') === s.value"
                @click="filter(s.value)"
            >
                {{ s.label }}
            </button>
        </nav>

        <!-- Satu daftar kartu: 1 kartu melebar penuh, 2 kartu berdampingan, lebih banyak mengalir otomatis -->
        <div v-if="packages.data.length || showPending" class="space-y-4">
        <ul v-if="showPending" class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,22rem),1fr))] gap-4" aria-label="Penugasan yang belum memiliki paket">
            <li v-for="a in pending" :key="`pending-${a.id}`" class="@container/card">
                <Link
                    :href="`/teacher/packages/create?assignment=${a.id}`"
                    class="group flex h-full flex-col gap-4 rounded-2xl border-2 border-dashed border-primary-300/70 bg-primary-50/40 p-5 transition hover:-translate-y-0.5 hover:border-primary-500 hover:bg-primary-50 @xl/card:flex-row @xl/card:items-center dark:bg-primary-500/5 dark:hover:bg-primary-500/10"
                >
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary-600 text-white shadow-sm"><Plus class="size-6" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-xs font-bold uppercase tracking-wide text-primary-700 dark:text-primary-300">Belum dibuat</span>
                        <span class="mt-0.5 block truncate text-lg font-bold text-ink">{{ a.subject }}</span>
                        <span class="block text-sm text-ink-soft">Kelas {{ a.grade }} · {{ a.code }}</span>
                    </span>
                    <span class="btn-primary pointer-events-none w-full justify-center @xl/card:w-auto">Buat paket <ArrowRight class="size-4 transition group-hover:translate-x-0.5" /></span>
                </Link>
            </li>
        </ul>

        <ul v-if="packages.data.length" class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,22rem),1fr))] gap-4">
            <li v-for="p in packages.data" :key="p.id" class="@container/card">
                <Link :href="`/teacher/packages/${p.id}`" class="card group flex h-full flex-col gap-4 p-5 transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-pop focus-visible:outline-2 focus-visible:outline-primary-500 @xl/card:flex-row @xl/card:items-center @xl/card:gap-6">
                    <div class="flex min-w-0 items-start gap-3 @xl/card:w-2/5">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-500/15 dark:text-primary-300"><BookOpen class="size-6" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-lg font-bold text-ink">{{ p.subject }}</p>
                            <p class="mt-0.5 text-sm font-medium text-ink-soft">Kelas {{ p.grade }}</p>
                            <p class="mt-1 flex items-center gap-1.5 text-xs text-ink-faint"><CalendarDays class="size-3.5 shrink-0" /> <span class="truncate">{{ p.period }}</span></p>
                        </div>
                        <StatusBadge :status="p.status" class="@xl/card:hidden" />
                        <LateBadge :late="p.late" class="@xl/card:hidden" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="mb-1.5 flex items-baseline justify-between gap-2 text-xs">
                            <span class="font-semibold text-ink-soft">Progres validasi</span>
                            <span class="tabular-nums text-ink-faint">{{ p.approved_items }}/{{ p.total_items }} nomor</span>
                        </div>
                        <ProgressBar :value="p.progress" :label="`Progres ${p.subject}`" />
                        <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-faint">
                            <span v-if="p.validator" class="inline-flex items-center gap-1"><UserCheck class="size-3.5" /> {{ p.validator }}</span>
                            <span v-else>Belum memilih validator</span>
                            <span v-if="p.submitted_at">Dikirim {{ formatDateTime(p.submitted_at) }}</span>
                            <span v-if="p.is_self_validated" class="badge bg-accent-50 text-accent-700 dark:bg-accent-500/15 dark:text-accent-100"><ShieldCheck class="size-3.5" /> Divalidasi Mandiri</span>
                        </p>
                    </div>

                    <div class="flex items-center justify-between gap-3 border-t border-line pt-3 @xl/card:flex-col @xl/card:items-end @xl/card:border-0 @xl/card:pt-0">
                        <StatusBadge :status="p.status" class="hidden @xl/card:inline-flex" />
                        <LateBadge :late="p.late" class="hidden @xl/card:inline-flex" />
                        <span class="ml-auto inline-flex items-center gap-1 text-sm font-semibold text-primary-600 dark:text-primary-300">Buka paket <ArrowRight class="size-4 transition group-hover:translate-x-0.5" /></span>
                    </div>
                </Link>
            </li>
        </ul>
        </div>
        <div v-else class="card">
            <EmptyState title="Belum ada paket" :description="!can_create ? 'Periode ini tidak menerima paket baru.' : pending.length ? 'Mulai dengan membuat paket untuk mapel yang Anda ampu.' : 'Anda belum memiliki penugasan mengajar (mapel + tingkat) pada semester ini. Minta admin menambahkannya di menu Penugasan Mengajar, atau impor ulang akun guru.'">
                <template #icon><FolderOpen class="size-7" /></template>
                <Link v-if="can_create && pending.length" href="/teacher/packages/create" class="btn-primary"><FilePlus2 class="size-4" /> Buat paket</Link>
            </EmptyState>
        </div>
        <Pagination :meta="packages" />
    </div>
</template>
