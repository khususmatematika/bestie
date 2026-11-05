<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Download, FileSpreadsheet, FileText, LoaderCircle, MonitorCheck, PackageCheck } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import { formatDateTime } from '@/Composables/useFormat';
import type { DownloadsInfo } from '@/types';

const props = defineProps<{ downloads: DownloadsInfo }>();

const requesting = ref(false);
let timer: ReturnType<typeof setInterval> | undefined;
let sawQueued = false;

function stopPolling(): void {
    if (timer !== undefined) clearInterval(timer);
    timer = undefined;
}

// Selama naskah final disiapkan di antrian, cek ulang tiap 3 detik; begitu siap, unduh otomatis (langkah 8).
watch(
    () => props.downloads.final?.status,
    (status) => {
        if (status === 'queued') {
            sawQueued = true;
            if (timer === undefined) timer = setInterval(() => router.reload({ only: ['downloads'] }), 3000);
        } else {
            stopPolling();
            if (status === 'ready' && sawQueued && props.downloads.final?.docx_url) {
                sawQueued = false;
                window.location.href = props.downloads.final.docx_url;
            }
        }
    },
    { immediate: true },
);

onBeforeUnmount(stopPolling);

function requestFinal(): void {
    requesting.value = true;
    router.post(props.downloads.final_url, {}, { preserveScroll: true, onFinish: () => (requesting.value = false) });
}
</script>

<template>
    <section class="card mb-6 p-6" aria-labelledby="dl-title">
        <h2 id="dl-title" class="mb-1 text-base font-bold text-ink">Unduhan</h2>
        <p class="mb-4 text-sm text-ink-soft">
            Rekap memuat identitas paket, hasil daftar periksa validator per butir (biru terpenuhi, merah tidak), kemiripan tertinggi, catatan, dan waktu tiap nomor. Unduhan sebelum paket Fix diberi tanda “BELUM FINAL”.
        </p>

        <div class="flex flex-wrap gap-2">
            <a :href="downloads.recap_docx_url" class="btn-secondary"><FileText class="size-4" /> Rekap validasi siap cetak (DOCX)</a>
            <a :href="downloads.recap_url" class="btn-secondary"><FileSpreadsheet class="size-4" /> Rekap validasi (XLSX)</a>
            <a :href="downloads.validated_url" class="btn-secondary"><FileText class="size-4" /> Soal tervalidasi (DOCX)</a>
        </div>

        <div v-if="downloads.is_final" class="mt-5 rounded-xl border border-line bg-surface-2 p-4">
            <p class="mb-3 flex items-center gap-2 text-sm font-semibold text-ink"><PackageCheck class="size-4 text-success-600" /> Naskah final</p>

            <div v-if="downloads.final?.status === 'ready'" class="flex flex-wrap items-center gap-2">
                <a v-if="downloads.final.docx_url" :href="downloads.final.docx_url" class="btn-primary"><Download class="size-4" /> Unduh final (DOCX)</a>
                <a v-if="downloads.final.pdf_url" :href="downloads.final.pdf_url" class="btn-secondary"><Download class="size-4" /> PDF</a>
                <div v-if="downloads.final.stale" class="flex w-full flex-wrap items-center gap-2 rounded-lg bg-warning-50 p-3 text-xs text-warning-700 dark:bg-warning-500/10 dark:text-warning-500" role="note">
                    <span class="min-w-0 flex-1">Naskah ini dibuat dengan format/kop lama. Buat ulang agar memakai format terbaru.</span>
                    <button type="button" class="btn-primary btn-sm" :disabled="requesting" @click="requestFinal">
                        <LoaderCircle v-if="requesting" class="size-3.5 animate-spin" /> Buat ulang naskah final
                    </button>
                </div>
                <p class="w-full text-xs text-ink-faint">
                    No. dokumen {{ downloads.final.doc_number }} · dibuat {{ formatDateTime(downloads.final.generated_at) }}
                    <template v-if="downloads.final.sha256"> · SHA-256 {{ downloads.final.sha256.slice(0, 16) }}…</template>
                </p>
            </div>

            <p v-else-if="downloads.final?.status === 'queued'" class="flex items-center gap-2 text-sm text-ink-soft" role="status">
                <LoaderCircle class="size-4 animate-spin" /> Naskah final sedang disiapkan; unduhan dimulai otomatis begitu siap.
            </p>

            <div v-else class="flex flex-wrap items-center gap-3">
                <button type="button" class="btn-primary" :disabled="requesting" @click="requestFinal">
                    <LoaderCircle v-if="requesting" class="size-4 animate-spin" /> <Download v-else class="size-4" /> Siapkan naskah final
                </button>
                <p v-if="downloads.final?.error" class="text-xs text-danger-600">Percobaan sebelumnya gagal: {{ downloads.final.error }}</p>
            </div>
        </div>

        <div v-if="downloads.cbt_url" class="mt-4 rounded-xl border border-line bg-surface-2 p-4">
            <p class="mb-1 flex items-center gap-2 text-sm font-semibold text-ink"><MonitorCheck class="size-4 text-primary-600" /> Naskah untuk CBT sekolah</p>
            <p class="mb-3 text-xs text-ink-soft">Berformat “Template Ujian CBT” (@META-NOMOR, @KONTEN-SOAL, dst.), siap diunggah ke CBT. Soal menjodohkan diekspor sebagai Essay karena template CBT tidak memiliki jenis menjodohkan.</p>
            <a :href="downloads.cbt_url" class="btn-primary"><Download class="size-4" /> Unduh naskah CBT (DOCX)</a>
        </div>
    </section>
</template>
