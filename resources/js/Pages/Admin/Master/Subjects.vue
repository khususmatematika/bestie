<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { BookOpen, FileSpreadsheet, LoaderCircle, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { currentPerPage } from '@/Composables/perPage';
import { useConfirm } from '@/Composables/useConfirm';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FormField from '@/Components/ui/FormField.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import StatusBadge from '@/Components/ui/StatusBadge.vue';
import type { Paginated } from '@/types';

interface Subject {
    id: number;
    code: string;
    name: string;
    category: 'umum' | 'pilihan';
    category_label: string;
    grade_level_ids: number[];
    phases: { grade: string; phase: string | null }[];
    is_active: boolean;
}

interface Grade {
    id: number;
    name: string;
    phase: string | null;
    phase_elective: string | null;
}

const props = defineProps<{
    subjects: Paginated<Subject>;
    grades: Grade[];
    categories: { value: string; label: string }[];
    filters: { search: string };
}>();

const { confirm } = useConfirm();
const open = ref(false);
const editing = ref<Subject | null>(null);
const search = ref(props.filters.search);
const form = useForm({ code: '', name: '', category: 'umum', grade_level_ids: [] as number[], is_active: true });

// Fase yang tampil di samping tiap tingkat mengikuti kategori mapel yang dipilih.
const gradeOptions = computed(() =>
    props.grades.map((g) => ({
        id: g.id,
        name: g.name,
        phase: form.category === 'pilihan' ? (g.phase_elective ?? g.phase) : g.phase,
    })),
);

let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get('/admin/subjects', { search: value || undefined, per_page: currentPerPage() }, { preserveState: true, replace: true }), 350);
});

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.grade_level_ids = props.grades.map((g) => g.id);
    open.value = true;
}

function openEdit(s: Subject): void {
    editing.value = s;
    form.clearErrors();
    form.code = s.code;
    form.name = s.name;
    form.category = s.category;
    form.grade_level_ids = [...s.grade_level_ids];
    form.is_active = s.is_active;
    open.value = true;
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (editing.value) form.put(`/admin/subjects/${editing.value.id}`, options);
    else form.post('/admin/subjects', options);
}

async function remove(s: Subject): Promise<void> {
    const ok = await confirm({
        title: `Hapus mapel ${s.name}?`,
        message: 'Mapel yang sudah dipakai pada penugasan tidak dapat dihapus — nonaktifkan saja.',
        confirmLabel: 'Hapus',
        danger: true,
    });

    if (ok) router.delete(`/admin/subjects/${s.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Mapel" />
    <PageHeader title="Mata pelajaran" description="Tentukan kategori (umum/pilihan) dan tingkat kelas tiap mapel. Kode mapel dipakai pada impor akun guru (kolom kode_mapel).">
        <Link href="/admin/subjects/import" class="btn-secondary"><FileSpreadsheet class="size-4" /> Impor Excel</Link>
        <button type="button" class="btn-primary" @click="openCreate"><Plus class="size-4" /> Tambah mapel</button>
    </PageHeader>

    <section class="card overflow-hidden">
        <div class="border-b border-line p-4">
            <div class="relative max-w-sm">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-faint" />
                <input v-model="search" type="search" class="input pl-10" placeholder="Cari kode atau nama mapel…" aria-label="Cari mapel" />
            </div>
        </div>

        <div v-if="subjects.data.length" class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Kode</th><th>Nama mapel</th><th>Kategori</th><th>Tingkat &amp; fase</th><th>Status</th><th class="text-right">Aksi</th></tr>
                </thead>
                <tbody>
                    <tr v-for="s in subjects.data" :key="s.id">
                        <td class="font-mono text-xs font-semibold">{{ s.code }}</td>
                        <td class="font-semibold">{{ s.name }}</td>
                        <td>
                            <span
                                class="badge"
                                :class="s.category === 'pilihan' ? 'bg-accent-50 text-accent-700 dark:bg-accent-500/15 dark:text-accent-100' : 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300'"
                            >{{ s.category_label }}</span>
                        </td>
                        <td>
                            <div v-if="s.phases.length" class="flex flex-wrap gap-1">
                                <span v-for="p in s.phases" :key="p.grade" class="badge bg-slate-100 text-slate-700 dark:bg-slate-500/15 dark:text-slate-300">
                                    {{ p.grade }}<template v-if="p.phase"> · {{ p.phase }}</template>
                                </span>
                            </div>
                            <span v-else class="text-ink-faint">—</span>
                        </td>
                        <td><StatusBadge :status="s.is_active ? 'aktif' : 'nonaktif'" /></td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <button type="button" class="btn-icon" :aria-label="`Ubah ${s.name}`" @click="openEdit(s)"><Pencil class="size-4" /></button>
                                <button type="button" class="btn-icon hover:!text-danger-600" :aria-label="`Hapus ${s.name}`" @click="remove(s)"><Trash2 class="size-4" /></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="Mapel tidak ditemukan" :description="filters.search ? 'Coba kata kunci lain.' : 'Tambahkan mapel pertama.'">
            <template #icon><BookOpen class="size-7" /></template>
        </EmptyState>
        <Pagination :meta="subjects" per-page-control />
    </section>

    <Modal :open="open" :title="editing ? 'Ubah mapel' : 'Tambah mapel'" @close="open = false">
        <form id="subject-form" class="space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <FormField v-slot="{ id, invalid }" label="Kode" :error="form.errors.code" required>
                    <input :id="id" v-model="form.code" class="input font-mono uppercase" :class="{ 'input-error': invalid }" maxlength="20" placeholder="MAT" />
                </FormField>
                <div class="sm:col-span-2">
                    <FormField v-slot="{ id, invalid }" label="Nama mapel" :error="form.errors.name" required>
                        <input :id="id" v-model="form.name" class="input" :class="{ 'input-error': invalid }" placeholder="Matematika" />
                    </FormField>
                </div>
            </div>
            <fieldset>
                <legend class="label">Kategori<span class="ml-0.5 text-danger-500">*</span></legend>
                <div class="flex flex-wrap gap-2">
                    <label
                        v-for="c in categories"
                        :key="c.value"
                        class="flex cursor-pointer items-center gap-2 rounded-xl border border-line px-4 py-2 text-sm font-semibold has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 has-[:checked]:text-primary-700 dark:has-[:checked]:bg-primary-500/15 dark:has-[:checked]:text-primary-300"
                    >
                        <input v-model="form.category" type="radio" :value="c.value" class="size-4 accent-primary-600" /> {{ c.label }}
                    </label>
                </div>
                <p v-if="form.errors.category" class="error-text" role="alert">{{ form.errors.category }}</p>
            </fieldset>
            <fieldset>
                <legend class="label">Tingkat kelas<span class="ml-0.5 text-danger-500">*</span></legend>
                <div class="flex flex-wrap gap-2">
                    <label
                        v-for="g in gradeOptions"
                        :key="g.id"
                        class="flex cursor-pointer items-center gap-2 rounded-xl border border-line px-4 py-2 text-sm font-semibold has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 has-[:checked]:text-primary-700 dark:has-[:checked]:bg-primary-500/15 dark:has-[:checked]:text-primary-300"
                    >
                        <input v-model="form.grade_level_ids" type="checkbox" :value="g.id" class="size-4 accent-primary-600" />
                        <span>Kelas {{ g.name }}<span v-if="g.phase" class="block text-xs font-medium text-ink-faint">{{ g.phase }}</span></span>
                    </label>
                </div>
                <p v-if="form.errors.grade_level_ids" class="error-text" role="alert">{{ form.errors.grade_level_ids }}</p>
            </fieldset>
            <label v-if="editing" class="flex cursor-pointer items-center gap-2.5 text-sm text-ink">
                <input v-model="form.is_active" type="checkbox" class="size-4 accent-primary-600" /> Mapel aktif
            </label>
        </form>
        <template #footer>
            <button type="button" class="btn-secondary" @click="open = false">Batal</button>
            <button type="submit" form="subject-form" class="btn-primary" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" /> Simpan
            </button>
        </template>
    </Modal>
</template>
