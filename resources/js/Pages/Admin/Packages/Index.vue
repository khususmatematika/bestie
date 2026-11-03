<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Archive, ChevronDown, Download, FolderOpen, Lock, LoaderCircle, Search, ShieldCheck, Trash2, UserRoundCog } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { formatDateTime } from '@/Composables/useFormat';
import { useConfirm } from '@/Composables/useConfirm';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FormField from '@/Components/ui/FormField.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import ProgressBar from '@/Components/ui/ProgressBar.vue';
import StatusBadge from '@/Components/ui/StatusBadge.vue';
import type { PackageSummary, Paginated } from '@/types';

const props = defineProps<{
    packages: Paginated<PackageSummary>;
    counts: { value: string; label: string; total: number }[];
    filters: { status: string; subject_id: number | ''; search: string };
    subjects: { id: number; name: string }[];
    validators: { id: number; name: string }[];
}>();

const { confirm } = useConfirm();
const status = ref(props.filters.status);
const subjectId = ref<number | ''>(props.filters.subject_id);
const search = ref(props.filters.search);

// Unduh massal: paket yang dicentang (lintas halaman tidak ikut), atau seluruh hasil penyaringan bila tidak ada yang dicentang.
const selected = ref<number[]>([]);
const bulkOpen = ref(false);
const pageIds = computed(() => props.packages.data.filter((p) => p.status !== 'draft').map((p) => p.id));
const allSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every((id) => selected.value.includes(id)));

function toggleAll(): void {
    selected.value = allSelected.value ? selected.value.filter((id) => !pageIds.value.includes(id)) : [...new Set([...selected.value, ...pageIds.value])];
}

const bulkKinds = [
    { kind: 'final', label: 'Naskah final (DOCX)', hint: 'Hanya paket Fix/Terkunci' },
    { kind: 'validated', label: 'Soal tervalidasi (DOCX)', hint: 'Nomor yang sudah disetujui' },
    { kind: 'recap_docx', label: 'Rekap validasi siap cetak (DOCX)', hint: 'Daftar periksa per kolom + kemiripan' },
    { kind: 'recap_xlsx', label: 'Rekap validasi (XLSX)', hint: 'Lembar kerja siap cetak' },
];

const closeBulk = (): void => {
    bulkOpen.value = false;
};
onMounted(() => document.addEventListener('click', closeBulk));
onBeforeUnmount(() => document.removeEventListener('click', closeBulk));

function bulkUrl(kind: string): string {
    const params = new URLSearchParams({ kind });

    if (selected.value.length) {
        selected.value.forEach((id) => params.append('ids[]', String(id)));
    } else {
        if (status.value) params.set('status', status.value);
        if (subjectId.value) params.set('subject_id', String(subjectId.value));
        if (search.value) params.set('search', search.value);
    }

    return `/admin/packages/download?${params.toString()}`;
}

function apply(): void {
    router.get(
        '/admin/packages',
        { status: status.value || undefined, subject_id: subjectId.value || undefined, search: search.value || undefined },
        { preserveState: true, replace: true },
    );
}

let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(apply, 350);
});

function setStatus(value: string): void {
    status.value = status.value === value ? '' : value;
    apply();
}

async function lock(p: PackageSummary): Promise<void> {
    const ok = await confirm({
        title: `Kunci paket “${p.title}”?`,
        message: 'Paket terkunci tidak dapat diubah lagi (nomor, berkas, keputusan validator).',
        confirmLabel: 'Kunci paket',
        danger: true,
    });
    if (ok) router.post(`/admin/packages/${p.id}/lock`, {}, { preserveScroll: true });
}

async function removePackage(p: PackageSummary): Promise<void> {
    const ok = await confirm({
        title: `Hapus paket “${p.title}”?`,
        message: `Paket milik ${p.teacher} beserta seluruh soalnya akan dihapus. Paket yang sudah Fix atau terkunci tidak dapat dihapus.`,
        confirmLabel: 'Hapus paket',
        danger: true,
    });
    if (ok) router.delete(`/admin/packages/${p.id}`, { preserveScroll: true });
}

const changing = ref<PackageSummary | null>(null);
const form = useForm<{ validator_id: number | '' }>({ validator_id: '' });

function openChange(p: PackageSummary): void {
    changing.value = p;
    form.reset();
    form.clearErrors();
}

function submitChange(): void {
    if (!changing.value) return;
    form.post(`/admin/packages/${changing.value.id}/change-validator`, { preserveScroll: true, onSuccess: () => (changing.value = null) });
}
</script>

<template>
    <Head title="Monitoring Paket" />
    <PageHeader title="Monitoring paket soal" description="Pantau status paket pada periode terpilih, kunci paket yang sudah Fix, atau ganti validator." />

    <div class="mb-6 flex flex-wrap gap-2">
        <button
            v-for="c in counts"
            :key="c.value"
            type="button"
            class="flex items-center gap-2 rounded-xl border px-3 py-2 text-sm transition"
            :class="status === c.value ? 'border-primary-500 bg-primary-50 dark:bg-primary-500/10' : 'border-line bg-surface hover:bg-surface-2'"
            :aria-pressed="status === c.value"
            @click="setStatus(c.value)"
        >
            <span class="text-lg font-extrabold tabular-nums text-ink">{{ c.total }}</span>
            <span class="text-xs font-semibold text-ink-soft">{{ c.label }}</span>
        </button>
    </div>

    <section class="card">
        <div class="flex flex-col gap-3 border-b border-line p-4 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-faint" />
                <input v-model="search" type="search" class="input pl-10" placeholder="Cari judul paket atau nama guru…" aria-label="Cari paket" />
            </div>
            <select v-model="subjectId" class="input sm:max-w-52" aria-label="Filter mapel" @change="apply">
                <option value="">Semua mapel</option>
                <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>

            <div class="relative">
                <button type="button" class="btn-primary w-full sm:w-auto" :aria-expanded="bulkOpen" aria-haspopup="menu" @click.stop="bulkOpen = !bulkOpen">
                    <Archive class="size-4" />
                    Unduh massal (ZIP)<span v-if="selected.length" class="ml-1 rounded-full bg-white/25 px-2 text-xs">{{ selected.length }}</span>
                    <ChevronDown class="size-4" />
                </button>
                <div v-if="bulkOpen" role="menu" class="card absolute right-0 z-20 mt-2 w-80 p-1.5 shadow-pop" @click="bulkOpen = false">
                    <p class="px-3 py-2 text-xs text-ink-soft">
                        {{ selected.length ? `${selected.length} paket dicentang.` : 'Tidak ada yang dicentang: seluruh paket sesuai penyaringan saat ini (selain draf) diunduh.' }}
                    </p>
                    <a v-for="k in bulkKinds" :key="k.kind" :href="bulkUrl(k.kind)" role="menuitem" class="block rounded-lg px-3 py-2 hover:bg-surface-2">
                        <span class="block text-sm font-semibold text-ink">{{ k.label }}</span>
                        <span class="block text-xs text-ink-faint">{{ k.hint }}</span>
                    </a>
                    <p class="px-3 pb-2 pt-1 text-[11px] text-ink-faint">Maks. 80 paket per unduhan. Pembuatan dapat memakan waktu beberapa detik.</p>
                </div>
            </div>
        </div>

        <div v-if="packages.data.length" class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th class="w-10"><input type="checkbox" class="size-4 accent-primary-600" :checked="allSelected" aria-label="Pilih semua paket di halaman ini" @change="toggleAll" /></th><th>Paket</th><th>Guru</th><th>Validator</th><th>Status</th><th>Progres</th><th>Dikirim</th><th class="text-right">Aksi</th></tr>
                </thead>
                <tbody>
                    <tr v-for="p in packages.data" :key="p.id">
                        <td><input v-if="p.status !== 'draft'" v-model="selected" type="checkbox" :value="p.id" class="size-4 accent-primary-600" :aria-label="`Pilih ${p.title}`" /></td>
                        <td>
                            <p class="font-semibold text-ink">{{ p.title }}</p>
                            <p class="text-xs text-ink-faint">{{ p.subject }} · Kelas {{ p.grade }} · {{ p.period }}</p>
                        </td>
                        <td class="text-ink-soft">{{ p.teacher }}</td>
                        <td class="text-ink-soft">
                            {{ p.validator ?? '—' }}
                            <span v-if="p.is_self_validated" class="badge ml-1 bg-accent-50 text-accent-700 dark:bg-accent-500/15 dark:text-accent-100"><ShieldCheck class="size-3.5" /> Mandiri</span>
                        </td>
                        <td><StatusBadge :status="p.status" /></td>
                        <td class="min-w-36"><ProgressBar :value="p.progress" :label="`Progres ${p.title}`" /></td>
                        <td class="whitespace-nowrap text-xs text-ink-soft">{{ formatDateTime(p.submitted_at) }}</td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <a :href="`/packages/${p.id}/downloads/recap?format=docx`" class="btn-icon" :aria-label="`Unduh rekap ${p.title}`" title="Rekap validasi siap cetak (DOCX)"><Download class="size-4" /></a>
                                <button v-if="p.status !== 'fix' && p.status !== 'terkunci'" type="button" class="btn-icon hover:!text-danger-600" :aria-label="`Hapus paket ${p.title}`" title="Hapus paket" @click="removePackage(p)"><Trash2 class="size-4" /></button>
                                <button v-if="p.validator && p.status !== 'terkunci'" type="button" class="btn-icon" :aria-label="`Ganti validator ${p.title}`" @click="openChange(p)"><UserRoundCog class="size-4" /></button>
                                <button v-if="p.status === 'fix'" type="button" class="btn-secondary btn-sm" @click="lock(p)"><Lock class="size-3.5" /> Kunci</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="Belum ada paket" description="Paket yang dibuat guru pada periode terpilih akan tampil di sini.">
            <template #icon><FolderOpen class="size-7" /></template>
        </EmptyState>
        <Pagination :meta="packages" />
    </section>

    <Modal :open="changing !== null" title="Ganti validator" :description="changing ? changing.title : undefined" size="sm" @close="changing = null">
        <form id="change-validator" @submit.prevent="submitChange">
            <FormField v-slot="{ id, invalid }" label="Validator baru" :error="form.errors.validator_id" required>
                <select :id="id" v-model="form.validator_id" class="input" :class="{ 'input-error': invalid }">
                    <option value="" disabled>Pilih validator</option>
                    <option v-for="v in validators" :key="v.id" :value="v.id">{{ v.name }}</option>
                </select>
            </FormField>
            <p class="mt-3 text-xs text-ink-faint">Pergantian tercatat pada log aktivitas. Keputusan yang sudah dibuat validator sebelumnya tetap tersimpan.</p>
        </form>
        <template #footer>
            <button type="button" class="btn-secondary" @click="changing = null">Batal</button>
            <button type="submit" form="change-validator" class="btn-primary" :disabled="form.validator_id === '' || form.processing">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" /> Ganti
            </button>
        </template>
    </Modal>
</template>
