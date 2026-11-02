<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, Download, FileSpreadsheet, KeyRound, LoaderCircle, Upload } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { formatDateTime } from '@/Composables/useFormat';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatusBadge from '@/Components/ui/StatusBadge.vue';

interface RowResult {
    row: number;
    name: string;
    status: 'created' | 'updated' | 'skipped' | 'failed';
    message: string;
}

interface Batch {
    id: number;
    file: string;
    status: string;
    status_label: string;
    finished: boolean;
    total: number;
    success: number;
    failed: number;
    error_message: string | null;
    has_accounts: boolean;
    results: RowResult[];
    created_by: string | null;
    created_at: string | null;
}

const props = defineProps<{ batches: Batch[]; hasRunning: boolean }>();

const form = useForm<{ file: File | null; update_existing: boolean; must_change_password: boolean }>({ file: null, update_existing: false, must_change_password: true });
const fileInput = ref<HTMLInputElement | null>(null);
const expanded = ref<number | null>(null);
const dragging = ref(false);

function pick(files: FileList | null): void {
    form.file = files?.[0] ?? null;
}

function onDrop(event: DragEvent): void {
    dragging.value = false;
    pick(event.dataTransfer?.files ?? null);
}

function submit(): void {
    form.post('/admin/teachers/import', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

// Sondase (polling) selama masih ada impor yang berjalan di antrian.
let timer: ReturnType<typeof setInterval> | undefined;

function startPolling(): void {
    stopPolling();
    timer = setInterval(() => router.reload({ only: ['batches', 'hasRunning'] }), 2500);
}

function stopPolling(): void {
    if (timer) clearInterval(timer);
    timer = undefined;
}

onMounted(() => props.hasRunning && startPolling());
onBeforeUnmount(stopPolling);
watch(
    () => props.hasRunning,
    (running) => (running ? startPolling() : stopPolling()),
);

function afterDownload(): void {
    // Kartu akun dihapus server setelah diunduh; segarkan agar tombolnya hilang.
    setTimeout(() => router.reload({ only: ['batches'] }), 1500);
}

function sizeLabel(bytes: number | null): string {
    if (!bytes) return '';
    return bytes > 1_048_576 ? `${(bytes / 1_048_576).toFixed(1)} MB` : `${Math.ceil(bytes / 1024)} KB`;
}
</script>

<template>
    <Head title="Impor Akun Guru" />

    <Link href="/admin/teachers" class="mb-3 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-soft hover:text-primary-600">
        <ArrowLeft class="size-4" /> Kembali ke akun guru
    </Link>

    <PageHeader title="Impor akun guru" description="Unggah berkas Excel/CSV sesuai template. Sistem membuat akun, menautkan mapel &amp; tingkat, lalu menyiapkan kartu akun berisi kata sandi awal." />

    <div class="grid gap-6 lg:grid-cols-5">
        <section class="card p-6 lg:col-span-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-bold text-ink">1. Unggah berkas</h2>
                <a href="/admin/teachers/import/template" class="btn-secondary btn-sm"><Download class="size-3.5" /> Unduh template</a>
            </div>

            <form class="mt-4 space-y-4" @submit.prevent="submit">
                <label
                    class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-10 text-center transition"
                    :class="dragging ? 'border-primary-500 bg-primary-50 dark:bg-primary-500/10' : 'border-line hover:border-primary-300 hover:bg-surface-2'"
                    @dragover.prevent="dragging = true"
                    @dragleave="dragging = false"
                    @drop.prevent="onDrop"
                >
                    <span class="flex size-12 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-500/15 dark:text-primary-300"><FileSpreadsheet class="size-6" /></span>
                    <span v-if="form.file" class="mt-3 text-sm font-bold text-ink">{{ form.file.name }}</span>
                    <span v-else class="mt-3 text-sm font-semibold text-ink">Klik atau seret berkas ke sini</span>
                    <span class="mt-1 text-xs text-ink-faint">{{ form.file ? sizeLabel(form.file.size) : '.xlsx atau .csv · maks. 1.000 baris' }}</span>
                    <input ref="fileInput" type="file" accept=".xlsx,.csv" class="sr-only" @change="pick(($event.target as HTMLInputElement).files)" />
                </label>
                <p v-if="form.errors.file" class="error-text" role="alert">{{ form.errors.file }}</p>

                <label class="flex cursor-pointer items-start gap-2.5 text-sm text-ink">
                    <input v-model="form.update_existing" type="checkbox" class="mt-0.5 size-4 accent-primary-600" />
                    <span>
                        Perbarui data guru yang sudah terdaftar
                        <span class="block text-xs text-ink-faint">Jika dimatikan, baris dengan NIP/email yang sudah ada akan dilewati (tidak diduplikasi).</span>
                    </span>
                </label>

                <label class="flex cursor-pointer items-start gap-2.5 text-sm text-ink">
                    <input v-model="form.must_change_password" type="checkbox" class="mt-0.5 size-4 accent-primary-600" />
                    <span>
                        Wajib ganti kata sandi saat login pertama
                        <span class="block text-xs text-ink-faint">Berlaku untuk semua akun pada berkas ini, baik kata sandi dari kolom password maupun yang dibuat acak.</span>
                    </span>
                </label>

                <button type="submit" class="btn-primary" :disabled="!form.file || form.processing">
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    <Upload v-else class="size-4" />
                    Mulai impor
                </button>
            </form>
        </section>

        <section class="card p-6 lg:col-span-2">
            <h2 class="text-base font-bold text-ink">Petunjuk kolom</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div><dt class="font-semibold text-ink">nama</dt><dd class="text-ink-soft">Wajib.</dd></div>
                <div><dt class="font-semibold text-ink">email / nip</dt><dd class="text-ink-soft">Isi salah satu atau keduanya; keduanya dapat dipakai untuk masuk. NIP 8–25 digit, simpan sebagai teks agar angka nol di depan tidak hilang.</dd></div>
                <div><dt class="font-semibold text-ink">password</dt><dd class="text-ink-soft">Opsional, minimal 8 karakter. Kosong = dibuat acak. Kartu akun yang diunduh memuat kata sandi tiap guru untuk dibagikan.</dd></div>
                <div><dt class="font-semibold text-ink">kode_mapel</dt><dd class="text-ink-soft">Kode pada menu Mapel. Beberapa mapel dipisah koma, mis. <code class="rounded bg-surface-2 px-1">MAT,FIS</code>.</dd></div>
                <div><dt class="font-semibold text-ink">tingkat</dt><dd class="text-ink-soft">X, XI, atau XII (boleh lebih dari satu, dipisah koma). Wajib jika kode_mapel diisi.</dd></div>
                <div><dt class="font-semibold text-ink">telepon</dt><dd class="text-ink-soft">Opsional.</dd></div>
            </dl>
            <p class="mt-4 rounded-xl bg-surface-2 p-3 text-xs text-ink-soft">Penugasan mapel ditautkan ke <strong>semester aktif</strong>. Peran validator ditunjuk setelah impor, pada menu Validator.</p>
        </section>
    </div>

    <section class="card mt-6 overflow-hidden">
        <div class="flex items-center justify-between border-b border-line px-6 py-4">
            <h2 class="text-base font-bold text-ink">2. Riwayat impor</h2>
            <span v-if="hasRunning" class="inline-flex items-center gap-2 text-xs font-semibold text-primary-600"><LoaderCircle class="size-3.5 animate-spin" /> Memproses…</span>
        </div>

        <ul v-if="batches.length" class="divide-y divide-line">
            <li v-for="b in batches" :key="b.id" class="px-6 py-4">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-ink">{{ b.file }}</p>
                        <p class="text-xs text-ink-faint">{{ formatDateTime(b.created_at) }} WIB · {{ b.created_by ?? 'Sistem' }}</p>
                    </div>
                    <StatusBadge :status="b.status" />
                    <p v-if="b.finished && !b.error_message" class="text-xs text-ink-soft">
                        <span class="font-bold text-success-700 dark:text-success-500">{{ b.success }} berhasil</span> ·
                        <span class="font-bold" :class="b.failed ? 'text-danger-600' : 'text-ink-soft'">{{ b.failed }} gagal</span> · {{ b.total }} baris
                    </p>
                    <a v-if="b.has_accounts" :href="`/admin/teachers/import/${b.id}/accounts`" class="btn-primary btn-sm" @click="afterDownload">
                        <KeyRound class="size-3.5" /> Unduh kartu akun
                    </a>
                    <button v-if="b.results.length" type="button" class="btn-ghost btn-sm" :aria-expanded="expanded === b.id" @click="expanded = expanded === b.id ? null : b.id">
                        Detail <ChevronDown class="size-3.5 transition" :class="{ 'rotate-180': expanded === b.id }" />
                    </button>
                </div>

                <p v-if="b.error_message" class="mt-3 rounded-xl bg-danger-50 p-3 text-sm font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-500" role="alert">{{ b.error_message }}</p>
                <p v-if="b.has_accounts" class="mt-2 text-xs text-warning-700 dark:text-warning-500">Kartu akun berisi kata sandi awal dan hanya dapat diunduh <strong>satu kali</strong>.</p>

                <div v-if="expanded === b.id" class="table-wrap mt-4 rounded-xl border border-line">
                    <table class="table">
                        <thead><tr><th>Baris</th><th>Nama</th><th>Hasil</th><th>Keterangan</th></tr></thead>
                        <tbody>
                            <tr v-for="r in b.results" :key="r.row">
                                <td class="tabular-nums text-ink-faint">{{ r.row }}</td>
                                <td class="font-semibold">{{ r.name || '—' }}</td>
                                <td><StatusBadge :status="r.status" /></td>
                                <td class="text-ink-soft">{{ r.message }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </li>
        </ul>
        <EmptyState v-else title="Belum ada impor" description="Hasil impor beserta laporan per baris akan tampil di sini." />
    </section>
</template>
