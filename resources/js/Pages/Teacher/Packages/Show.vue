<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronDown,
    Download,
    FilePlus2,
    LoaderCircle,
    Lock,
    Pencil,
    RefreshCw,
    Send,
    ShieldCheck,
    Trash2,
    TriangleAlert,
    Upload,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { formatDate, formatDateTime } from '@/Composables/useFormat';
import { useConfirm } from '@/Composables/useConfirm';
import DownloadPanel from '@/Components/package/DownloadPanel.vue';
import ItemForm from '@/Components/package/ItemForm.vue';
import ItemKisi from '@/Components/package/ItemKisi.vue';
import ItemSoal from '@/Components/package/ItemSoal.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FormField from '@/Components/ui/FormField.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import ProgressBar from '@/Components/ui/ProgressBar.vue';
import StatusBadge from '@/Components/ui/StatusBadge.vue';
import type { DownloadsInfo, ItemDetail, PackageFileInfo, PackageSummary, TypeOption } from '@/types';

interface PackageDetail extends PackageSummary {
    can_edit: boolean;
    can_revise: boolean;
    readonly_reason: string | null;
    period_label: string;
    validation_deadline: string | null;
    revision_deadline: string | null;
}

const props = defineProps<{
    pkg: PackageDetail;
    items: ItemDetail[];
    files: PackageFileInfo[];
    extracting: boolean;
    downloads: DownloadsInfo | null;
    validators: { id: number; name: string; is_self: boolean }[];
    types: TypeOption[];
    max_mb: number;
}>();

const { confirm } = useConfirm();

// ── Alur status ───────────────────────────────────────────────────────────────
const steps = ['Draft', 'Terkirim', 'Ditelaah', 'Fix'];
const stepIndex = computed(() => ({ draft: 0, terkirim: 1, ditelaah: 2, perlu_revisi: 2, fix: 3, terkunci: 4 })[props.pkg.status] ?? 0);

// ── Polling selama ekstraksi berjalan ─────────────────────────────────────────
let timer: ReturnType<typeof setInterval> | undefined;

function startPolling(): void {
    stopPolling();
    timer = setInterval(() => router.reload({ only: ['pkg', 'items', 'files', 'extracting', 'validators'] }), 2500);
}

function stopPolling(): void {
    if (timer) clearInterval(timer);
    timer = undefined;
}

onMounted(() => props.extracting && startPolling());
onBeforeUnmount(stopPolling);
watch(
    () => props.extracting,
    (running) => (running ? startPolling() : stopPolling()),
);

// ── Butir ─────────────────────────────────────────────────────────────────────
const expanded = ref<number | null>(null);
const editing = ref<ItemDetail | null>(null);
const mode = ref<'draft' | 'revise'>('draft');
const modalOpen = ref(false);

function openEditor(item: ItemDetail, m: 'draft' | 'revise'): void {
    editing.value = item;
    mode.value = m;
    modalOpen.value = true;
}

function lastNote(item: ItemDetail): string | null {
    const withNote = [...item.reviews].reverse().find((r) => r.note && r.decision !== 'setuju');
    return withNote?.note ?? null;
}

function addItem(): void {
    router.post(`/teacher/packages/${props.pkg.id}/items`, {}, { preserveScroll: true });
}

async function removeItem(item: ItemDetail): Promise<void> {
    const ok = await confirm({ title: `Hapus nomor ${item.number}?`, message: 'Nomor akan dihapus dari paket draft ini.', confirmLabel: 'Hapus', danger: true });
    if (ok) router.delete(`/teacher/items/${item.id}`, { preserveScroll: true });
}

const incomplete = computed(() => props.items.filter((i) => i.missing.length > 0));
const needChange = computed(() => props.items.filter((i) => i.status === 'perlu_ganti'));

// ── Berkas ────────────────────────────────────────────────────────────────────
const filesForm = useForm<{ kisi_kisi: File | null; naskah: File | null }>({ kisi_kisi: null, naskah: null });

async function submitFiles(): Promise<void> {
    if (props.items.length > 0) {
        const ok = await confirm({
            title: 'Ganti berkas dan ekstrak ulang?',
            message: 'Semua nomor draft saat ini (termasuk hasil sunting manual) akan diganti dengan hasil ekstraksi berkas baru.',
            confirmLabel: 'Ganti & ekstrak ulang',
            danger: true,
        });
        if (!ok) return;
    }

    filesForm.post(`/teacher/packages/${props.pkg.id}/files`, { forceFormData: true, preserveScroll: true, onSuccess: () => filesForm.reset() });
}

// ── Kirim ke validator ────────────────────────────────────────────────────────
const submitForm = useForm<{ validator_id: number | '' }>({ validator_id: '' });

async function submitPackage(): Promise<void> {
    const ok = await confirm({
        title: 'Kirim paket ke validator?',
        message: 'Setelah dikirim, nomor tidak dapat disunting langsung. Perubahan hanya lewat unggah ulang nomor yang diminta diganti validator.',
        confirmLabel: 'Kirim paket',
    });
    if (ok) submitForm.post(`/teacher/packages/${props.pkg.id}/submit`, { preserveScroll: true });
}

async function removePackage(): Promise<void> {
    const ok = await confirm({ title: 'Hapus paket draft?', message: 'Paket dan seluruh nomornya akan dihapus.', confirmLabel: 'Hapus paket', danger: true });
    if (ok) router.delete(`/teacher/packages/${props.pkg.id}`);
}

// ── Unggah ulang seluruh naskah ───────────────────────────────────────────────
const docForm = useForm<{ naskah: File | null }>({ naskah: null });

function submitDocument(): void {
    docForm.post(`/teacher/packages/${props.pkg.id}/revision-document`, { forceFormData: true, preserveScroll: true, onSuccess: () => docForm.reset() });
}

function sizeLabel(bytes: number): string {
    return bytes > 1_048_576 ? `${(bytes / 1_048_576).toFixed(1)} MB` : `${Math.max(1, Math.ceil(bytes / 1024))} KB`;
}
</script>

<template>
    <Head :title="pkg.title" />

    <Link href="/teacher/packages" class="mb-3 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-soft hover:text-primary-600">
        <ArrowLeft class="size-4" /> Kembali ke paket saya
    </Link>

    <PageHeader :title="pkg.title" :description="`${pkg.subject} · Kelas ${pkg.grade} · ${pkg.period_label}`">
        <button v-if="pkg.can_edit" type="button" class="btn-secondary text-danger-600" @click="removePackage"><Trash2 class="size-4" /> Hapus draft</button>
    </PageHeader>

    <p v-if="pkg.readonly_reason" class="mb-6 flex items-center gap-2 rounded-xl bg-warning-50 p-3 text-sm font-medium text-warning-700 dark:bg-warning-500/10 dark:text-warning-500" role="status">
        <Lock class="size-4 shrink-0" /> {{ pkg.readonly_reason }}
    </p>

    <DownloadPanel v-if="downloads" :downloads="downloads" />

    <!-- Ringkasan & alur -->
    <section class="card mb-6 p-6">
        <div class="flex flex-wrap items-center gap-3">
            <StatusBadge :status="pkg.status" />
            <span v-if="pkg.is_self_validated" class="badge bg-accent-50 text-accent-700 dark:bg-accent-500/15 dark:text-accent-100"><ShieldCheck class="size-3.5" /> Divalidasi Mandiri</span>
            <span v-if="pkg.validator" class="text-sm text-ink-soft">Validator: <strong class="text-ink">{{ pkg.validator }}</strong></span>
        </div>

        <ol class="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-4" aria-label="Alur paket">
            <li v-for="(s, i) in steps" :key="s" class="text-center">
                <div class="h-1.5 rounded-full" :class="i <= stepIndex ? (pkg.status === 'perlu_revisi' && i === 2 ? 'bg-danger-500' : 'bg-primary-600') : 'bg-surface-2'" />
                <p class="mt-1.5 text-xs font-semibold" :class="i <= stepIndex ? 'text-ink' : 'text-ink-faint'">{{ i === 2 && pkg.status === 'perlu_revisi' ? 'Perlu revisi' : s }}</p>
            </li>
        </ol>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
                <ProgressBar :value="pkg.progress" label="Progres validasi" />
                <p class="mt-1 text-xs text-ink-faint">{{ pkg.approved_items }} dari {{ pkg.total_items }} nomor disetujui</p>
            </div>
            <dl class="grid grid-cols-1 gap-2 text-xs sm:grid-cols-3">
                <div><dt class="text-ink-faint">Dikirim</dt><dd class="font-semibold text-ink">{{ formatDateTime(pkg.submitted_at) }}</dd></div>
                <div><dt class="text-ink-faint">Mulai ditelaah</dt><dd class="font-semibold text-ink">{{ formatDateTime(pkg.first_reviewed_at) }}</dd></div>
                <div><dt class="text-ink-faint">Fix</dt><dd class="font-semibold text-ink">{{ formatDateTime(pkg.fixed_at) }}</dd></div>
            </dl>
        </div>
        <p v-if="pkg.validation_deadline || pkg.revision_deadline" class="mt-3 text-xs text-ink-faint">
            Tenggat validasi {{ formatDate(pkg.validation_deadline) }} · tenggat revisi {{ formatDate(pkg.revision_deadline) }}
        </p>
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <!-- Butir -->
            <section class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4">
                    <h2 class="text-base font-bold text-ink">Nomor soal ({{ items.length }})</h2>
                    <button v-if="pkg.can_edit" type="button" class="btn-secondary btn-sm" @click="addItem"><FilePlus2 class="size-3.5" /> Tambah nomor</button>
                </div>

                <p v-if="needChange.length && pkg.can_revise" class="m-4 rounded-xl bg-danger-50 p-3 text-sm font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-500" role="alert">
                    {{ needChange.length }} nomor diminta diganti: {{ needChange.map((i) => i.number).join(', ') }}. Buka catatan validator lalu unggah ulang.
                </p>

                <ul v-if="items.length" class="divide-y divide-line">
                    <li v-for="i in items" :key="i.id">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 sm:px-6">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-surface-2 text-sm font-extrabold tabular-nums text-ink">{{ i.number }}</span>
                            <StatusBadge :status="i.status" />
                            <span class="text-sm text-ink-soft">{{ i.type_label }}</span>

                            <div class="ml-auto flex flex-wrap items-center justify-end gap-1">
                                <button v-if="pkg.can_revise && i.status === 'perlu_ganti'" type="button" class="btn-primary btn-sm" @click="openEditor(i, 'revise')"><RefreshCw class="size-3.5" /> Unggah ulang</button>
                                <button v-if="pkg.can_edit" type="button" class="btn-icon" :aria-label="`Sunting nomor ${i.number}`" @click="openEditor(i, 'draft')"><Pencil class="size-4" /></button>
                                <button v-if="pkg.can_edit" type="button" class="btn-icon hover:!text-danger-600" :aria-label="`Hapus nomor ${i.number}`" @click="removeItem(i)"><Trash2 class="size-4" /></button>
                                <button type="button" class="btn-icon" :aria-expanded="expanded === i.id" :aria-label="`Detail nomor ${i.number}`" @click="expanded = expanded === i.id ? null : i.id">
                                    <ChevronDown class="size-4 transition" :class="{ 'rotate-180': expanded === i.id }" />
                                </button>
                            </div>

                            <dl class="flex w-full flex-wrap gap-x-5 gap-y-1 text-xs text-ink-soft">
                                <div class="flex gap-1.5">
                                    <dt class="text-ink-faint">Kelengkapan</dt>
                                    <dd v-if="!i.missing.length" class="font-semibold text-success-700 dark:text-success-500">Lengkap</dd>
                                    <dd v-else class="inline-flex items-center gap-1 text-warning-700 dark:text-warning-500"><TriangleAlert class="size-3.5 shrink-0" /> {{ i.missing.join(', ') }}</dd>
                                </div>
                                <div class="flex gap-1.5"><dt class="text-ink-faint">Divalidasi</dt><dd>{{ formatDateTime(i.reviewed_at) }}</dd></div>
                                <div class="flex gap-1.5">
                                    <dt class="text-ink-faint">Diunggah ulang</dt>
                                    <dd>{{ formatDateTime(i.resubmitted_at) }}<span v-if="i.version > 1" class="ml-1 text-ink-faint">(v{{ i.version }})</span></dd>
                                </div>
                            </dl>
                        </div>

                        <div v-if="expanded === i.id" class="border-t border-line bg-surface-2/40 p-4 sm:p-6">
                            <div class="grid gap-6 md:grid-cols-2">
                                <div class="min-w-0"><p class="mb-2 text-xs font-bold uppercase tracking-wide text-ink-faint">Kisi-kisi</p><ItemKisi :item="i" /></div>
                                <div class="min-w-0"><p class="mb-2 text-xs font-bold uppercase tracking-wide text-ink-faint">Soal</p><ItemSoal :item="i" /></div>
                            </div>
                            <div v-if="i.reviews.length" class="mt-4 border-t border-line pt-4">
                                <p class="mb-2 text-xs font-bold uppercase tracking-wide text-ink-faint">Catatan validator</p>
                                <ul class="space-y-2">
                                    <li v-for="r in i.reviews" :key="r.id" class="rounded-xl border border-line bg-surface p-3 text-sm">
                                        <p class="text-xs text-ink-faint"><strong class="text-ink">{{ r.decision_label }}</strong> · {{ r.validator }} · {{ formatDateTime(r.reviewed_at) }}</p>
                                        <p v-if="r.note" class="mt-1 whitespace-pre-line break-words text-ink">{{ r.note }}</p>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </li>
                </ul>
                <EmptyState v-else title="Belum ada nomor soal" :description="extracting ? 'Berkas sedang diekstrak…' : 'Unggah kisi-kisi dan naskah sesuai template, atau tambahkan nomor secara manual.'" />
            </section>

            <!-- Unggah ulang seluruh naskah -->
            <section v-if="pkg.can_revise && needChange.length" class="card p-6">
                <h2 class="text-base font-bold text-ink">Unggah ulang dari naskah (.docx atau .pdf)</h2>
                <p class="mt-1 text-sm text-ink-soft">Hanya nomor berstatus “Perlu ganti” yang diperbarui; nomor lain pada berkas diabaikan.</p>
                <form class="mt-4 flex flex-wrap items-center gap-3" @submit.prevent="submitDocument">
                    <input type="file" accept=".docx,.pdf" class="min-w-0 max-w-full text-sm" aria-label="Naskah revisi" @change="docForm.naskah = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                    <button type="submit" class="btn-primary btn-sm" :disabled="!docForm.naskah || docForm.processing"><Upload class="size-3.5" /> Unggah</button>
                </form>
                <p v-if="docForm.errors.naskah" class="error-text" role="alert">{{ docForm.errors.naskah }}</p>
            </section>
        </div>

        <div class="space-y-6">
            <!-- Kirim -->
            <section v-if="pkg.can_edit" class="card p-6">
                <h2 class="text-base font-bold text-ink">Kirim ke validator</h2>
                <p v-if="!items.length" class="mt-2 text-sm text-ink-soft">Tambahkan nomor soal terlebih dahulu.</p>
                <p v-else-if="incomplete.length" class="mt-2 flex items-start gap-2 text-sm text-warning-700 dark:text-warning-500">
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" /> {{ incomplete.length }} nomor belum lengkap (indikator, kunci, bobot, teks soal). Lengkapi dahulu.
                </p>
                <form v-else class="mt-3 space-y-4" @submit.prevent="submitPackage">
                    <FormField v-slot="{ id, invalid }" label="Pilih validator" :error="submitForm.errors.validator_id" required>
                        <select :id="id" v-model="submitForm.validator_id" class="input" :class="{ 'input-error': invalid }">
                            <option value="" disabled>Pilih validator</option>
                            <option v-for="v in validators" :key="v.id" :value="v.id">{{ v.name }}{{ v.is_self ? ' (diri sendiri)' : '' }}</option>
                        </select>
                    </FormField>
                    <p v-if="!validators.length" class="text-xs text-danger-600">Belum ada validator yang sesuai untuk mapel ini. Hubungi admin.</p>
                    <button type="submit" class="btn-primary w-full" :disabled="submitForm.validator_id === '' || submitForm.processing">
                        <LoaderCircle v-if="submitForm.processing" class="size-4 animate-spin" />
                        <Send v-else class="size-4" /> Kirim paket
                    </button>
                </form>
            </section>

            <!-- Berkas -->
            <section class="card p-6">
                <h2 class="text-base font-bold text-ink">Berkas</h2>
                <p v-if="extracting" class="mt-2 inline-flex items-center gap-2 text-xs font-semibold text-primary-600"><LoaderCircle class="size-3.5 animate-spin" /> Mengekstrak per nomor… (antrian harus berjalan)</p>

                <ul v-if="files.length" class="mt-3 space-y-3">
                    <li v-for="f in files" :key="f.id" class="rounded-xl border border-line p-3 text-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-ink">{{ f.name }}</p>
                                <p class="text-xs text-ink-faint">{{ f.kind_label }} · {{ sizeLabel(f.size) }} · {{ formatDateTime(f.uploaded_at) }}</p>
                            </div>
                            <a :href="`/packages/${pkg.id}/files/${f.id}`" class="btn-icon" :aria-label="`Unduh ${f.name}`"><Download class="size-4" /></a>
                        </div>
                        <p v-if="f.kind !== 'lampiran'" class="mt-1.5 text-xs">
                            Ekstraksi: <strong :class="f.extraction_status === 'failed' ? 'text-danger-600' : 'text-ink'">{{ f.extraction_label }}</strong>
                        </p>
                        <p v-if="f.error" class="mt-1 text-xs text-danger-600" role="alert">{{ f.error }}</p>
                        <ul v-if="f.warnings.length" class="mt-1 list-disc space-y-0.5 pl-4 text-xs text-warning-700 dark:text-warning-500">
                            <li v-for="(w, n) in f.warnings" :key="n">{{ w }}</li>
                        </ul>
                    </li>
                </ul>
                <p v-else class="mt-2 text-sm text-ink-soft">Belum ada berkas.</p>

                <form v-if="pkg.can_edit" class="mt-4 space-y-3 border-t border-line pt-4" @submit.prevent="submitFiles">
                    <p class="text-xs font-bold uppercase tracking-wide text-ink-faint">Unggah / ganti berkas</p>
                    <div>
                        <label class="label" for="f-kisi">Kisi-kisi (.xlsx/.csv)</label>
                        <input id="f-kisi" type="file" accept=".xlsx,.docx,.pdf" class="text-sm" @change="filesForm.kisi_kisi = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                        <p v-if="filesForm.errors.kisi_kisi" class="error-text" role="alert">{{ filesForm.errors.kisi_kisi }}</p>
                    </div>
                    <div>
                        <label class="label" for="f-naskah">Naskah (.docx atau .pdf)</label>
                        <input id="f-naskah" type="file" accept=".docx,.pdf" class="text-sm" @change="filesForm.naskah = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                        <p v-if="filesForm.errors.naskah" class="error-text" role="alert">{{ filesForm.errors.naskah }}</p>
                    </div>
                    <button type="submit" class="btn-secondary btn-sm" :disabled="(!filesForm.kisi_kisi && !filesForm.naskah) || filesForm.processing"><Upload class="size-3.5" /> Unggah &amp; ekstrak</button>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <a href="/templates/kisi-kisi" class="font-semibold text-primary-600 hover:underline">Template kisi-kisi</a>
                        <a href="/templates/naskah" class="font-semibold text-primary-600 hover:underline">Template naskah</a>
                    </div>
                </form>
            </section>
        </div>
    </div>

    <Modal
        :open="modalOpen"
        :title="editing ? `${mode === 'draft' ? 'Sunting' : 'Unggah ulang'} nomor ${editing.number}` : ''"
        :description="mode === 'revise' ? 'Perbaiki soal sesuai catatan validator. Versi sebelumnya tetap tersimpan.' : undefined"
        size="lg"
        @close="modalOpen = false"
    >
        <ItemForm v-if="editing && modalOpen" :key="`${editing.id}-${mode}`" :item="editing" :types="types" :mode="mode" :note="lastNote(editing)" @done="modalOpen = false" @cancel="modalOpen = false" />
    </Modal>
</template>
