<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BadgeCheck, Inbox, RefreshCw, ScanSearch, Sparkles } from '@lucide/vue';
import PackageProgressList, { type ProgressPackage } from '@/Components/package/PackageProgressList.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import { formatDate } from '@/Composables/useFormat';

defineProps<{
    stats: { new: number; in_review: number; fix: number; rechecks: number };
    period: string | null;
    queue: ProgressPackage[];
    deadline: string | null;
}>();
</script>

<template>
    <Head title="Dashboard Validator" />
    <PageHeader title="Dashboard validator" :description="period ? `Antrean telaah pada ${period}.` : 'Belum ada periode asesmen yang dipilih.'">
        <Link href="/validator/inbox" class="btn-primary"><Inbox class="size-4" /> Buka inbox telaah</Link>
    </PageHeader>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Paket baru" :value="stats.new" tone="primary" href="/validator/inbox?status=terkirim"><Sparkles class="size-6" /></StatCard>
        <StatCard label="Sedang ditelaah / revisi" :value="stats.in_review" tone="accent" href="/validator/inbox"><ScanSearch class="size-6" /></StatCard>
        <StatCard label="Nomor menunggu cek ulang" :value="stats.rechecks" tone="warning" href="/validator/inbox"><RefreshCw class="size-6" /></StatCard>
        <StatCard label="Paket Fix" :value="stats.fix" tone="success" href="/validator/inbox?status=fix"><BadgeCheck class="size-6" /></StatCard>
    </div>

    <section class="card mt-6 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4">
            <h2 class="text-base font-bold text-ink">Antrean telaah</h2>
            <span v-if="deadline" class="text-xs font-semibold text-ink-soft">Tenggat validasi: {{ formatDate(deadline) }}</span>
        </div>
        <PackageProgressList :packages="queue" role="validator" empty-title="Belum ada paket masuk" empty-description="Paket yang dikirim guru kepada Anda akan muncul di sini." />
    </section>

    <section class="card mt-6 p-6">
        <h2 class="text-base font-bold text-ink">Cara menelaah</h2>
        <ol class="mt-3 list-decimal space-y-1.5 pl-5 text-sm text-ink-soft">
            <li>Buka salah satu paket di inbox.</li>
            <li>Telaah nomor demi nomor: kisi-kisi di kiri, soal di kanan. Gunakan daftar periksa dan catatan.</li>
            <li>Pilih <strong>Setujui</strong> atau <strong>Minta ganti</strong> (catatan wajib). Guru akan mengunggah ulang nomor tersebut.</li>
            <li>Saat semua nomor disetujui (100%), paket otomatis berstatus <strong>Fix</strong>.</li>
        </ol>
        <p class="mt-3 text-xs text-ink-faint">Anda juga dapat memvalidasi paket sendiri: ganti peran ke Guru, buat paket, lalu pilih diri sendiri sebagai validator (bila diizinkan admin).</p>
    </section>
</template>
