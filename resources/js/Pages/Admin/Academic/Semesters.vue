<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Layers, LoaderCircle, Pencil, Play, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useConfirm } from '@/Composables/useConfirm';
import { formatDate } from '@/Composables/useFormat';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FormField from '@/Components/ui/FormField.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatusBadge from '@/Components/ui/StatusBadge.vue';

interface Semester {
    id: number;
    academic_year_id: number;
    label: string;
    term: string;
    term_label: string;
    starts_on: string;
    ends_on: string;
    status: string;
    status_label: string;
    periods_count: number;
    assignments_count: number;
}

defineProps<{
    semesters: Semester[];
    years: { id: number; name: string }[];
    terms: { value: string; label: string }[];
}>();

const { confirm } = useConfirm();
const open = ref(false);
const editing = ref<Semester | null>(null);
const form = useForm({ academic_year_id: '' as number | '', term: 'ganjil', starts_on: '', ends_on: '' });

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function openEdit(s: Semester): void {
    editing.value = s;
    form.clearErrors();
    form.academic_year_id = s.academic_year_id;
    form.term = s.term;
    form.starts_on = s.starts_on;
    form.ends_on = s.ends_on;
    open.value = true;
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (editing.value) form.put(`/admin/semesters/${editing.value.id}`, options);
    else form.post('/admin/semesters', options);
}

async function activate(s: Semester): Promise<void> {
    const ok = await confirm({
        title: `Aktifkan ${s.label}?`,
        message: 'Hanya satu semester yang boleh aktif. Semester aktif saat ini akan otomatis menjadi arsip (hanya-baca).',
        confirmLabel: 'Aktifkan',
    });

    if (ok) router.post(`/admin/semesters/${s.id}/activate`, {}, { preserveScroll: true });
}

async function remove(s: Semester): Promise<void> {
    const ok = await confirm({
        title: `Hapus ${s.label}?`,
        message: 'Semester yang sudah memiliki periode atau penugasan tidak dapat dihapus.',
        confirmLabel: 'Hapus',
        danger: true,
    });

    if (ok) router.delete(`/admin/semesters/${s.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Semester" />
    <PageHeader title="Semester" description="Setiap tahun ajaran memiliki semester Ganjil dan Genap. Hanya satu semester yang aktif pada satu waktu.">
        <button type="button" class="btn-primary" :disabled="!years.length" @click="openCreate"><Plus class="size-4" /> Tambah semester</button>
    </PageHeader>

    <section class="card overflow-hidden">
        <div v-if="semesters.length" class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Semester</th>
                        <th>Rentang</th>
                        <th>Periode</th>
                        <th>Penugasan</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in semesters" :key="s.id">
                        <td class="font-bold">{{ s.label }}</td>
                        <td class="whitespace-nowrap text-ink-soft">{{ formatDate(s.starts_on) }} – {{ formatDate(s.ends_on) }}</td>
                        <td>{{ s.periods_count }}</td>
                        <td>{{ s.assignments_count }}</td>
                        <td><StatusBadge :status="s.status" /></td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <button v-if="s.status !== 'aktif'" type="button" class="btn-secondary btn-sm" @click="activate(s)"><Play class="size-3.5" /> Aktifkan</button>
                                <button v-if="s.status !== 'arsip'" type="button" class="btn-icon" :aria-label="`Ubah ${s.label}`" @click="openEdit(s)"><Pencil class="size-4" /></button>
                                <button type="button" class="btn-icon hover:!text-danger-600" :aria-label="`Hapus ${s.label}`" @click="remove(s)"><Trash2 class="size-4" /></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="Belum ada semester" :description="years.length ? 'Tambahkan semester Ganjil atau Genap.' : 'Buat tahun ajaran terlebih dahulu.'">
            <template #icon><Layers class="size-7" /></template>
        </EmptyState>
    </section>

    <Modal :open="open" :title="editing ? 'Ubah semester' : 'Tambah semester'" @close="open = false">
        <form id="semester-form" class="space-y-4" @submit.prevent="submit">
            <FormField v-slot="{ id, invalid }" label="Tahun ajaran" :error="form.errors.academic_year_id" required>
                <select :id="id" v-model="form.academic_year_id" class="input" :class="{ 'input-error': invalid }">
                    <option value="" disabled>Pilih tahun ajaran</option>
                    <option v-for="y in years" :key="y.id" :value="y.id">{{ y.name }}</option>
                </select>
            </FormField>
            <FormField v-slot="{ id, invalid }" label="Semester" :error="form.errors.term" required>
                <select :id="id" v-model="form.term" class="input" :class="{ 'input-error': invalid }">
                    <option v-for="t in terms" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </FormField>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField v-slot="{ id, invalid }" label="Tanggal mulai" :error="form.errors.starts_on" required>
                    <input :id="id" v-model="form.starts_on" type="date" class="input" :class="{ 'input-error': invalid }" />
                </FormField>
                <FormField v-slot="{ id, invalid }" label="Tanggal selesai" :error="form.errors.ends_on" required>
                    <input :id="id" v-model="form.ends_on" type="date" class="input" :class="{ 'input-error': invalid }" />
                </FormField>
            </div>
        </form>
        <template #footer>
            <button type="button" class="btn-secondary" @click="open = false">Batal</button>
            <button type="submit" form="semester-form" class="btn-primary" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" /> Simpan
            </button>
        </template>
    </Modal>
</template>
