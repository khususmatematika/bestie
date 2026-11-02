<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Download, FileSpreadsheet, LoaderCircle, Upload } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatusBadge from '@/Components/ui/StatusBadge.vue';

interface RowResult {
    row: number;
    code: string;
    status: 'created' | 'updated' | 'skipped' | 'failed';
    message: string;
}

interface ImportResult {
    total: number;
    created: number;
    updated: number;
    skipped: number;
    failed: number;
    rows: RowResult[];
}

defineProps<{
    result: ImportResult | null;
    grades: { name: string; phase: string | null; phase_elective: string | null }[];
}>();

const form = useForm<{ file: File | null; update_existing: boolean }>({ file: null, update_existing: true });
const fileInput = ref<HTMLInputElement | null>(null);
const dragging = ref(false);

function pick(files: FileList | null): void {
    form.file = files?.[0] ?? null;
}

function onDrop(event: DragEvent): void {
    dragging.value = false;
    pick(event.dataTransfer?.files ?? null);
}

function submit(): void {
    form.post('/admin/subjects/import', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}
</script>

<template>
    <Head title="Impor Mapel" />

    <Link href="/admin/subjects" class="mb-3 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-soft hover:text-primary-600">
        <ArrowLeft class="size-4" /> Kembali ke mapel
    </Link>

    <PageHeader title="Impor mapel" description="Unggah berkas Excel/CSV berisi kode, nama, kategori, dan tingkat kelas. Hasilnya langsung diproses dan dilaporkan per baris." />

    <div class="grid gap-6 lg:grid-cols-5">
        <section class="card p-6 lg:col-span-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-bold text-ink">1. Unggah berkas</h2>
                <a href="/admin/subjects/import/template" class="btn-secondary btn-sm"><Download class="size-3.5" /> Unduh template</a>
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
                    <span class="mt-1 text-xs text-ink-faint">.xlsx atau .csv · maks. 500 baris</span>
                    <input ref="fileInput" type="file" accept=".xlsx,.csv" class="sr-only" @change="pick(($event.target as HTMLInputElement).files)" />
                </label>
                <p v-if="form.errors.file" class="error-text" role="alert">{{ form.errors.file }}</p>

                <label class="flex cursor-pointer items-start gap-2.5 text-sm text-ink">
                    <input v-model="form.update_existing" type="checkbox" class="mt-0.5 size-4 accent-primary-600" />
                    <span>
                        Perbarui mapel yang kodenya sudah ada
                        <span class="block text-xs text-ink-faint">Jika dimatikan, baris dengan kode yang sudah ada dilewati. Saat diperbarui, daftar tingkat diganti sesuai berkas.</span>
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
                <div><dt class="font-semibold text-ink">kode_mapel</dt><dd class="text-ink-soft">Wajib, unik, maks. 20 karakter (huruf, angka, “-”, “_”). Contoh: <code class="rounded bg-surface-2 px-1">MAT</code>.</dd></div>
                <div><dt class="font-semibold text-ink">nama_mapel</dt><dd class="text-ink-soft">Wajib.</dd></div>
                <div><dt class="font-semibold text-ink">kategori</dt><dd class="text-ink-soft"><code class="rounded bg-surface-2 px-1">umum</code> atau <code class="rounded bg-surface-2 px-1">pilihan</code>. Kosong dianggap umum.</dd></div>
                <div><dt class="font-semibold text-ink">tingkat</dt><dd class="text-ink-soft">Wajib. X, XI, dan/atau XII dipisah koma, mis. <code class="rounded bg-surface-2 px-1">X,XI,XII</code>.</dd></div>
            </dl>
            <div class="mt-4 rounded-xl bg-surface-2 p-3 text-xs text-ink-soft">
                <p class="mb-1.5 font-bold text-ink">Fase mengikuti tingkat &amp; kategori</p>
                <ul class="space-y-1">
                    <li v-for="g in grades" :key="g.name">
                        Kelas {{ g.name }}: umum = <strong>{{ g.phase ?? '—' }}</strong>, pilihan = <strong>{{ g.phase_elective ?? g.phase ?? '—' }}</strong>
                    </li>
                </ul>
            </div>
        </section>
    </div>

    <section v-if="result" class="card mt-6 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4">
            <h2 class="text-base font-bold text-ink">2. Hasil impor</h2>
            <p class="text-xs text-ink-soft">
                <span class="font-bold text-success-700 dark:text-success-500">{{ result.created }} dibuat</span> ·
                <span class="font-bold text-primary-700 dark:text-primary-300">{{ result.updated }} diperbarui</span> ·
                <span class="font-bold text-warning-700 dark:text-warning-500">{{ result.skipped }} dilewati</span> ·
                <span class="font-bold" :class="result.failed ? 'text-danger-600' : 'text-ink-soft'">{{ result.failed }} gagal</span>
                · {{ result.total }} baris
            </p>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Baris</th><th>Kode</th><th>Hasil</th><th>Keterangan</th></tr></thead>
                <tbody>
                    <tr v-for="r in result.rows" :key="r.row">
                        <td class="tabular-nums text-ink-faint">{{ r.row }}</td>
                        <td class="font-mono text-xs font-semibold">{{ r.code || '—' }}</td>
                        <td><StatusBadge :status="r.status" /></td>
                        <td class="text-ink-soft">{{ r.message }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
