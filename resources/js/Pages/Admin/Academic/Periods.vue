<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarClock, Lock, LockOpen, LoaderCircle, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useConfirm } from '@/Composables/useConfirm';
import { formatDate } from '@/Composables/useFormat';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FormField from '@/Components/ui/FormField.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatusBadge from '@/Components/ui/StatusBadge.vue';

interface Period {
    id: number;
    semester_id: number;
    name: string;
    label: string;
    type: string;
    type_label: string;
    upload_deadline: string | null;
    validation_deadline: string | null;
    revision_deadline: string | null;
    status: string;
    status_label: string;
}

defineProps<{
    periods: Period[];
    semesters: { id: number; label: string }[];
    types: { value: string; label: string }[];
}>();

const { confirm } = useConfirm();
const open = ref(false);
const editing = ref<Period | null>(null);
const form = useForm({
    semester_id: '' as number | '',
    name: '',
    type: 'sts',
    upload_deadline: '',
    validation_deadline: '',
    revision_deadline: '',
});

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function openEdit(p: Period): void {
    editing.value = p;
    form.clearErrors();
    form.semester_id = p.semester_id;
    form.name = p.name;
    form.type = p.type;
    form.upload_deadline = p.upload_deadline ?? '';
    form.validation_deadline = p.validation_deadline ?? '';
    form.revision_deadline = p.revision_deadline ?? '';
    open.value = true;
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (editing.value) form.put(`/admin/periods/${editing.value.id}`, options);
    else form.post('/admin/periods', options);
}

async function toggle(p: Period): Promise<void> {
    const closing = p.status === 'buka';
    const ok = await confirm({
        title: closing ? `Tutup periode ${p.label}?` : `Buka kembali ${p.label}?`,
        message: closing
            ? 'Periode yang ditutup bersifat hanya-baca: guru dan validator tidak dapat mengubah paket soal di dalamnya.'
            : 'Guru dan validator dapat kembali mengubah paket soal pada periode ini.',
        confirmLabel: closing ? 'Tutup periode' : 'Buka kembali',
        danger: closing,
    });

    if (ok) router.post(`/admin/periods/${p.id}/toggle`, {}, { preserveScroll: true });
}

async function remove(p: Period): Promise<void> {
    const ok = await confirm({
        title: `Hapus periode ${p.label}?`,
        message: 'Periode yang sudah memiliki paket soal tidak dapat dihapus.',
        confirmLabel: 'Hapus',
        danger: true,
    });

    if (ok) router.delete(`/admin/periods/${p.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Periode Asesmen" />
    <PageHeader title="Periode asesmen" description="Sumatif Tengah Semester (STS) dan Sumatif Akhir Semester (SAS) beserta tenggat unggah, validasi, dan revisi.">
        <button type="button" class="btn-primary" :disabled="!semesters.length" @click="openCreate"><Plus class="size-4" /> Tambah periode</button>
    </PageHeader>

    <section class="card overflow-hidden">
        <div v-if="periods.length" class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Periode</th>
                        <th>Tenggat unggah</th>
                        <th>Tenggat validasi</th>
                        <th>Tenggat revisi</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in periods" :key="p.id">
                        <td>
                            <p class="font-bold">{{ p.name }}</p>
                            <p class="text-xs text-ink-faint">{{ p.label }}</p>
                        </td>
                        <td class="whitespace-nowrap">{{ formatDate(p.upload_deadline) }}</td>
                        <td class="whitespace-nowrap">{{ formatDate(p.validation_deadline) }}</td>
                        <td class="whitespace-nowrap">{{ formatDate(p.revision_deadline) }}</td>
                        <td><StatusBadge :status="p.status" /></td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <button type="button" class="btn-secondary btn-sm" @click="toggle(p)">
                                    <component :is="p.status === 'buka' ? Lock : LockOpen" class="size-3.5" />
                                    {{ p.status === 'buka' ? 'Tutup' : 'Buka' }}
                                </button>
                                <button v-if="p.status === 'buka'" type="button" class="btn-icon" :aria-label="`Ubah ${p.name}`" @click="openEdit(p)"><Pencil class="size-4" /></button>
                                <button type="button" class="btn-icon hover:!text-danger-600" :aria-label="`Hapus ${p.name}`" @click="remove(p)"><Trash2 class="size-4" /></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="Belum ada periode asesmen" :description="semesters.length ? 'Tambahkan periode STS atau SAS untuk semester terkait.' : 'Buat semester terlebih dahulu.'">
            <template #icon><CalendarClock class="size-7" /></template>
        </EmptyState>
    </section>

    <Modal :open="open" :title="editing ? 'Ubah periode asesmen' : 'Tambah periode asesmen'" size="lg" @close="open = false">
        <form id="period-form" class="space-y-4" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField v-slot="{ id, invalid }" label="Semester" :error="form.errors.semester_id" required>
                    <select :id="id" v-model="form.semester_id" class="input" :class="{ 'input-error': invalid }">
                        <option value="" disabled>Pilih semester</option>
                        <option v-for="s in semesters" :key="s.id" :value="s.id">{{ s.label }}</option>
                    </select>
                </FormField>
                <FormField v-slot="{ id, invalid }" label="Jenis asesmen" :error="form.errors.type" required>
                    <select :id="id" v-model="form.type" class="input" :class="{ 'input-error': invalid }">
                        <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                </FormField>
            </div>
            <FormField v-slot="{ id, invalid }" label="Nama periode" :error="form.errors.name" required>
                <input :id="id" v-model="form.name" class="input" :class="{ 'input-error': invalid }" placeholder="Sumatif Tengah Semester Ganjil" />
            </FormField>
            <div class="grid gap-4 sm:grid-cols-3">
                <FormField v-slot="{ id, invalid }" label="Tenggat unggah" :error="form.errors.upload_deadline">
                    <input :id="id" v-model="form.upload_deadline" type="date" class="input" :class="{ 'input-error': invalid }" />
                </FormField>
                <FormField v-slot="{ id, invalid }" label="Tenggat validasi" :error="form.errors.validation_deadline">
                    <input :id="id" v-model="form.validation_deadline" type="date" class="input" :class="{ 'input-error': invalid }" />
                </FormField>
                <FormField v-slot="{ id, invalid }" label="Tenggat revisi" :error="form.errors.revision_deadline">
                    <input :id="id" v-model="form.revision_deadline" type="date" class="input" :class="{ 'input-error': invalid }" />
                </FormField>
            </div>
        </form>
        <template #footer>
            <button type="button" class="btn-secondary" @click="open = false">Batal</button>
            <button type="submit" form="period-form" class="btn-primary" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" /> Simpan
            </button>
        </template>
    </Modal>
</template>
