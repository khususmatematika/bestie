<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, KeyRound, LoaderCircle, Pencil, Plus, Search, Trash2, Upload, UserCheck, UserX, Users } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useConfirm } from '@/Composables/useConfirm';
import { formatDateTime } from '@/Composables/useFormat';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FormField from '@/Components/ui/FormField.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import StatusBadge from '@/Components/ui/StatusBadge.vue';
import type { Paginated } from '@/types';

interface Teacher {
    id: number;
    name: string;
    email: string | null;
    nip: string | null;
    phone: string | null;
    assignments: { subject_id: number; code: string; grade_level_ids: number[] }[];
    is_active: boolean;
    is_validator: boolean;
    must_change_password: boolean;
    last_login_at: string | null;
}

interface SubjectOption {
    id: number;
    name: string;
    code: string;
    grade_ids: number[];
}

const props = defineProps<{
    teachers: Paginated<Teacher>;
    filters: { search: string; role: string };
    semester: { id: number; label: string; archived: boolean } | null;
    subjects: SubjectOption[];
    grades: { id: number; name: string }[];
}>();

const { confirm } = useConfirm();
const open = ref(false);
const editing = ref<Teacher | null>(null);
const search = ref(props.filters.search);
const role = ref(props.filters.role);
const form = useForm<{
    name: string;
    email: string;
    nip: string;
    phone: string;
    password: string;
    must_change_password: boolean;
}>({ name: '', email: '', nip: '', phone: '', password: '', must_change_password: true });
const showPassword = ref(false);

function reload(): void {
    router.get('/admin/teachers', { search: search.value || undefined, role: role.value || undefined }, { preserveState: true, replace: true });
}

let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(reload, 350);
});
watch(role, reload);

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showPassword.value = false;
    open.value = true;
}

function openEdit(t: Teacher): void {
    editing.value = t;
    form.clearErrors();
    form.name = t.name;
    form.email = t.email ?? '';
    form.nip = t.nip ?? '';
    form.phone = t.phone ?? '';
    form.password = '';
    open.value = true;
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (editing.value) form.put(`/admin/teachers/${editing.value.id}`, options);
    else form.post('/admin/teachers', options);
}

async function resetPassword(t: Teacher): Promise<void> {
    const ok = await confirm({
        title: `Atur ulang kata sandi ${t.name}?`,
        message: 'Kata sandi lama tidak berlaku lagi dan sesi login guru akan diakhiri. Kata sandi baru ditampilkan satu kali.',
        confirmLabel: 'Atur ulang',
    });

    if (ok) router.post(`/admin/teachers/${t.id}/reset-password`, {}, { preserveScroll: true });
}

async function toggleActive(t: Teacher): Promise<void> {
    const ok = await confirm({
        title: t.is_active ? `Nonaktifkan ${t.name}?` : `Aktifkan ${t.name}?`,
        message: t.is_active ? 'Akun nonaktif tidak dapat masuk ke aplikasi.' : 'Akun dapat kembali masuk ke aplikasi.',
        confirmLabel: t.is_active ? 'Nonaktifkan' : 'Aktifkan',
        danger: t.is_active,
    });

    if (ok) router.post(`/admin/teachers/${t.id}/toggle-active`, {}, { preserveScroll: true });
}

async function remove(t: Teacher): Promise<void> {
    const ok = await confirm({
        title: `Hapus akun ${t.name}?`,
        message: 'Akun dihapus dari daftar dan tidak dapat masuk lagi. Riwayat soal (tahap berikutnya) tetap tersimpan.',
        confirmLabel: 'Hapus akun',
        danger: true,
    });

    if (ok) router.delete(`/admin/teachers/${t.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Akun Guru" />
    <PageHeader title="Akun guru" description="Langkah 1: input akun guru satu per satu atau impor dari Excel.">
        <Link href="/admin/teachers/import" class="btn-secondary"><Upload class="size-4" /> Impor Excel</Link>
        <button type="button" class="btn-primary" @click="openCreate"><Plus class="size-4" /> Tambah guru</button>
    </PageHeader>

    <section class="card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-line p-4 sm:flex-row">
            <div class="relative flex-1 sm:max-w-sm">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-faint" />
                <input v-model="search" type="search" class="input pl-10" placeholder="Cari nama, email, atau NIP…" aria-label="Cari guru" />
            </div>
            <select v-model="role" class="input sm:!w-48" aria-label="Saring guru">
                <option value="">Semua guru</option>
                <option value="validator">Validator saja</option>
                <option value="inactive">Nonaktif</option>
            </select>
        </div>

        <div v-if="teachers.data.length" class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Guru</th><th>NIP</th><th>Peran</th><th>Login terakhir</th><th class="text-right">Aksi</th></tr>
                </thead>
                <tbody>
                    <tr v-for="t in teachers.data" :key="t.id">
                        <td>
                            <p class="font-bold">{{ t.name }}</p>
                            <p class="text-xs text-ink-faint">{{ t.email ?? 'Masuk dengan NIP' }}</p>
                            <p v-if="t.assignments.length" class="mt-1 flex flex-wrap gap-1">
                                <span v-for="a in t.assignments" :key="a.subject_id" class="badge bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">{{ a.code }} · {{ a.grade_level_ids.map((g) => grades.find((x) => x.id === g)?.name).join(', ') }}</span>
                            </p>
                        </td>
                        <td class="font-mono text-xs">{{ t.nip ?? '—' }}</td>
                        <td>
                            <div class="flex flex-wrap gap-1.5">
                                <StatusBadge v-if="!t.is_active" status="nonaktif" />
                                <StatusBadge v-if="t.is_validator" status="validator" />
                                <span v-if="t.must_change_password" class="badge bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-500">Belum ganti sandi</span>
                            </div>
                        </td>
                        <td class="whitespace-nowrap text-xs text-ink-soft">{{ formatDateTime(t.last_login_at) }}</td>
                        <td>
                            <div class="flex justify-end gap-0.5">
                                <button type="button" class="btn-icon" :aria-label="`Ubah ${t.name}`" title="Ubah" @click="openEdit(t)"><Pencil class="size-4" /></button>
                                <button type="button" class="btn-icon" :aria-label="`Atur ulang sandi ${t.name}`" title="Atur ulang kata sandi" @click="resetPassword(t)"><KeyRound class="size-4" /></button>
                                <button type="button" class="btn-icon" :aria-label="t.is_active ? `Nonaktifkan ${t.name}` : `Aktifkan ${t.name}`" :title="t.is_active ? 'Nonaktifkan' : 'Aktifkan'" @click="toggleActive(t)">
                                    <component :is="t.is_active ? UserX : UserCheck" class="size-4" />
                                </button>
                                <button type="button" class="btn-icon hover:!text-danger-600" :aria-label="`Hapus ${t.name}`" title="Hapus" @click="remove(t)"><Trash2 class="size-4" /></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="Belum ada akun guru" :description="filters.search || filters.role ? 'Tidak ada guru yang cocok dengan penyaringan.' : 'Tambah satu per satu atau impor dari berkas Excel.'">
            <template #icon><Users class="size-7" /></template>
            <Link v-if="!filters.search && !filters.role" href="/admin/teachers/import" class="btn-primary"><Upload class="size-4" /> Impor dari Excel</Link>
        </EmptyState>
        <Pagination :meta="teachers" />
    </section>

    <Modal :open="open" :title="editing ? 'Ubah data guru' : 'Tambah guru'" :description="editing ? undefined : 'Kata sandi awal dibuat otomatis dan ditampilkan satu kali.'" @close="open = false">
        <form id="teacher-form" class="space-y-4" @submit.prevent="submit">
            <FormField v-slot="{ id, invalid }" label="Nama lengkap (beserta gelar)" :error="form.errors.name" required>
                <input :id="id" v-model="form.name" class="input" :class="{ 'input-error': invalid }" />
            </FormField>
            <p class="rounded-lg bg-surface-2 px-3 py-2 text-xs text-ink-soft">Isi <strong>email atau NIP</strong> (salah satu cukup). Keduanya dapat dipakai untuk masuk.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField v-slot="{ id, invalid }" label="Email" :error="form.errors.email">
                    <input :id="id" v-model="form.email" type="email" class="input" :class="{ 'input-error': invalid }" autocomplete="off" />
                </FormField>
                <FormField v-slot="{ id, invalid }" label="NIP" :error="form.errors.nip" hint="8–25 digit.">
                    <input :id="id" v-model="form.nip" inputmode="numeric" class="input font-mono" :class="{ 'input-error': invalid }" />
                </FormField>
            </div>
            <FormField v-slot="{ id, invalid }" label="Telepon" :error="form.errors.phone">
                <input :id="id" v-model="form.phone" inputmode="tel" class="input" :class="{ 'input-error': invalid }" />
            </FormField>

            <template v-if="!editing">
                <FormField v-slot="{ id, invalid }" label="Kata sandi awal" :error="form.errors.password" hint="Kosongkan agar dibuat acak. Minimal 8 karakter.">
                    <div class="relative">
                        <input :id="id" v-model="form.password" :type="showPassword ? 'text' : 'password'" class="input pr-11" :class="{ 'input-error': invalid }" autocomplete="new-password" />
                        <button type="button" class="btn-icon absolute right-1.5 top-1/2 -translate-y-1/2" :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'" @click="showPassword = !showPassword">
                            <component :is="showPassword ? EyeOff : Eye" class="size-4" />
                        </button>
                    </div>
                </FormField>
                <label class="flex cursor-pointer items-center gap-2.5 text-sm text-ink">
                    <input v-model="form.must_change_password" type="checkbox" class="size-4 accent-primary-600" /> Wajib ganti kata sandi saat login pertama
                </label>
            </template>
        </form>
        <template #footer>
            <button type="button" class="btn-secondary" @click="open = false">Batal</button>
            <button type="submit" form="teacher-form" class="btn-primary" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" /> {{ editing ? 'Simpan' : 'Buat akun' }}
            </button>
        </template>
    </Modal>
</template>
