<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, FilePlus2, FolderOpen, ShieldCheck } from '@lucide/vue';
import { formatDateTime } from '@/Composables/useFormat';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import ProgressBar from '@/Components/ui/ProgressBar.vue';
import StatusBadge from '@/Components/ui/StatusBadge.vue';
import type { PackageSummary, Paginated } from '@/types';

const props = defineProps<{
    packages: Paginated<PackageSummary>;
    pending: { id: number; subject: string; code: string; grade: string }[];
    can_create: boolean;
    filters: { status: string };
    statuses: { value: string; label: string }[];
}>();

function filter(event: Event): void {
    const status = (event.target as HTMLSelectElement).value;
    router.get('/teacher/packages', { status: status || undefined }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Paket Soal Saya" />
    <PageHeader title="Paket soal saya" description="Satu paket = satu mapel dan tingkat pada periode asesmen terpilih. Unggah kisi-kisi dan naskah, lalu kirim ke validator.">
        <Link v-if="can_create && pending.length" href="/teacher/packages/create" class="btn-primary"><FilePlus2 class="size-4" /> Buat paket</Link>
    </PageHeader>

    <section v-if="pending.length && can_create" class="card mb-6 p-5">
        <h2 class="text-sm font-bold text-ink">Belum ada paket untuk</h2>
        <div class="mt-3 flex flex-wrap gap-2">
            <Link
                v-for="a in pending"
                :key="a.id"
                :href="`/teacher/packages/create?assignment=${a.id}`"
                class="inline-flex items-center gap-2 rounded-xl border border-line px-3.5 py-2 text-sm font-semibold text-ink transition hover:border-primary-300 hover:bg-primary-50 dark:hover:bg-primary-500/10"
            >
                {{ a.subject }} <span class="text-ink-faint">· Kelas {{ a.grade }}</span> <ArrowRight class="size-3.5 text-primary-600" />
            </Link>
        </div>
    </section>

    <section class="card overflow-hidden">
        <div class="flex items-center justify-between gap-3 border-b border-line p-4">
            <label class="sr-only" for="status-filter">Filter status</label>
            <select id="status-filter" class="input max-w-48" :value="props.filters.status" @change="filter">
                <option value="">Semua status</option>
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
        </div>

        <ul v-if="packages.data.length" class="divide-y divide-line">
            <li v-for="p in packages.data" :key="p.id">
                <Link :href="`/teacher/packages/${p.id}`" class="flex flex-col gap-3 px-6 py-4 transition hover:bg-surface-2/60 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-bold text-ink">{{ p.title }}</p>
                        <p class="mt-0.5 text-xs text-ink-faint">
                            {{ p.subject }} · Kelas {{ p.grade }} · {{ p.period }}
                            <template v-if="p.validator"> · Validator: {{ p.validator }}</template>
                            <template v-if="p.submitted_at"> · Dikirim {{ formatDateTime(p.submitted_at) }}</template>
                        </p>
                    </div>
                    <span v-if="p.is_self_validated" class="badge bg-accent-50 text-accent-700 dark:bg-accent-500/15 dark:text-accent-100"><ShieldCheck class="size-3.5" /> Divalidasi Mandiri</span>
                    <div class="w-full sm:w-44">
                        <ProgressBar :value="p.progress" :label="`Progres ${p.title}`" />
                        <p class="mt-0.5 text-right text-[11px] text-ink-faint">{{ p.approved_items }}/{{ p.total_items }} nomor disetujui</p>
                    </div>
                    <StatusBadge :status="p.status" />
                </Link>
            </li>
        </ul>
        <EmptyState v-else title="Belum ada paket" :description="!can_create ? 'Periode ini tidak menerima paket baru.' : pending.length ? 'Mulai dengan membuat paket untuk mapel yang Anda ampu.' : 'Anda belum memiliki penugasan mengajar (mapel + tingkat) pada semester ini. Minta admin menambahkannya di menu Penugasan Mengajar, atau impor ulang akun guru.'">
            <template #icon><FolderOpen class="size-7" /></template>
            <Link v-if="can_create && pending.length" href="/teacher/packages/create" class="btn-primary"><FilePlus2 class="size-4" /> Buat paket</Link>
        </EmptyState>
        <Pagination :meta="packages" />
    </section>
</template>
