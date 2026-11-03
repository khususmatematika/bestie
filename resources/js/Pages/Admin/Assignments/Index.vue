<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Copy, LoaderCircle, Search, UserCog } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FormField from '@/Components/ui/FormField.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

interface Row {
    key: string;
    subject_id: number;
    grade_level_id: number;
    subject: string;
    code: string;
    grade: string;
    phase: string | null;
    teachers: { assignment_id: number; id: number; name: string }[];
}

const props = defineProps<{
    rows: Row[];
    semesters: { id: number; label: string; archived: boolean }[];
    selected_semester_id: number;
    teachers: { id: number; name: string }[];
}>();

const rolloverOpen = ref(false);
const semesterId = ref(props.selected_semester_id);
const search = ref('');
const filter = ref<'all' | 'empty' | 'filled'>('all');

const rollover = useForm({ from_semester_id: '' as number | '', to_semester_id: props.selected_semester_id });
const isArchived = computed(() => props.semesters.find((s) => s.id === semesterId.value)?.archived ?? false);

const visible = computed(() => {
    const q = search.value.trim().toLowerCase();

    return props.rows.filter((r) => {
        if (filter.value === 'empty' && r.teachers.length) return false;
        if (filter.value === 'filled' && !r.teachers.length) return false;

        return q === '' || r.subject.toLowerCase().includes(q) || r.code.toLowerCase().includes(q) || r.teachers.some((t) => t.name.toLowerCase().includes(q));
    });
});

const emptyCount = computed(() => props.rows.filter((r) => !r.teachers.length).length);
const filledCount = computed(() => props.rows.length - emptyCount.value);

function reload(): void {
    router.get('/admin/assignments', { semester_id: semesterId.value }, { preserveState: true, preserveScroll: true, replace: true });
}
watch(semesterId, reload);

function assign(r: Row, e: Event): void {
    const value = (e.target as HTMLSelectElement).value;

    router.post(
        '/admin/assignments/assign',
        { semester_id: semesterId.value, subject_id: r.subject_id, grade_level_id: r.grade_level_id, teacher_id: value === '' ? null : Number(value) },
        { preserveScroll: true },
    );
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
</script>

<template>
    <Head title="Penugasan Mengajar" />
    <PageHeader title="Penugasan mengajar" description="Seluruh mapel beserta fasenya sudah tampil (urut abjad). Pilih satu guru pada mapel yang dimaksud.">
        <button type="button" class="btn-secondary" :disabled="isArchived" @click="openRollover"><Copy class="size-4" /> Salin dari semester lain</button>
    </PageHeader>

    <section class="card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-line p-4 sm:flex-row sm:items-center">
            <select v-model="semesterId" class="input sm:!w-64" aria-label="Semester">
                <option v-for="s in semesters" :key="s.id" :value="s.id">{{ s.label }}{{ s.archived ? ' (arsip)' : '' }}</option>
            </select>
            <div class="relative flex-1 sm:max-w-xs">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-faint" />
                <input v-model="search" type="search" class="input pl-10" placeholder="Cari mapel atau guru…" aria-label="Cari mapel atau guru" />
            </div>
            <select v-model="filter" class="input sm:!w-52" aria-label="Filter penugasan">
                <option value="all">Semua mapel</option>
                <option value="empty">Belum ada guru ({{ emptyCount }})</option>
                <option value="filled">Sudah ada guru</option>
            </select>
        </div>

        <div class="flex flex-wrap gap-2 border-b border-line px-4 py-3 text-sm">
            <span class="badge bg-surface-2 text-ink-soft">Total mapel-kelas: <b class="ml-1 text-ink">{{ rows.length }}</b></span>
            <span class="badge bg-success-50 text-success-700 dark:bg-success-500/15">Penugasan terisi: <b class="ml-1">{{ filledCount }}</b></span>
            <span class="badge bg-warning-50 text-warning-700 dark:bg-warning-500/15">Belum ada guru: <b class="ml-1">{{ emptyCount }}</b></span>
        </div>

        <div v-if="visible.length" class="table-wrap">
            <table class="table">
                <thead><tr><th>Mapel</th><th>Kelas</th><th>Fase</th><th>Guru</th></tr></thead>
                <tbody>
                    <tr v-for="r in visible" :key="r.key">
                        <td class="font-bold">{{ r.subject }} <span class="font-mono text-[11px] font-normal text-ink-faint">{{ r.code }}</span></td>
                        <td class="whitespace-nowrap">Kelas {{ r.grade }}</td>
                        <td class="whitespace-nowrap text-ink-soft">{{ r.phase ?? '—' }}</td>
                        <td>
                            <select
                                v-if="!isArchived"
                                class="input !w-full min-w-52 !py-1.5 text-sm sm:!w-72"
                                :value="r.teachers[0]?.id ?? ''"
                                :aria-label="`Guru ${r.subject} kelas ${r.grade}`"
                                @change="assign(r, $event)"
                            >
                                <option value="">Pilih guru…</option>
                                <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                            <span v-else class="text-ink-soft">{{ r.teachers.map((t) => t.name).join(', ') || '—' }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else-if="!rows.length" title="Belum ada mapel" description="Tambahkan mapel pada menu Mapel & Tingkat atau impor mapel terlebih dahulu.">
            <template #icon><UserCog class="size-7" /></template>
        </EmptyState>
        <EmptyState v-else title="Tidak ada hasil" description="Ubah kata kunci atau filter pencarian.">
            <template #icon><Search class="size-7" /></template>
        </EmptyState>
    </section>

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
