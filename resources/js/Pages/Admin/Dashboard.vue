<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CalendarClock, Check, ClipboardX, FolderKanban, GraduationCap, Search, ShieldCheck, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import DashboardHero from '@/Components/dashboard/DashboardHero.vue';
import DeadlineList from '@/Components/dashboard/DeadlineList.vue';
import SectionCard from '@/Components/dashboard/SectionCard.vue';
import Modal from '@/Components/ui/Modal.vue';
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

// Daftar guru yang belum membuat paket: ringkas di dashboard, rinci (per guru) saat diklik.
const missingOpen = ref(false);
const missingQuery = ref('');
const missingByTeacher = computed(() => {
    const q = missingQuery.value.trim().toLowerCase();
    const map = new Map<string, string[]>();
    for (const r of props.missing_packages.rows) {
        if (q && !`${r.teacher} ${r.subject}`.toLowerCase().includes(q)) continue;
        map.set(r.teacher, [...(map.get(r.teacher) ?? []), `${r.subject} · Kelas ${r.grade}`]);
    }

    return [...map.entries()];
});
const missingTeachers = computed(() => new Set(props.missing_packages.rows.map((r) => r.teacher)).size);
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

        <!-- Persiapan awal: deretan langkah mendatar, tanpa ruang kosong; ringkas bila sudah selesai -->
        <section class="card mb-6 p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-bold text-ink">Persiapan awal</h2>
                    <p class="text-xs text-ink-faint">{{ progress === 100 ? 'Semua langkah selesai. Guru sudah dapat mengunggah soal.' : 'Selesaikan langkah berikut agar guru dapat mulai mengunggah soal.' }}</p>
                </div>
                <span class="badge tabular-nums" :class="progress === 100 ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500' : 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300'">{{ doneCount }}/{{ checklist.length }}</span>
            </div>
            <ol class="mt-4 grid gap-2.5 @xl:grid-cols-2 @4xl:grid-cols-5">
                <li v-for="(item, n) in checklist" :key="item.label">
                    <Link :href="item.href" class="group flex h-full items-center gap-3 rounded-xl border p-3 transition hover:-translate-y-0.5 hover:shadow-soft" :class="item.done ? 'border-success-500/30 bg-success-50/60 dark:bg-success-500/10' : 'border-line bg-surface hover:border-primary-300'">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-extrabold" :class="item.done ? 'bg-success-500 text-white' : 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300'">
                            <Check v-if="item.done" class="size-4" />
                            <template v-else>{{ n + 1 }}</template>
                        </span>
                        <span class="min-w-0 flex-1 text-sm" :class="item.done ? 'text-ink-soft line-through decoration-danger-500 decoration-2' : 'font-semibold text-ink'">{{ item.label }}</span>
                    </Link>
                </li>
            </ol>
        </section>

        <div class="grid items-start gap-6 @4xl:grid-cols-3">
            <div class="space-y-6 @4xl:col-span-2">
                <SectionCard title="Paket soal pada periode terpilih" href="/admin/packages" link-label="Monitoring paket">
                    <ul class="grid grid-cols-2 gap-3 @2xl:grid-cols-3">
                        <li v-for="p in packages" :key="p.value">
                            <Link :href="`/admin/packages?status=${p.value}`" class="block rounded-xl border border-line p-3 transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-soft">
                                <p class="text-2xl font-extrabold tabular-nums" :class="p.total ? 'text-ink' : 'text-ink-faint'">{{ p.total }}</p>
                                <StatusBadge :status="p.value" />
                            </Link>
                        </li>
                    </ul>
                </SectionCard>

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
            </div>

            <div class="space-y-6">
                <!-- Belum membuat paket: hanya jumlah; nama guru muncul saat diklik -->
                <button
                    type="button"
                    class="card group flex w-full items-center gap-4 p-5 text-left transition hover:-translate-y-0.5 hover:shadow-soft"
                    :class="missing_packages.total ? 'hover:border-warning-500/60' : 'cursor-default'"
                    :disabled="!missing_packages.total"
                    @click="missingOpen = true"
                >
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl" :class="missing_packages.total ? 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-500' : 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500'">
                        <ClipboardX v-if="missing_packages.total" class="size-6" />
                        <Check v-else class="size-6" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-2xl font-extrabold tabular-nums text-ink">{{ missing_packages.total }}</span>
                        <span class="block text-sm text-ink-soft">{{ missing_packages.total ? 'Penugasan belum membuat paket' : 'Semua penugasan sudah membuat paket' }}</span>
                    </span>
                    <span v-if="missing_packages.total" class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-primary-600">Lihat guru <ArrowRight class="size-3.5 transition group-hover:translate-x-0.5" /></span>
                </button>

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
    </div>

    <Modal :open="missingOpen" title="Belum membuat paket" :description="`${missingTeachers} guru · ${missing_packages.total} penugasan pada periode terpilih`" size="lg" @close="missingOpen = false">
        <div class="relative mb-3">
            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-faint" />
            <input v-model="missingQuery" type="search" class="input pl-9" placeholder="Cari nama guru atau mapel" aria-label="Cari guru atau mapel" />
        </div>
        <ul v-if="missingByTeacher.length" class="max-h-[55vh] divide-y divide-line overflow-y-auto rounded-xl border border-line">
            <li v-for="[teacher, subjects] in missingByTeacher" :key="teacher" class="flex flex-wrap items-center gap-x-4 gap-y-1.5 px-4 py-3">
                <p class="min-w-0 flex-1 font-semibold text-ink">{{ teacher }}</p>
                <div class="flex flex-wrap gap-1.5">
                    <span v-for="s in subjects" :key="s" class="badge bg-surface-2 text-ink-soft">{{ s }}</span>
                </div>
            </li>
        </ul>
        <p v-else class="py-6 text-center text-sm text-ink-soft">Tidak ada yang cocok.</p>
        <div class="mt-4 flex justify-end gap-2">
            <Link href="/admin/assignments" class="btn-secondary">Buka penugasan</Link>
            <button type="button" class="btn-primary" @click="missingOpen = false">Tutup</button>
        </div>
    </Modal>
</template>
