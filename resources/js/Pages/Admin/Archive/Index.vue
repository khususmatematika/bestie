<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Archive, FileText, LoaderCircle, Trash2, Upload } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
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
const fileInput = ref<HTMLInputElement | null>(null);

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
            if (fileInput.value) fileInput.value.value = '';
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

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <StatCard label="Butir arsip impor" :value="stats.imported_items" tone="primary"><Archive class="size-6" /></StatCard>
        <StatCard label="Butir paket Fix/terkunci (otomatis)" :value="stats.fixed_items" tone="success" href="/admin/packages"><FileText class="size-6" /></StatCard>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <section class="card p-6 lg:col-span-2">
            <h2 class="text-base font-bold text-ink">Impor naskah lama</h2>
            <p class="mt-1 text-sm text-ink-soft">Naskah .docx atau .pdf dibaca per nomor (teks saja). Pakai format naskah ujian yang sama seperti unggahan guru.</p>

            <form class="mt-4 space-y-4" @submit.prevent="submit">
                <FormField v-slot="{ id, invalid }" label="Mapel" :error="form.errors.subject_id" required>
                    <select :id="id" v-model="form.subject_id" class="input" :class="{ 'input-error': invalid }">
                        <option value="" disabled>Pilih mapel</option>
                        <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }} ({{ s.code }})</option>
                    </select>
                </FormField>
                <FormField v-slot="{ id, invalid }" label="Tingkat (opsional)" :error="form.errors.grade_level_id">
                    <select :id="id" v-model="form.grade_level_id" class="input" :class="{ 'input-error': invalid }">
                        <option value="">Semua / tidak diketahui</option>
                        <option v-for="g in grades" :key="g.id" :value="g.id">Kelas {{ g.name }}</option>
                    </select>
                </FormField>
                <FormField v-slot="{ id, invalid }" label="Tahun / semester asal" :error="form.errors.year_label" hint="Contoh: Genap 2024/2025" required>
                    <input :id="id" v-model="form.year_label" class="input" maxlength="40" :class="{ 'input-error': invalid }" />
                </FormField>
                <FormField v-slot="{ id, invalid }" label="Naskah (.docx / .pdf)" :error="form.errors.file" required>
                    <input :id="id" ref="fileInput" type="file" accept=".docx,.pdf" class="block w-full min-w-0 text-sm" :class="{ 'input-error': invalid }" @change="form.file = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                </FormField>
                <button type="submit" class="btn-primary" :disabled="!form.file || form.subject_id === '' || !form.year_label.trim() || form.processing">
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    <Upload v-else class="size-4" /> Impor ke arsip
                </button>
            </form>
        </section>

        <section class="card overflow-hidden lg:col-span-3">
            <div class="border-b border-line px-6 py-4"><h2 class="text-base font-bold text-ink">Arsip yang diimpor</h2></div>

            <ul v-if="imports.data.length" class="divide-y divide-line">
                <li v-for="row in imports.data" :key="row.id" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 sm:px-6">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-ink">{{ row.subject }} · {{ row.year_label }}<span v-if="row.grade"> · Kelas {{ row.grade }}</span></p>
                        <p class="truncate text-xs text-ink-faint">{{ row.file }} · {{ row.items }} butir · {{ formatDateTime(row.created_at) }}</p>
                    </div>
                    <button type="button" class="btn-icon hover:!text-danger-600" :aria-label="`Hapus arsip ${row.subject} ${row.year_label}`" @click="remove(row)"><Trash2 class="size-4" /></button>
                </li>
            </ul>
            <EmptyState v-else title="Belum ada arsip impor" description="Paket Fix semester lama tetap dipakai otomatis. Impor naskah lama untuk tahun sebelum BESTIE." />
            <Pagination :meta="imports" />
        </section>
    </div>
</template>
