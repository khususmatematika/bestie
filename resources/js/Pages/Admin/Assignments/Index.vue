<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Copy, LoaderCircle, Plus, Search, Trash2, UserCog } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useConfirm } from '@/Composables/useConfirm';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FormField from '@/Components/ui/FormField.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import type { Paginated } from '@/types';

interface Assignment {
    id: number;
    teacher: string;
    subject: string;
    code: string;
    grade: string;
}

const props = defineProps<{
    assignments: Paginated<Assignment>;
    semesters: { id: number; label: string; archived: boolean }[];
    selected_semester_id: number;
    teachers: { id: number; name: string }[];
    subjects: { id: number; name: string; code: string; grade_ids: number[] }[];
    grades: { id: number; name: string }[];
    filters: { search: string };
}>();

const { confirm } = useConfirm();
const open = ref(false);
const rolloverOpen = ref(false);
const semesterId = ref(props.selected_semester_id);
const search = ref(props.filters.search);

const form = useForm({
    semester_id: props.selected_semester_id,
    teacher_id: '' as number | '',
    subject_id: '' as number | '',
    grade_level_ids: [] as number[],
});

// Hanya tingkat yang memang diajarkan untuk mapel terpilih yang bisa dicentang.
const availableGrades = computed(() => {
    const subject = props.subjects.find((s) => s.id === form.subject_id);
    return subject ? props.grades.filter((g) => subject.grade_ids.includes(g.id)) : [];
});

watch(
    () => form.subject_id,
    () => (form.grade_level_ids = []),
);
const rollover = useForm({ from_semester_id: '' as number | '', to_semester_id: props.selected_semester_id });

const isArchived = () => props.semesters.find((s) => s.id === semesterId.value)?.archived ?? false;

function reload(): void {
    router.get('/admin/assignments', { semester_id: semesterId.value, search: search.value || undefined }, { preserveState: true, replace: true });
}

let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(reload, 350);
});
watch(semesterId, reload);

function openCreate(): void {
    form.reset('teacher_id', 'subject_id', 'grade_level_ids');
    form.semester_id = semesterId.value;
    form.clearErrors();
    open.value = true;
}

function submit(): void {
    form.post('/admin/assignments', { preserveScroll: true, onSuccess: () => (open.value = false) });
}

function openRollover(): void {
    rollover.to_semester_id = semesterId.value;
    rollover.from_semester_id = '';
    rollover.clearErrors();
    rolloverOpen.value = true;
}

function submitRollover(): void {
    rollover.post('/admin/assignments/rollover', { preserveScroll: true, onSuccess: () => (rolloverOpen.value = false) });
}

async function remove(a: Assignment): Promise<void> {
    const ok = await confirm({
        title: 'Hapus penugasan?',
        message: `${a.teacher} — ${a.subject} kelas ${a.grade} akan dihapus dari semester ini.`,
        confirmLabel: 'Hapus',
        danger: true,
    });

    if (ok) router.delete(`/admin/assignments/${a.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Penugasan Mengajar" />
    <PageHeader title="Penugasan mengajar" description="Hubungkan guru dengan mapel dan tingkat untuk setiap semester.">
        <button type="button" class="btn-secondary" :disabled="isArchived()" @click="openRollover"><Copy class="size-4" /> Salin dari semester lain</button>
        <button type="button" class="btn-primary" :disabled="isArchived() || !semesters.length" @click="openCreate"><Plus class="size-4" /> Tambah penugasan</button>
    </PageHeader>

    <section class="card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-line p-4 sm:flex-row">
            <select v-model="semesterId" class="input sm:!w-64" aria-label="Semester">
                <option v-for="s in semesters" :key="s.id" :value="s.id">{{ s.label }}{{ s.archived ? ' (arsip)' : '' }}</option>
            </select>
            <div class="relative flex-1 sm:max-w-xs">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-faint" />
                <input v-model="search" type="search" class="input pl-10" placeholder="Cari nama guru…" aria-label="Cari guru" />
            </div>
        </div>

        <div v-if="assignments.data.length" class="table-wrap">
            <table class="table">
                <thead><tr><th>Guru</th><th>Mapel</th><th>Tingkat</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>
                    <tr v-for="a in assignments.data" :key="a.id">
                        <td class="font-bold">{{ a.teacher }}</td>
                        <td>{{ a.subject }} <span class="font-mono text-[11px] text-ink-faint">{{ a.code }}</span></td>
                        <td>Kelas {{ a.grade }}</td>
                        <td>
                            <div class="flex justify-end">
                                <button v-if="!isArchived()" type="button" class="btn-icon hover:!text-danger-600" :aria-label="`Hapus penugasan ${a.teacher}`" @click="remove(a)"><Trash2 class="size-4" /></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="Belum ada penugasan" description="Tambahkan manual, salin dari semester sebelumnya, atau isi kolom kode_mapel saat impor guru.">
            <template #icon><UserCog class="size-7" /></template>
        </EmptyState>
        <Pagination :meta="assignments" />
    </section>

    <Modal :open="open" title="Tambah penugasan" @close="open = false">
        <form id="assign-form" class="space-y-4" @submit.prevent="submit">
            <FormField v-slot="{ id, invalid }" label="Guru" :error="form.errors.teacher_id" required>
                <select :id="id" v-model="form.teacher_id" class="input" :class="{ 'input-error': invalid }">
                    <option value="" disabled>Pilih guru</option>
                    <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
            </FormField>
            <FormField v-slot="{ id, invalid }" label="Mapel" :error="form.errors.subject_id" required>
                <select :id="id" v-model="form.subject_id" class="input" :class="{ 'input-error': invalid }">
                    <option value="" disabled>Pilih mapel</option>
                    <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }} ({{ s.code }})</option>
                </select>
            </FormField>
            <fieldset>
                <legend class="label">Tingkat<span class="ml-0.5 text-danger-500">*</span></legend>
                <div class="flex flex-wrap gap-2">
                    <label v-for="g in availableGrades" :key="g.id" class="flex cursor-pointer items-center gap-2 rounded-xl border border-line px-4 py-2 text-sm font-semibold has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 has-[:checked]:text-primary-700 dark:has-[:checked]:bg-primary-500/15 dark:has-[:checked]:text-primary-300">
                        <input v-model="form.grade_level_ids" type="checkbox" :value="g.id" class="size-4 accent-primary-600" /> Kelas {{ g.name }}
                    </label>
                </div>
                <p v-if="!form.subject_id" class="mt-1 text-xs text-ink-faint">Pilih mapel terlebih dahulu.</p>
                <p v-if="form.errors.grade_level_ids" class="error-text" role="alert">{{ form.errors.grade_level_ids }}</p>
            </fieldset>
        </form>
        <template #footer>
            <button type="button" class="btn-secondary" @click="open = false">Batal</button>
            <button type="submit" form="assign-form" class="btn-primary" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" /> Simpan
            </button>
        </template>
    </Modal>

    <Modal :open="rolloverOpen" title="Salin penugasan antar semester" description="Penugasan yang sudah ada di semester tujuan tidak diduplikasi." size="sm" @close="rolloverOpen = false">
        <form id="rollover-form" class="space-y-4" @submit.prevent="submitRollover">
            <FormField v-slot="{ id, invalid }" label="Salin dari" :error="rollover.errors.from_semester_id" required>
                <select :id="id" v-model="rollover.from_semester_id" class="input" :class="{ 'input-error': invalid }">
                    <option value="" disabled>Pilih semester sumber</option>
                    <option v-for="s in semesters.filter((x) => x.id !== rollover.to_semester_id)" :key="s.id" :value="s.id">{{ s.label }}</option>
                </select>
            </FormField>
            <FormField v-slot="{ id, invalid }" label="Ke semester" :error="rollover.errors.to_semester_id" required>
                <select :id="id" v-model="rollover.to_semester_id" class="input" :class="{ 'input-error': invalid }">
                    <option v-for="s in semesters.filter((x) => !x.archived)" :key="s.id" :value="s.id">{{ s.label }}</option>
                </select>
            </FormField>
        </form>
        <template #footer>
            <button type="button" class="btn-secondary" @click="rolloverOpen = false">Batal</button>
            <button type="submit" form="rollover-form" class="btn-primary" :disabled="rollover.processing">
                <LoaderCircle v-if="rollover.processing" class="size-4 animate-spin" /> Salin
            </button>
        </template>
    </Modal>
</template>
