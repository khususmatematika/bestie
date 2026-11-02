<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarClock, CircleCheck, Circle, GraduationCap, ShieldCheck, Users } from '@lucide/vue';
import { computed } from 'vue';
import { daysUntil, formatDate } from '@/Composables/useFormat';
import PageHeader from '@/Components/ui/PageHeader.vue';
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

function deadlineHint(date: string | null): string {
    const d = daysUntil(date);
    if (d === null) return 'Belum diatur';
    if (d < 0) return `Lewat ${Math.abs(d)} hari`;
    if (d === 0) return 'Hari ini';
    return `${d} hari lagi`;
}
</script>

<template>
    <Head title="Dashboard Admin" />
    <PageHeader title="Dashboard" description="Ringkasan persiapan asesmen sumatif pada periode yang dipilih." />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Guru terdaftar" :value="stats.teachers" href="/admin/teachers"><Users class="size-6" /></StatCard>
        <StatCard label="Validator" :value="stats.validators" tone="accent" href="/admin/validators"><ShieldCheck class="size-6" /></StatCard>
        <StatCard label="Mapel aktif" :value="stats.subjects" tone="warning" href="/admin/subjects"><GraduationCap class="size-6" /></StatCard>
        <StatCard label="Periode dibuka" :value="stats.periods_open" tone="success" href="/admin/periods"><CalendarClock class="size-6" /></StatCard>
    </div>

    <section class="card mt-6 p-6">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-bold text-ink">Paket soal pada periode terpilih</h2>
            <Link href="/admin/packages" class="text-sm font-semibold text-primary-600 hover:underline">Monitoring paket</Link>
        </div>
        <ul class="mt-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <li v-for="p in packages" :key="p.value">
                <Link :href="`/admin/packages?status=${p.value}`" class="block rounded-xl border border-line p-3 transition hover:bg-surface-2">
                    <p class="text-2xl font-extrabold tabular-nums text-ink">{{ p.total }}</p>
                    <StatusBadge :status="p.value" />
                </Link>
            </li>
        </ul>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="card overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-line px-6 py-4">
                <h2 class="text-base font-bold text-ink">Kemajuan telaah per mapel</h2>
                <Link href="/admin/packages" class="text-sm font-semibold text-primary-600 hover:underline">Monitoring</Link>
            </div>
            <ul v-if="subject_progress.length" class="divide-y divide-line">
                <li v-for="s in subject_progress" :key="s.subject">
                    <Link href="/admin/packages" class="block px-4 py-3 transition hover:bg-surface-2 sm:px-6">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="min-w-0 truncate font-semibold text-ink">{{ s.subject }}</p>
                            <p class="shrink-0 text-xs text-ink-faint">{{ s.fix }}/{{ s.packages }} paket Fix · {{ s.approved }}/{{ s.total }} nomor</p>
                        </div>
                        <ProgressBar class="mt-2" :value="s.percent" :label="`Kemajuan ${s.subject}`" />
                    </Link>
                </li>
            </ul>
            <p v-else class="px-6 py-8 text-center text-sm text-ink-soft">Belum ada paket pada periode terpilih.</p>
        </section>

        <section class="card overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-line px-6 py-4">
                <h2 class="text-base font-bold text-ink">Belum membuat paket</h2>
                <span class="text-sm font-bold tabular-nums text-warning-700 dark:text-warning-500">{{ missing_packages.total }}</span>
            </div>
            <ul v-if="missing_packages.rows.length" class="divide-y divide-line">
                <li v-for="(r, i) in missing_packages.rows" :key="i">
                    <Link href="/admin/assignments" class="flex flex-wrap items-center justify-between gap-x-3 px-4 py-2.5 text-sm transition hover:bg-surface-2 sm:px-6">
                        <span class="font-semibold text-ink">{{ r.teacher }}</span>
                        <span class="text-xs text-ink-soft">{{ r.subject }} · Kelas {{ r.grade }}</span>
                    </Link>
                </li>
                <li v-if="missing_packages.total > missing_packages.rows.length" class="px-6 py-2.5 text-xs text-ink-faint">… dan {{ missing_packages.total - missing_packages.rows.length }} lainnya</li>
            </ul>
            <p v-else class="px-6 py-8 text-center text-sm text-ink-soft">Semua penugasan sudah memiliki paket.</p>
        </section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-5">
        <section class="card p-6 lg:col-span-3">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-ink">Persiapan awal</h2>
                    <p class="text-sm text-ink-soft">Selesaikan langkah berikut agar guru dapat mulai mengunggah soal.</p>
                </div>
                <p class="text-2xl font-extrabold tabular-nums text-primary-600">{{ progress }}%</p>
            </div>

            <div class="mt-4 h-2 overflow-hidden rounded-full bg-surface-2" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full bg-primary-600 transition-all duration-500" :style="{ width: `${progress}%` }" />
            </div>

            <ul class="mt-5 divide-y divide-line">
                <li v-for="item in checklist" :key="item.label">
                    <Link :href="item.href" class="flex items-center gap-3 py-3 text-sm transition hover:text-primary-600">
                        <component :is="item.done ? CircleCheck : Circle" class="size-5 shrink-0" :class="item.done ? 'text-success-500' : 'text-ink-faint'" />
                        <span :class="item.done ? 'text-ink-soft line-through decoration-line' : 'font-semibold text-ink'">{{ item.label }}</span>
                    </Link>
                </li>
            </ul>
        </section>

        <section class="card p-6 lg:col-span-2">
            <h2 class="text-base font-bold text-ink"><Link href="/admin/semesters" class="hover:underline">Semester aktif</Link></h2>
            <div v-if="semester" class="mt-3 flex items-center gap-3">
                <p class="text-lg font-bold text-ink">{{ semester.label }}</p>
                <StatusBadge :status="semester.status" />
            </div>
            <p v-else class="mt-3 text-sm text-ink-soft">Belum ada semester aktif.</p>

            <h3 class="mt-6 text-sm font-bold text-ink">Tenggat periode terpilih</h3>
            <div class="mt-3 space-y-3">
                <Link v-for="d in deadlines" :key="d.label" href="/admin/periods" class="flex items-center justify-between gap-3 rounded-xl bg-surface-2 px-4 py-3 transition hover:bg-surface-2/60 hover:ring-1 hover:ring-primary-300">
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ d.label }}</p>
                        <p class="text-xs text-ink-faint">{{ formatDate(d.date) }}</p>
                    </div>
                    <span class="text-xs font-semibold text-ink-soft">{{ deadlineHint(d.date) }}</span>
                </Link>
                <p v-if="!deadlines.length" class="text-sm text-ink-soft">Belum ada periode asesmen.</p>
            </div>
        </section>
    </div>
</template>
