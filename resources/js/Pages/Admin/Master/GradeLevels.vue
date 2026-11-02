<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { GraduationCap, LoaderCircle, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useConfirm } from '@/Composables/useConfirm';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FormField from '@/Components/ui/FormField.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

interface Grade {
    id: number;
    name: string;
    phase: string | null;
    phase_elective: string | null;
    sort_order: number;
    general_count: number;
    elective_count: number;
}

defineProps<{ grades: Grade[] }>();

const { confirm } = useConfirm();
const open = ref(false);
const editing = ref<Grade | null>(null);
const form = useForm({ name: '', phase: '', phase_elective: '', sort_order: 1 });

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function openEdit(g: Grade): void {
    editing.value = g;
    form.clearErrors();
    form.name = g.name;
    form.phase = g.phase ?? '';
    form.phase_elective = g.phase_elective ?? '';
    form.sort_order = g.sort_order;
    open.value = true;
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (editing.value) form.put(`/admin/grade-levels/${editing.value.id}`, options);
    else form.post('/admin/grade-levels', options);
}

async function remove(g: Grade): Promise<void> {
    const ok = await confirm({
        title: `Hapus tingkat ${g.name}?`,
        message: 'Tingkat yang sudah dipakai pada penugasan tidak dapat dihapus.',
        confirmLabel: 'Hapus',
        danger: true,
    });

    if (ok) router.delete(`/admin/grade-levels/${g.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Tingkat" />
    <PageHeader title="Tingkat kelas" description="Kelas X = Fase E. Kelas XI dan XII = Fase F untuk mapel umum, Fase F+ untuk mapel pilihan. Dipakai pada mapel, penugasan guru, dan paket soal.">
        <button type="button" class="btn-primary" @click="openCreate"><Plus class="size-4" /> Tambah tingkat</button>
    </PageHeader>

    <section class="card overflow-hidden">
        <div v-if="grades.length" class="table-wrap">
            <table class="table">
                <thead><tr><th>Urutan</th><th>Tingkat</th><th>Fase mapel umum</th><th>Fase mapel pilihan</th><th>Jumlah mapel</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>
                    <tr v-for="g in grades" :key="g.id">
                        <td class="tabular-nums text-ink-soft">{{ g.sort_order }}</td>
                        <td class="font-bold">Kelas {{ g.name }}</td>
                        <td><span v-if="g.phase" class="badge bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">{{ g.phase }}</span><span v-else class="text-ink-faint">—</span></td>
                        <td><span v-if="g.phase_elective" class="badge bg-accent-50 text-accent-700 dark:bg-accent-500/15 dark:text-accent-100">{{ g.phase_elective }}</span><span v-else class="text-ink-faint">—</span></td>
                        <td class="text-xs text-ink-soft">{{ g.general_count }} umum · {{ g.elective_count }} pilihan</td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <button type="button" class="btn-icon" :aria-label="`Ubah ${g.name}`" @click="openEdit(g)"><Pencil class="size-4" /></button>
                                <button type="button" class="btn-icon hover:!text-danger-600" :aria-label="`Hapus ${g.name}`" @click="remove(g)"><Trash2 class="size-4" /></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="Belum ada tingkat" description="Tambahkan tingkat X, XI, dan XII.">
            <template #icon><GraduationCap class="size-7" /></template>
        </EmptyState>
    </section>

    <Modal :open="open" :title="editing ? 'Ubah tingkat' : 'Tambah tingkat'" size="sm" @close="open = false">
        <form id="grade-form" class="space-y-4" @submit.prevent="submit">
            <FormField v-slot="{ id, invalid }" label="Nama tingkat" :error="form.errors.name" required>
                <input :id="id" v-model="form.name" class="input uppercase" :class="{ 'input-error': invalid }" maxlength="10" placeholder="X" />
            </FormField>
            <FormField v-slot="{ id, invalid }" label="Fase mapel umum" :error="form.errors.phase" hint="Kelas X: Fase E · XI/XII: Fase F">
                <input :id="id" v-model="form.phase" class="input" :class="{ 'input-error': invalid }" placeholder="Fase F" />
            </FormField>
            <FormField v-slot="{ id, invalid }" label="Fase mapel pilihan" :error="form.errors.phase_elective" hint="Kelas X: Fase E · XI/XII: Fase F+">
                <input :id="id" v-model="form.phase_elective" class="input" :class="{ 'input-error': invalid }" placeholder="Fase F+" />
            </FormField>
            <FormField v-slot="{ id, invalid }" label="Urutan tampil" :error="form.errors.sort_order" required>
                <input :id="id" v-model.number="form.sort_order" type="number" min="0" max="255" class="input" :class="{ 'input-error': invalid }" />
            </FormField>
        </form>
        <template #footer>
            <button type="button" class="btn-secondary" @click="open = false">Batal</button>
            <button type="submit" form="grade-form" class="btn-primary" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" /> Simpan
            </button>
        </template>
    </Modal>
</template>
