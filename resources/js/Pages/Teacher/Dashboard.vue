<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BadgeCheck, BookOpen, FilePenLine, FolderOpen, RotateCcw, ScanSearch } from '@lucide/vue';
import PackageProgressList, { type ProgressPackage } from '@/Components/package/PackageProgressList.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import { daysUntil, formatDate } from '@/Composables/useFormat';

defineProps<{
    assignments: { id: number; subject: string; code: string; grade: string }[];
    package_stats: { draft: number; in_review: number; fix: number; items_to_revise: number };
    packages: ProgressPackage[];
    deadlines: { label: string; date: string | null }[];
}>();

function hint(date: string | null): string {
    const d = daysUntil(date);
    if (d === null) return 'Belum diatur';
    if (d < 0) return `Lewat ${Math.abs(d)} hari`;
    return d === 0 ? 'Hari ini' : `${d} hari lagi`;
}

const page = usePage();
</script>

<template>
    <Head title="Dashboard Guru" />
    <PageHeader :title="`Halo, ${page.props.auth?.user.name ?? ''}`" description="Mapel dan tingkat yang Anda ampu pada semester aktif." />

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Draft" :value="package_stats.draft" tone="primary" href="/teacher/packages?status=draft"><FilePenLine class="size-6" /></StatCard>
        <StatCard label="Dalam telaah" :value="package_stats.in_review" tone="accent" href="/teacher/packages"><ScanSearch class="size-6" /></StatCard>
        <StatCard label="Nomor perlu diganti" :value="package_stats.items_to_revise" tone="warning" href="/teacher/packages"><RotateCcw class="size-6" /></StatCard>
        <StatCard label="Paket Fix" :value="package_stats.fix" tone="success" href="/teacher/packages?status=fix"><BadgeCheck class="size-6" /></StatCard>
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <section class="card overflow-hidden lg:col-span-2">
            <div class="flex items-center justify-between gap-3 border-b border-line px-6 py-4">
                <h2 class="text-base font-bold text-ink">Paket saya pada periode ini</h2>
                <Link href="/teacher/packages" class="text-sm font-semibold text-primary-600 hover:underline">Semua paket</Link>
            </div>
            <PackageProgressList :packages="packages" role="teacher" empty-title="Belum ada paket" empty-description="Buat paket soal untuk mapel yang Anda ampu pada periode ini." />
        </section>

        <section class="card p-6">
            <h2 class="text-base font-bold text-ink">Tenggat periode ini</h2>
            <div class="mt-3 space-y-3">
                <div v-for="d in deadlines" :key="d.label" class="flex items-center justify-between gap-3 rounded-xl bg-surface-2 px-4 py-3">
                    <div><p class="text-sm font-semibold text-ink">{{ d.label }}</p><p class="text-xs text-ink-faint">{{ formatDate(d.date) }}</p></div>
                    <span class="text-xs font-semibold text-ink-soft">{{ hint(d.date) }}</span>
                </div>
                <p v-if="!deadlines.length" class="text-sm text-ink-soft">Belum ada periode asesmen.</p>
            </div>
        </section>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card lg:col-span-2">
            <div class="border-b border-line px-6 py-4">
                <h2 class="text-base font-bold text-ink">Penugasan mengajar</h2>
            </div>

            <div v-if="assignments.length" class="grid gap-3 p-6 sm:grid-cols-2">
                <Link v-for="a in assignments" :key="a.id" href="/teacher/packages/create" class="flex items-center gap-4 rounded-xl border border-line bg-surface-2/50 p-4 transition hover:border-primary-300 hover:bg-surface-2">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/15 dark:text-primary-300">
                        <BookOpen class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="truncate font-bold text-ink">{{ a.subject }}</p>
                        <p class="text-xs text-ink-faint">Kelas {{ a.grade }} · {{ a.code }}</p>
                    </div>
                </Link>
            </div>
            <EmptyState v-else title="Belum ada penugasan" description="Admin belum menautkan Anda ke mapel pada semester ini. Hubungi panitia asesmen." />
        </section>

        <section class="card p-6">
            <span class="flex size-11 items-center justify-center rounded-xl bg-accent-50 text-accent-600 dark:bg-accent-500/15 dark:text-accent-100"><FolderOpen class="size-5" /></span>
            <h2 class="mt-4 text-base font-bold text-ink">Paket soal</h2>
            <p class="mt-1 text-sm text-ink-soft">Unggah kisi-kisi dan naskah, pilih validator, lalu pantau catatan per nomor.</p>
            <Link href="/teacher/packages" class="btn-primary mt-4">Buka paket saya</Link>
        </section>
    </div>
</template>
