<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Archive, ChevronDown, FileText, LoaderCircle, Trash2, Upload } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FilePicker from '@/Components/ui/FilePicker.vue';
import FormField from '@/Components/ui/FormField.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { formatDateTime } from '@/Composables/useFormat';
import type { Paginated } from '@/types';

interface ImportRow {
    id: number;
    subject: string;
    grade: string | null;
    year_label: string;
    file: string;
    items: number;
    created_at: string | null;
}

defineProps<{
    imports: Paginated<ImportRow>;
    subjects: { id: number; name: string; code: string }[];
    grades: { id: number; name: string }[];
    stats: { imported_items: number; fixed_items: number };
}>();

const { confirm } = useConfirm();

// Kartu impor bisa dilipat agar daftar arsip memakai seluruh lebar.
const importOpen = ref(true);

const form = useForm<{ subject_id: number | ''; grade_level_id: number | ''; year_label: string; file: File | null }>({
    subject_id: '',
    grade_level_id: '',
    year_label: '',
    file: null,
});

function submit(): void {
    form.post('/admin/archive', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('file');
        },
    });
}

async function remove(row: ImportRow): Promise<void> {
    const ok = await confirm({
        title: 'Hapus arsip ini?',
        message: `${row.subject} · ${row.year_label} (${row.items} butir) tidak lagi dipakai untuk deteksi kemiripan.`,
        confirmLabel: 'Hapus',
        danger: true,
    });

    if (ok) router.delete(`/admin/archive/${row.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Arsip Soal" />
    <PageHeader title="Arsip soal" description="Pembanding kemiripan untuk validator: naskah tahun lalu yang Anda impor, ditambah paket Fix semester lama yang otomatis ikut." />

  <div class="@container">
    <div class="mb-6 grid grid-cols-1 gap-4 @xl:grid-cols-2">
        <StatCard label="Butir arsip impor" :value="stats.imported_items" tone="primary"><Archive class="size-6" /></StatCard>
        <StatCard label="Butir paket Fix/terkunci (otomatis)" :value="stats.fixed_items" tone="success" href="/admin/packages"><FileText class="size-6" /></StatCard>
    </div>

    <div class="grid gap-6" :class="importOpen ? '@4xl:grid-cols-5' : ''">
        <section class="card @container p-6 @4xl:col-span-2" :class="importOpen ? '' : 'py-4'">
            <button type="button" class="flex w-full items-start gap-3 text-left" :aria-expanded="importOpen" aria-controls="archive-import" @click="importOpen = !importOpen">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300"><Upload class="size-5" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-base font-bold text-ink">Impor naskah lama</span>
                    <span v-show="importOpen" class="mt-0.5 block text-sm text-ink-soft">Naskah .docx atau .pdf dibaca per nomor (teks saja), memakai format naskah yang sama seperti unggahan guru.</span>
                </span>
                <ChevronDown class="mt-2 size-5 shrink-0 text-ink-faint transition" :class="{ 'rotate-180': importOpen }" />
            </button>

            <form v-show="importOpen" id="archive-import" class="mt-5 space-y-5" @submit.prevent="submit">
                <div class="space-y-3">
                    <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-ink-faint"><span class="grid size-5 place-items-center rounded-full bg-primary-600 text-[11px] text-white">1</span> Identitas arsip</p>
                    <FormField v-slot="{ id, invalid }" label="Mapel" :error="form.errors.subject_id" required>
                        <select :id="id" v-model="form.subject_id" class="input" :class="{ 'input-error': invalid }">
                            <option value="" disabled>Pilih mapel</option>
                            <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }} ({{ s.code }})</option>
                        </select>
                    </FormField>
                    <div class="grid grid-cols-1 gap-3 @md:grid-cols-2">
                        <FormField v-slot="{ id, invalid }" label="Tingkat (opsional)" :error="form.errors.grade_level_id">
                            <select :id="id" v-model="form.grade_level_id" class="input" :class="{ 'input-error': invalid }">
                                <option value="">Semua / tidak diketahui</option>
                                <option v-for="g in grades" :key="g.id" :value="g.id">Kelas {{ g.name }}</option>
                            </select>
                        </FormField>
                        <FormField v-slot="{ id, invalid }" label="Tahun / semester asal" :error="form.errors.year_label" hint="Contoh: Genap 2024/2025" required>
                            <input :id="id" v-model="form.year_label" class="input" maxlength="40" :class="{ 'input-error': invalid }" />
                        </FormField>
                    </div>
                </div>

                <div class="space-y-3">
                    <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-ink-faint"><span class="grid size-5 place-items-center rounded-full bg-primary-600 text-[11px] text-white">2</span> Berkas naskah</p>
                    <FormField v-slot="{ id, invalid }" label="Naskah (.docx / .pdf)" :error="form.errors.file" required>
                        <FilePicker :id="id" v-model="form.file" accept=".docx,.pdf" label="Pilih naskah arsip" :invalid="invalid" />
                    </FormField>
                </div>

                <button type="submit" class="btn-primary w-full" :disabled="!form.file || form.subject_id === '' || !form.year_label.trim() || form.processing">
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    <Upload v-else class="size-4" /> Impor ke arsip
                </button>
            </form>
        </section>

        <section class="card overflow-hidden" :class="importOpen ? '@4xl:col-span-3' : ''">
            <div class="flex items-center justify-between gap-3 border-b border-line px-6 py-4">
                <h2 class="text-base font-bold text-ink">Arsip yang diimpor</h2>
                <span class="badge bg-surface-2 text-ink-soft">{{ imports.total ?? imports.data.length }} berkas</span>
            </div>

            <ul v-if="imports.data.length" class="divide-y divide-line">
                <li v-for="row in imports.data" :key="row.id" class="flex items-center gap-3 px-4 py-3.5 transition hover:bg-surface-2/60 sm:px-6">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-accent-50 text-accent-700 dark:bg-accent-500/15 dark:text-accent-100"><FileText class="size-5" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-ink">{{ row.subject }}</p>
                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs">
                            <span class="badge bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">{{ row.year_label }}</span>
                            <span v-if="row.grade" class="badge bg-surface-2 text-ink-soft">Kelas {{ row.grade }}</span>
                            <span class="badge bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500">{{ row.items }} butir</span>
                        </div>
                        <p class="mt-1 truncate text-xs text-ink-faint">{{ row.file }} · {{ formatDateTime(row.created_at) }}</p>
                    </div>
                    <button type="button" class="btn-icon hover:!text-danger-600" :aria-label="`Hapus arsip ${row.subject} ${row.year_label}`" @click="remove(row)"><Trash2 class="size-4" /></button>
                </li>
            </ul>
            <EmptyState v-else title="Belum ada arsip impor" description="Paket Fix semester lama tetap dipakai otomatis. Impor naskah lama untuk tahun sebelum BESTIE." />
            <Pagination :meta="imports" />
        </section>
    </div>
  </div>
</template>
