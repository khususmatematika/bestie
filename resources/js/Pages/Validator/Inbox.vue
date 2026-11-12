<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BadgeCheck, Inbox as InboxIcon, RefreshCw, Search, ShieldCheck, Sparkles } from '@lucide/vue';
import { ref, watch } from 'vue';
import { daysUntil, formatDate, formatDateTime } from '@/Composables/useFormat';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import ProgressBar from '@/Components/ui/ProgressBar.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import StatusBadge from '@/Components/ui/StatusBadge.vue';
import LateBadge from '@/Components/ui/LateBadge.vue';
import type { PackageSummary, Paginated } from '@/types';

interface InboxPackage extends PackageSummary {
    revised_count: number;
    is_new: boolean;
    deadline: string | null;
}

const props = defineProps<{
    packages: Paginated<InboxPackage>;
    counts: { new: number; in_review: number; fix: number };
    filters: { status: string; subject_id: number | ''; search: string };
    statuses: { value: string; label: string }[];
    subjects: { id: number; name: string }[];
}>();

const status = ref(props.filters.status);
const subjectId = ref<number | ''>(props.filters.subject_id);
const search = ref(props.filters.search);

function apply(): void {
    router.get(
        '/validator/inbox',
        { status: status.value || undefined, subject_id: subjectId.value || undefined, search: search.value || undefined },
        { preserveState: true, replace: true },
    );
}

let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(apply, 350);
});

function deadlineText(deadline: string | null): string | null {
    const days = daysUntil(deadline);
    if (days === null) return null;
    if (days < 0) return `Tenggat lewat ${Math.abs(days)} hari (${formatDate(deadline)})`;
    if (days === 0) return 'Tenggat hari ini';
    return `Tenggat ${days} hari lagi (${formatDate(deadline)})`;
}
</script>

<template>
    <Head title="Inbox Telaah" />
    <PageHeader title="Inbox telaah" description="Paket dari guru yang memilih Anda sebagai validator pada periode terpilih. Buka satu paket untuk menelaah nomor demi nomor." />

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard label="Paket baru" :value="counts.new" tone="primary"><Sparkles class="size-6" /></StatCard>
        <StatCard label="Sedang ditelaah / revisi" :value="counts.in_review" tone="warning"><RefreshCw class="size-6" /></StatCard>
        <StatCard label="Fix" :value="counts.fix" tone="success"><BadgeCheck class="size-6" /></StatCard>
    </div>

    <section class="card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-line p-4 sm:flex-row">
            <div class="relative flex-1">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-faint" />
                <input v-model="search" type="search" class="input pl-10" placeholder="Cari judul paket atau nama guru…" aria-label="Cari paket" />
            </div>
            <select v-model="status" class="input sm:max-w-44" aria-label="Filter status" @change="apply">
                <option value="">Semua status</option>
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <select v-model="subjectId" class="input sm:max-w-52" aria-label="Filter mapel" @change="apply">
                <option value="">Semua mapel</option>
                <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
        </div>

        <ul v-if="packages.data.length" class="divide-y divide-line">
            <li v-for="p in packages.data" :key="p.id">
                <Link :href="`/validator/packages/${p.id}/review`" class="flex flex-col gap-3 px-6 py-4 transition hover:bg-surface-2/60 lg:flex-row lg:items-center">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-bold text-ink">{{ p.title }}</p>
                            <span v-if="p.is_new" class="badge bg-primary-600 text-white">Baru</span>
                            <span v-if="p.revised_count" class="badge bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-500"><RefreshCw class="size-3.5" /> {{ p.revised_count }} diunggah ulang</span>
                            <span v-if="p.is_self_validated" class="badge bg-accent-50 text-accent-700 dark:bg-accent-500/15 dark:text-accent-100"><ShieldCheck class="size-3.5" /> Divalidasi Mandiri</span>
                        </div>
                        <p class="mt-0.5 text-xs text-ink-faint">
                            {{ p.teacher }} · {{ p.subject }} · Kelas {{ p.grade }} · Dikirim {{ formatDateTime(p.submitted_at) }}
                        </p>
                        <p v-if="deadlineText(p.deadline)" class="mt-0.5 text-xs text-ink-soft">{{ deadlineText(p.deadline) }}</p>
                    </div>
                    <div class="w-full lg:w-48">
                        <ProgressBar :value="p.progress" :label="`Progres validasi ${p.title}`" />
                        <p class="mt-0.5 text-right text-[11px] text-ink-faint">{{ p.approved_items }}/{{ p.total_items }} disetujui</p>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-1.5"><StatusBadge :status="p.status" /><LateBadge :late="p.late" /></div>
                </Link>
            </li>
        </ul>
        <EmptyState v-else title="Inbox kosong" description="Belum ada paket yang masuk pada periode ini, atau tidak ada yang cocok dengan filter.">
            <template #icon><InboxIcon class="size-7" /></template>
        </EmptyState>
        <Pagination :meta="packages" />
    </section>
</template>
