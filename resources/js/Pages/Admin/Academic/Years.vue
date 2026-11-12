<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, LoaderCircle, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useConfirm } from '@/Composables/useConfirm';
import { formatDate } from '@/Composables/useFormat';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FormField from '@/Components/ui/FormField.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatusBadge from '@/Components/ui/StatusBadge.vue';

interface Year {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string;
    is_active: boolean;
    semesters_count: number;
}

defineProps<{ years: Year[] }>();

const { confirm } = useConfirm();
const open = ref(false);
const editing = ref<Year | null>(null);
const form = useForm({ name: '', starts_on: '', ends_on: '' });

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function openEdit(year: Year): void {
    editing.value = year;
    form.clearErrors();
    form.name = year.name;
    form.starts_on = year.starts_on;
    form.ends_on = year.ends_on;
    open.value = true;
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (editing.value) form.put(`/admin/academic-years/${editing.value.id}`, options);
    else form.post('/admin/academic-years', options);
}

async function remove(year: Year): Promise<void> {
    const ok = await confirm({
        title: `Hapus tahun ajaran ${year.name}?`,
        message: 'Tindakan ini tidak dapat dibatalkan. Tahun ajaran yang sudah memiliki semester tidak dapat dihapus.',
        confirmLabel: 'Hapus',
        danger: true,
    });

    if (ok) router.delete(`/admin/academic-years/${year.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Tahun Ajaran" />
    <PageHeader title="Tahun ajaran" description="Contoh format: 2026/2027. Semester dibuat pada menu Semester.">
        <button type="button" class="btn-primary" @click="openCreate"><Plus class="size-4" /> Tambah tahun ajaran</button>
    </PageHeader>

    <section class="card overflow-hidden">
        <div v-if="years.length" class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tahun ajaran</th>
                        <th>Mulai</th>
                        <th>Selesai</th>
                        <th>Semester</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="y in years" :key="y.id">
                        <td class="font-bold">{{ y.name }}</td>
                        <td>{{ formatDate(y.starts_on) }}</td>
                        <td>{{ formatDate(y.ends_on) }}</td>
                        <td>{{ y.semesters_count }}</td>
                        <td><StatusBadge :status="y.is_active ? 'aktif' : 'arsip'" /></td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <button type="button" class="btn-icon" :aria-label="`Ubah ${y.name}`" @click="openEdit(y)"><Pencil class="size-4" /></button>
                                <button type="button" class="btn-icon hover:!text-danger-600" :aria-label="`Hapus ${y.name}`" @click="remove(y)"><Trash2 class="size-4" /></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="Belum ada tahun ajaran" description="Mulai dengan menambahkan tahun ajaran, lalu buat semester Ganjil dan Genap.">
            <template #icon><CalendarDays class="size-7" /></template>
            <button type="button" class="btn-primary" @click="openCreate"><Plus class="size-4" /> Tambah tahun ajaran</button>
        </EmptyState>
    </section>

    <Modal :open="open" :title="editing ? 'Ubah tahun ajaran' : 'Tambah tahun ajaran'" @close="open = false">
        <form id="year-form" class="space-y-4" @submit.prevent="submit">
            <FormField v-slot="{ id, invalid }" label="Tahun ajaran" :error="form.errors.name" hint="Format: 2026/2027" required>
                <input :id="id" v-model="form.name" class="input" :class="{ 'input-error': invalid }" placeholder="2026/2027" maxlength="9" />
            </FormField>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
            <button type="submit" form="year-form" class="btn-primary" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" /> Simpan
            </button>
        </template>
    </Modal>
</template>
