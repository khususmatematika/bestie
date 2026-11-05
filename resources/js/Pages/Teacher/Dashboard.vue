<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, BadgeCheck, BookOpen, FilePenLine, FilePlus2, FolderOpen, RotateCcw, ScanSearch, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import DashboardHero from '@/Components/dashboard/DashboardHero.vue';
import DeadlineList from '@/Components/dashboard/DeadlineList.vue';
import SectionCard from '@/Components/dashboard/SectionCard.vue';
import PackageProgressList, { type ProgressPackage } from '@/Components/package/PackageProgressList.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import { daysUntil, formatDate } from '@/Composables/useFormat';

const props = defineProps<{
    assignments: { id: number; subject: string; code: string; grade: string }[];
    package_stats: { draft: number; in_review: number; fix: number; items_to_revise: number };
    packages: ProgressPackage[];
    deadlines: { label: string; date: string | null }[];
}>();

const page = usePage();
const firstName = computed(() => String(page.props.auth?.user.name ?? '').split(' ')[0] ?? '');

// Tenggat terdekat yang belum lewat (atau yang pertama bila semuanya sudah lewat/belum diatur).
const nextDeadline = computed(() => {
    const dated = props.deadlines.filter((d) => daysUntil(d.date) !== null);
    const upcoming = dated.filter((d) => (daysUntil(d.date) ?? -1) >= 0).sort((a, b) => (daysUntil(a.date) ?? 0) - (daysUntil(b.date) ?? 0));

    return upcoming[0] ?? dated[0] ?? null;
});
const nextHint = computed(() => {
    const d = nextDeadline.value ? daysUntil(nextDeadline.value.date) : null;
    if (d === null) return '';
    return d < 0 ? `Lewat ${Math.abs(d)} hari` : d === 0 ? 'Hari ini' : `${d} hari lagi`;
});

const description = computed(() =>
    props.package_stats.items_to_revise > 0
        ? `Ada ${props.package_stats.items_to_revise} nomor soal yang diminta validator untuk diganti.`
        : 'Siapkan paket soal asesmen sumatif untuk mapel yang Anda ampu.',
);
</script>

<template>
    <Head title="Dashboard Guru" />

    <div class="@container">
        <DashboardHero eyebrow="Dashboard guru" :title="`Halo, ${firstName}`" :description="description">
            <Link href="/teacher/packages/create" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-primary-700 shadow-soft transition hover:bg-primary-50">
                <FilePlus2 class="size-4" /> Buat paket soal
            </Link>
            <Link href="/teacher/packages" class="inline-flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/30 transition hover:bg-white/20">
                <FolderOpen class="size-4" /> Paket saya
            </Link>
            <template #aside>
                <template v-if="nextDeadline">
                    <p class="text-xs font-bold uppercase tracking-wide text-white/70">Tenggat terdekat</p>
                    <p class="mt-1 text-lg font-extrabold">{{ nextHint }}</p>
                    <p class="text-xs text-white/80">{{ nextDeadline.label }} · {{ formatDate(nextDeadline.date) }}</p>
                </template>
            </template>
        </DashboardHero>

        <Link
            v-if="package_stats.items_to_revise > 0"
            href="/teacher/packages"
            class="mb-6 flex items-center gap-3 rounded-2xl border border-danger-500/30 bg-danger-50 p-4 text-sm text-danger-700 transition hover:border-danger-500/60 dark:bg-danger-500/10 dark:text-danger-500"
            role="alert"
        >
            <TriangleAlert class="size-5 shrink-0" />
            <span class="min-w-0 flex-1"><strong>{{ package_stats.items_to_revise }} nomor perlu diganti.</strong> Buka paket, baca catatan validator, lalu unggah ulang nomor tersebut.</span>
            <ArrowRight class="size-4 shrink-0" />
        </Link>

        <div class="mb-6 grid grid-cols-2 gap-3 @4xl:grid-cols-4 @4xl:gap-4">
            <StatCard label="Draft" :value="package_stats.draft" tone="primary" hint="Belum dikirim" href="/teacher/packages?status=draft"><FilePenLine class="size-6" /></StatCard>
            <StatCard label="Dalam telaah" :value="package_stats.in_review" tone="accent" hint="Di tangan validator" href="/teacher/packages"><ScanSearch class="size-6" /></StatCard>
            <StatCard label="Nomor perlu diganti" :value="package_stats.items_to_revise" tone="warning" hint="Menunggu unggah ulang" href="/teacher/packages"><RotateCcw class="size-6" /></StatCard>
            <StatCard label="Paket Fix" :value="package_stats.fix" tone="success" hint="Siap dipakai" href="/teacher/packages?status=fix"><BadgeCheck class="size-6" /></StatCard>
        </div>

        <div class="mb-6 grid gap-6 @4xl:grid-cols-3">
            <SectionCard class="@4xl:col-span-2" title="Paket saya pada periode ini" href="/teacher/packages" link-label="Semua paket" flush>
                <PackageProgressList :packages="packages" role="teacher" empty-title="Belum ada paket" empty-description="Buat paket soal untuk mapel yang Anda ampu pada periode ini." />
            </SectionCard>

            <SectionCard title="Tenggat periode ini" subtitle="Kirim paket sebelum tenggat validasi">
                <DeadlineList :deadlines="deadlines" />
            </SectionCard>
        </div>

        <SectionCard title="Penugasan mengajar" subtitle="Ketuk satu penugasan untuk mulai membuat paketnya" :count="assignments.length" flush>
            <div v-if="assignments.length" class="grid gap-3 p-5 sm:p-6 @xl:grid-cols-2 @4xl:grid-cols-3">
                <Link v-for="a in assignments" :key="a.id" href="/teacher/packages/create" class="group flex items-center gap-4 rounded-xl border border-line bg-surface p-4 transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-soft">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/15 dark:text-primary-300"><BookOpen class="size-5" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-bold text-ink">{{ a.subject }}</span>
                        <span class="block text-xs text-ink-faint">Kelas {{ a.grade }} · {{ a.code }}</span>
                    </span>
                    <ArrowRight class="size-4 shrink-0 text-ink-faint transition group-hover:translate-x-0.5 group-hover:text-primary-600" />
                </Link>
            </div>
            <EmptyState v-else title="Belum ada penugasan" description="Admin belum menautkan Anda ke mapel pada semester ini. Hubungi panitia asesmen." />
        </SectionCard>
    </div>
</template>
