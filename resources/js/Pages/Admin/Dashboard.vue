<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CalendarClock, Circle, CircleCheck, FolderKanban, GraduationCap, ShieldCheck, Users } from '@lucide/vue';
import { computed } from 'vue';
import DashboardHero from '@/Components/dashboard/DashboardHero.vue';
import DeadlineList from '@/Components/dashboard/DeadlineList.vue';
import SectionCard from '@/Components/dashboard/SectionCard.vue';
import ProgressBar from '@/Components/ui/ProgressBar.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import StatusBadge from '@/Components/ui/StatusBadge.vue';

const props = defineProps<{
    stats: { teachers: number; validators: number; subjects: number; periods_open: number };
    semester: { id: number; label: string; status: string } | null;
    deadlines: { label: string; date: string | null }[];
    checklist: { label: string; done: boolean; href: string }[];
    packages: { value: string; label: string; total: number }[];
    subject_progress: { subject: string; packages: number; fix: number; total: number; approved: number; percent: number }[];
    missing_packages: { total: number; rows: { teacher: string; subject: string; grade: string }[] };
}>();

const doneCount = computed(() => props.checklist.filter((c) => c.done).length);
const progress = computed(() => Math.round((doneCount.value / Math.max(props.checklist.length, 1)) * 100));
</script>

<template>
    <Head title="Dashboard Admin" />

    <div class="@container">
        <DashboardHero eyebrow="Dashboard admin" title="Persiapan asesmen sumatif" :description="semester ? `Semester aktif: ${semester.label}.` : 'Belum ada semester aktif. Mulai dari menu Semester.'">
            <Link href="/admin/packages" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-primary-700 shadow-soft transition hover:bg-primary-50">
                <FolderKanban class="size-4" /> Monitoring paket
            </Link>
            <Link href="/admin/assignments" class="inline-flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/30 transition hover:bg-white/20">
                <Users class="size-4" /> Penugasan mengajar
            </Link>
            <template #aside>
                <p class="text-xs font-bold uppercase tracking-wide text-white/70">Persiapan awal</p>
                <p class="mt-1 text-3xl font-extrabold tabular-nums">{{ progress }}%</p>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/20" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
                    <div class="h-full rounded-full bg-white transition-all duration-500" :style="{ width: `${progress}%` }" />
                </div>
                <p class="mt-1.5 text-xs text-white/80">{{ doneCount }} dari {{ checklist.length }} langkah selesai</p>
            </template>
        </DashboardHero>

        <div class="mb-6 grid grid-cols-2 gap-3 @4xl:grid-cols-4 @4xl:gap-4">
            <StatCard label="Guru terdaftar" :value="stats.teachers" href="/admin/teachers"><Users class="size-6" /></StatCard>
            <StatCard label="Validator" :value="stats.validators" tone="accent" href="/admin/validators"><ShieldCheck class="size-6" /></StatCard>
            <StatCard label="Mapel aktif" :value="stats.subjects" tone="warning" href="/admin/subjects"><GraduationCap class="size-6" /></StatCard>
            <StatCard label="Periode dibuka" :value="stats.periods_open" tone="success" href="/admin/periods"><CalendarClock class="size-6" /></StatCard>
        </div>

        <div class="mb-6 grid gap-6 @4xl:grid-cols-5">
            <SectionCard class="@4xl:col-span-3" title="Persiapan awal" subtitle="Selesaikan langkah berikut agar guru dapat mulai mengunggah soal" flush>
                <ul class="divide-y divide-line">
                    <li v-for="(item, n) in checklist" :key="item.label">
                        <Link :href="item.href" class="group flex items-center gap-3 px-5 py-3 text-sm transition hover:bg-surface-2 sm:px-6">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold" :class="item.done ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500' : 'bg-surface-2 text-ink-faint'">
                                <CircleCheck v-if="item.done" class="size-4" />
                                <Circle v-else class="size-4" />
                                <span class="sr-only">{{ item.done ? 'Selesai' : `Langkah ${n + 1}` }}</span>
                            </span>
                            <span class="min-w-0 flex-1" :class="item.done ? 'text-ink-soft line-through decoration-danger-500 decoration-2' : 'font-semibold text-ink'">{{ item.label }}</span>
                            <ArrowRight class="size-4 shrink-0 text-ink-faint opacity-0 transition group-hover:opacity-100" />
                        </Link>
                    </li>
                </ul>
            </SectionCard>

            <div class="space-y-6 @4xl:col-span-2">
                <SectionCard title="Semester aktif" href="/admin/semesters" link-label="Kelola">
                    <div v-if="semester" class="flex items-center gap-3">
                        <p class="text-lg font-bold text-ink">{{ semester.label }}</p>
                        <StatusBadge :status="semester.status" />
                    </div>
                    <p v-else class="text-sm text-ink-soft">Belum ada semester aktif.</p>
                </SectionCard>

                <SectionCard title="Tenggat periode terpilih" href="/admin/periods" link-label="Atur">
                    <DeadlineList :deadlines="deadlines" href="/admin/periods" />
                </SectionCard>
            </div>
        </div>

        <SectionCard class="mb-6" title="Paket soal pada periode terpilih" href="/admin/packages" link-label="Monitoring paket">
            <ul class="grid grid-cols-2 gap-3 @2xl:grid-cols-3 @4xl:grid-cols-6">
                <li v-for="p in packages" :key="p.value">
                    <Link :href="`/admin/packages?status=${p.value}`" class="block rounded-xl border border-line p-3 transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-soft">
                        <p class="text-2xl font-extrabold tabular-nums" :class="p.total ? 'text-ink' : 'text-ink-faint'">{{ p.total }}</p>
                        <StatusBadge :status="p.value" />
                    </Link>
                </li>
            </ul>
        </SectionCard>

        <div class="grid gap-6 @4xl:grid-cols-2">
            <SectionCard title="Kemajuan telaah per mapel" href="/admin/packages" link-label="Monitoring" flush>
                <ul v-if="subject_progress.length" class="divide-y divide-line">
                    <li v-for="s in subject_progress" :key="s.subject">
                        <Link href="/admin/packages" class="block px-5 py-3 transition hover:bg-surface-2 sm:px-6">
                            <div class="flex items-baseline justify-between gap-3">
                                <p class="min-w-0 truncate font-semibold text-ink">{{ s.subject }}</p>
                                <p class="shrink-0 text-xs text-ink-faint">{{ s.fix }}/{{ s.packages }} paket Fix · {{ s.approved }}/{{ s.total }} nomor</p>
                            </div>
                            <ProgressBar class="mt-2" :value="s.percent" :label="`Kemajuan ${s.subject}`" />
                        </Link>
                    </li>
                </ul>
                <p v-else class="px-6 py-8 text-center text-sm text-ink-soft">Belum ada paket pada periode terpilih.</p>
            </SectionCard>

            <SectionCard title="Belum membuat paket" subtitle="Penugasan yang belum punya paket pada periode ini" :count="missing_packages.total" flush>
                <ul v-if="missing_packages.rows.length" class="divide-y divide-line">
                    <li v-for="(r, i) in missing_packages.rows" :key="i">
                        <Link href="/admin/assignments" class="flex flex-wrap items-center justify-between gap-x-3 px-5 py-2.5 text-sm transition hover:bg-surface-2 sm:px-6">
                            <span class="font-semibold text-ink">{{ r.teacher }}</span>
                            <span class="text-xs text-ink-soft">{{ r.subject }} · Kelas {{ r.grade }}</span>
                        </Link>
                    </li>
                    <li v-if="missing_packages.total > missing_packages.rows.length" class="px-6 py-2.5 text-xs text-ink-faint">… dan {{ missing_packages.total - missing_packages.rows.length }} lainnya</li>
                </ul>
                <p v-else class="px-6 py-8 text-center text-sm text-ink-soft">Semua penugasan sudah memiliki paket.</p>
            </SectionCard>
        </div>
    </div>
</template>
