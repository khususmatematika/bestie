<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BadgeCheck, CircleCheckBig, FileSearch, Inbox, MessageSquareWarning, RefreshCw, ScanSearch, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import DashboardHero from '@/Components/dashboard/DashboardHero.vue';
import DeadlineList from '@/Components/dashboard/DeadlineList.vue';
import SectionCard from '@/Components/dashboard/SectionCard.vue';
import PackageProgressList, { type ProgressPackage } from '@/Components/package/PackageProgressList.vue';
import StatCard from '@/Components/ui/StatCard.vue';

const props = defineProps<{
    stats: { new: number; in_review: number; fix: number; rechecks: number };
    period: string | null;
    queue: ProgressPackage[];
    deadline: string | null;
}>();

const waiting = computed(() => props.stats.new + props.stats.rechecks);

const steps = [
    { icon: FileSearch, title: 'Buka paket', text: 'Pilih salah satu paket di inbox.' },
    { icon: ScanSearch, title: 'Telaah per nomor', text: 'Kisi-kisi di kiri, soal di kanan. Pakai daftar periksa dan catatan.' },
    { icon: MessageSquareWarning, title: 'Putuskan', text: 'Setujui, atau minta ganti dengan catatan wajib.' },
    { icon: CircleCheckBig, title: 'Fix otomatis', text: 'Semua nomor disetujui (100%) → paket berstatus Fix.' },
];
</script>

<template>
    <Head title="Dashboard Validator" />

    <div class="@container">
        <DashboardHero eyebrow="Dashboard validator" title="Antrean telaah" :description="period ? `Paket yang menunggu telaah Anda pada ${period}.` : 'Belum ada periode asesmen yang dipilih.'">
            <Link href="/validator/inbox" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-primary-700 shadow-soft transition hover:bg-primary-50">
                <Inbox class="size-4" /> Buka inbox telaah
            </Link>
            <template #aside>
                <p class="text-xs font-bold uppercase tracking-wide text-white/70">Menunggu Anda</p>
                <p class="mt-1 text-3xl font-extrabold tabular-nums">{{ waiting }}</p>
                <p class="text-xs text-white/80">{{ stats.new }} paket baru · {{ stats.rechecks }} nomor cek ulang</p>
            </template>
        </DashboardHero>

        <div class="mb-6 grid grid-cols-2 gap-3 @4xl:grid-cols-4 @4xl:gap-4">
            <StatCard label="Paket baru" :value="stats.new" tone="primary" hint="Belum Anda buka" href="/validator/inbox?status=terkirim"><Sparkles class="size-6" /></StatCard>
            <StatCard label="Sedang ditelaah / revisi" :value="stats.in_review" tone="accent" hint="Dalam proses" href="/validator/inbox"><ScanSearch class="size-6" /></StatCard>
            <StatCard label="Menunggu cek ulang" :value="stats.rechecks" tone="warning" hint="Nomor yang diunggah ulang" href="/validator/inbox"><RefreshCw class="size-6" /></StatCard>
            <StatCard label="Paket Fix" :value="stats.fix" tone="success" hint="Selesai divalidasi" href="/validator/inbox?status=fix"><BadgeCheck class="size-6" /></StatCard>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-6 @4xl:grid-cols-3">
            <SectionCard class="@4xl:col-span-2" title="Antrean telaah" href="/validator/inbox" link-label="Buka inbox" :count="queue.length" flush>
                <PackageProgressList :packages="queue" role="validator" empty-title="Belum ada paket masuk" empty-description="Paket yang dikirim guru kepada Anda akan muncul di sini." />
            </SectionCard>

            <SectionCard title="Tenggat validasi" subtitle="Selesaikan telaah sebelum tanggal ini">
                <DeadlineList :deadlines="deadline ? [{ label: 'Tenggat validasi', date: deadline }] : []" empty-text="Tenggat belum diatur untuk periode ini." />
            </SectionCard>
        </div>

        <SectionCard title="Cara menelaah" subtitle="Empat langkah dari paket masuk sampai Fix">
            <ol class="grid grid-cols-1 gap-4 @xl:grid-cols-2 @4xl:grid-cols-4">
                <li v-for="(s, i) in steps" :key="s.title" class="relative rounded-xl border border-line bg-surface-2/50 p-4">
                    <span class="absolute right-3 top-2 text-3xl font-extrabold text-ink/5 dark:text-white/10">{{ i + 1 }}</span>
                    <span class="flex size-9 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/15 dark:text-primary-300"><component :is="s.icon" class="size-5" /></span>
                    <p class="mt-3 text-sm font-bold text-ink">{{ s.title }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-ink-soft">{{ s.text }}</p>
                </li>
            </ol>
            <p class="mt-4 text-xs text-ink-faint">Anda juga dapat memvalidasi paket sendiri: ganti peran ke Guru, buat paket, lalu pilih diri sendiri sebagai validator (bila diizinkan admin).</p>
        </SectionCard>
    </div>
</template>
