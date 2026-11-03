<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CircleCheck, ChevronLeft, ChevronRight, CircleSlash, Keyboard, Lock, RotateCcw, ShieldCheck } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { formatDate, formatDateTime } from '@/Composables/useFormat';
import { useConfirm } from '@/Composables/useConfirm';
import DownloadPanel from '@/Components/package/DownloadPanel.vue';
import ItemKisi from '@/Components/package/ItemKisi.vue';
import ItemSoal from '@/Components/package/ItemSoal.vue';
import SimilarityPanel, { type SimilarMatch } from '@/Components/package/SimilarityPanel.vue';
import TranslateButton from '@/Components/package/TranslateButton.vue';
import ProgressBar from '@/Components/ui/ProgressBar.vue';
import StatusBadge from '@/Components/ui/StatusBadge.vue';
import type { DownloadsInfo, ItemDetail, PackageSummary } from '@/types';

interface NavItem {
    number: number;
    status: string;
    status_label: string;
    revised: boolean;
}

const props = defineProps<{
    pkg: PackageSummary & { can_review: boolean; readonly_reason: string | null; validation_deadline: string | null };
    nav: NavItem[];
    downloads: DownloadsInfo;
    item: ItemDetail;
    similar: SimilarMatch[];
    checklist_items: { key: string; label: string }[];
}>();

const { confirm } = useConfirm();
const noteField = ref<HTMLTextAreaElement | null>(null);

// Semua butir daftar periksa tercentang secara bawaan; validator mencabut centang pada yang tidak terpenuhi.
function defaultChecklist(): Record<string, boolean> {
    return Object.fromEntries(props.checklist_items.map((c) => [c.key, true]));
}

const form = useForm({ note: '', checklist: defaultChecklist() });

// Ganti nomor => formulir keputusan dikosongkan.
watch(
    () => props.item.id,
    () => {
        form.reset();
        form.clearErrors();
        form.checklist = defaultChecklist();
    },
);

const unmet = computed(() => props.checklist_items.filter((c) => !form.checklist[c.key]));

const canDecide = computed(() => props.pkg.can_review && ['belum_ditelaah', 'direvisi'].includes(props.item.status));
const canReopen = computed(() => props.pkg.can_review && props.item.status === 'disetujui');
const noteReady = computed(() => form.note.trim().length >= 10);

const ringTone: Record<string, string> = {
    belum_ditelaah: 'bg-slate-100 text-slate-700 dark:bg-slate-500/20 dark:text-slate-200',
    disetujui: 'bg-success-50 text-success-700 ring-1 ring-success-500/50 dark:bg-success-500/15 dark:text-success-500',
    perlu_ganti: 'bg-danger-50 text-danger-700 ring-1 ring-danger-500/50 dark:bg-danger-500/15 dark:text-danger-500',
    direvisi: 'bg-warning-50 text-warning-700 ring-1 ring-warning-500/60 dark:bg-warning-500/15 dark:text-warning-500',
};

function go(number: number): void {
    router.get(`/validator/packages/${props.pkg.id}/review/${number}`, {}, { preserveScroll: false, preserveState: false });
}

function move(delta: number): void {
    const index = props.nav.findIndex((n) => n.number === props.item.number);
    const target = props.nav[index + delta];
    if (target) go(target.number);
}

function approve(): void {
    if (!canDecide.value || form.processing) return;
    form.post(`/validator/items/${props.item.id}/approve`, { preserveScroll: true });
}

function requestChange(): void {
    if (!canDecide.value || form.processing) return;
    form.post(`/validator/items/${props.item.id}/request-change`, { preserveScroll: true });
}

async function reopen(): Promise<void> {
    if (!canReopen.value) return;
    if (!noteReady.value) {
        form.setError('note', 'Isi alasan membuka kembali (minimal 10 karakter).');
        noteField.value?.focus();
        return;
    }
    const ok = await confirm({
        title: `Buka kembali nomor ${props.item.number}?`,
        message: 'Nomor akan dikembalikan ke guru berstatus “Perlu ganti” dan progres paket berkurang.',
        confirmLabel: 'Buka kembali',
        danger: true,
    });
    if (ok) form.post(`/validator/items/${props.item.id}/reopen`, { preserveScroll: true });
}

// Pintasan papan ketik (PRD §8): J/K pindah nomor, A setujui, R minta revisi.
function onKey(event: KeyboardEvent): void {
    const target = event.target as HTMLElement | null;
    if (event.ctrlKey || event.metaKey || event.altKey) return;
    if (target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) return;

    switch (event.key.toLowerCase()) {
        case 'j':
            move(1);
            break;
        case 'k':
            move(-1);
            break;
        case 'a':
            approve();
            break;
        case 'r':
            event.preventDefault();
            noteField.value?.focus();
            break;
    }
}

onMounted(() => document.addEventListener('keydown', onKey));
onBeforeUnmount(() => document.removeEventListener('keydown', onKey));

const position = computed(() => props.nav.findIndex((n) => n.number === props.item.number) + 1);
</script>

<template>
    <Head :title="`Telaah No. ${item.number} — ${pkg.title}`" />

    <Link href="/validator/inbox" class="mb-3 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-soft hover:text-primary-600">
        <ArrowLeft class="size-4" /> Kembali ke inbox
    </Link>

    <header class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-extrabold tracking-tight text-ink">{{ pkg.title }}</h1>
                <StatusBadge :status="pkg.status" />
                <span v-if="pkg.is_self_validated" class="badge bg-accent-50 text-accent-700 dark:bg-accent-500/15 dark:text-accent-100"><ShieldCheck class="size-3.5" /> Divalidasi Mandiri</span>
            </div>
            <p class="mt-1 text-sm text-ink-soft">
                {{ pkg.teacher }} · {{ pkg.subject }} · Kelas {{ pkg.grade }} · {{ pkg.period }}
                <template v-if="pkg.validation_deadline"> · tenggat validasi {{ formatDate(pkg.validation_deadline) }}</template>
            </p>
        </div>
        <div class="w-full lg:w-72">
            <ProgressBar :value="pkg.progress" label="Progres validasi" />
            <p class="mt-0.5 text-right text-xs text-ink-faint">{{ pkg.approved_items }} dari {{ pkg.total_items }} nomor disetujui</p>
        </div>
    </header>

    <p v-if="pkg.readonly_reason" class="mb-5 flex items-center gap-2 rounded-xl bg-warning-50 p-3 text-sm font-medium text-warning-700 dark:bg-warning-500/10 dark:text-warning-500" role="status">
        <Lock class="size-4 shrink-0" /> {{ pkg.readonly_reason }}
    </p>
    <p v-else-if="pkg.status === 'fix'" class="mb-5 flex items-center gap-2 rounded-xl bg-success-50 p-3 text-sm font-semibold text-success-700 dark:bg-success-500/10 dark:text-success-500" role="status">
        <CircleCheck class="size-4 shrink-0" /> Semua nomor disetujui — paket berstatus Fix{{ pkg.fixed_at ? ` sejak ${formatDateTime(pkg.fixed_at)}` : '' }}.
    </p>

    <DownloadPanel :downloads="downloads" />

    <div class="grid gap-6 lg:grid-cols-[13rem_1fr]">
        <!-- Daftar nomor -->
        <nav class="card h-fit p-3 lg:sticky lg:top-20" aria-label="Daftar nomor soal">
            <p class="px-1 pb-2 text-xs font-bold uppercase tracking-wide text-ink-faint">Nomor ({{ position }}/{{ nav.length }})</p>
            <ul class="grid grid-cols-6 gap-1.5 lg:grid-cols-4">
                <li v-for="n in nav" :key="n.number">
                    <button
                        type="button"
                        class="relative flex size-9 w-full items-center justify-center rounded-lg text-sm font-bold tabular-nums transition hover:brightness-95"
                        :class="[ringTone[n.status] ?? ringTone.belum_ditelaah, n.number === item.number ? 'outline-2 outline-offset-2 outline-primary-600' : '']"
                        :aria-current="n.number === item.number ? 'true' : undefined"
                        :aria-label="`Nomor ${n.number}, ${n.status_label}`"
                        :title="n.status_label"
                        @click="go(n.number)"
                    >
                        {{ n.number }}
                    </button>
                </li>
            </ul>
            <dl class="mt-3 space-y-1 border-t border-line pt-3 text-[11px] text-ink-soft">
                <div class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-slate-300" /> Belum ditelaah</div>
                <div class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-success-500" /> Disetujui</div>
                <div class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-danger-500" /> Perlu ganti</div>
                <div class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-warning-500" /> Direvisi (cek ulang)</div>
            </dl>
        </nav>

        <div class="min-w-0 space-y-6">
            <!-- Panel dua sisi -->
            <section class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-lg font-extrabold text-ink">Nomor {{ item.number }}</h2>
                        <StatusBadge :status="item.status" />
                        <span class="text-xs text-ink-faint">Versi {{ item.version }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <button type="button" class="btn-icon" aria-label="Nomor sebelumnya (K)" :disabled="position <= 1" @click="move(-1)"><ChevronLeft class="size-4" /></button>
                        <button type="button" class="btn-icon" aria-label="Nomor berikutnya (J)" :disabled="position >= nav.length" @click="move(1)"><ChevronRight class="size-4" /></button>
                    </div>
                </div>

                <dl class="grid gap-2 border-b border-line bg-surface-2/50 px-6 py-3 text-xs sm:grid-cols-3">
                    <div><dt class="text-ink-faint">Waktu validasi terakhir</dt><dd class="font-semibold text-ink">{{ formatDateTime(item.reviewed_at) }}</dd></div>
                    <div><dt class="text-ink-faint">Waktu unggah ulang</dt><dd class="font-semibold text-ink">{{ formatDateTime(item.resubmitted_at) }}</dd></div>
                    <div><dt class="text-ink-faint">Pembaruan guru</dt><dd class="font-semibold text-ink">{{ item.revisions.length ? item.revisions[item.revisions.length - 1].uploader : '—' }}</dd></div>
                </dl>

                <div class="grid divide-y divide-line md:grid-cols-5 md:divide-x md:divide-y-0">
                    <div class="p-6 md:col-span-2">
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-ink-faint">Kisi-kisi</h3>
                        <ItemKisi :item="item" />
                    </div>
                    <div class="p-6 md:col-span-3">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-xs font-bold uppercase tracking-wide text-ink-faint">Soal</h3>
                            <TranslateButton :item="item" />
                        </div>
                        <ItemSoal :item="item" ropes />
                    </div>
                </div>
            </section>

            <SimilarityPanel :matches="similar" />

            <!-- Keputusan -->
            <section class="card p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-bold text-ink">Keputusan telaah</h2>
                    <span class="inline-flex items-center gap-1.5 text-[11px] text-ink-faint"><Keyboard class="size-3.5" /> J/K pindah · A setujui · R tulis catatan</span>
                </div>

                <fieldset class="mt-4" :disabled="!canDecide && !canReopen">
                    <legend class="label">Daftar periksa</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label v-for="c in checklist_items" :key="c.key" class="flex cursor-pointer items-center gap-2.5 text-sm text-ink">
                            <input v-model="form.checklist[c.key]" type="checkbox" class="size-4 accent-primary-600" /> {{ c.label }}
                        </label>
                    </div>
                    <p v-if="unmet.length" class="mt-2 text-xs font-medium text-warning-700 dark:text-warning-500" role="status">
                        {{ unmet.length }} butir daftar periksa tidak terpenuhi. Pertimbangkan “Minta ganti” dan jelaskan pada catatan.
                    </p>
                </fieldset>

                <div class="mt-4">
                    <label class="label" for="note">
                        {{ canReopen ? 'Alasan membuka kembali (wajib)' : 'Keterangan / catatan' }}
                        <span class="font-normal text-ink-faint">— wajib minimal 10 karakter untuk “Minta ganti”</span>
                    </label>
                    <textarea id="note" ref="noteField" v-model="form.note" rows="3" class="input" :class="{ 'input-error': form.errors.note }" :disabled="!canDecide && !canReopen" placeholder="Tulis catatan untuk guru…" />
                    <p v-if="form.errors.note" class="error-text" role="alert">{{ form.errors.note }}</p>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button type="button" class="btn-primary" :disabled="!canDecide || form.processing" @click="approve"><CircleCheck class="size-4" /> Setujui (A)</button>
                    <button type="button" class="btn-danger" :disabled="!canDecide || form.processing || !noteReady" @click="requestChange"><CircleSlash class="size-4" /> Minta ganti (R)</button>
                    <button v-if="canReopen" type="button" class="btn-secondary" :disabled="form.processing" @click="reopen"><RotateCcw class="size-4" /> Buka kembali</button>
                    <p v-if="!canDecide && !canReopen" class="text-xs text-ink-faint">Nomor ini tidak dapat diputuskan pada statusnya saat ini{{ item.status === 'perlu_ganti' ? ' — menunggu unggah ulang guru.' : '.' }}</p>
                </div>
            </section>

            <!-- Riwayat -->
            <section v-if="item.reviews.length || item.revisions.length" class="card p-6">
                <h2 class="text-base font-bold text-ink">Riwayat nomor {{ item.number }}</h2>
                <ol class="mt-4 space-y-3">
                    <li v-for="r in [...item.reviews].reverse()" :key="`r${r.id}`" class="rounded-xl border border-line p-3 text-sm">
                        <p class="text-xs text-ink-faint"><strong class="text-ink">{{ r.decision_label }}</strong> · {{ r.validator }} · {{ formatDateTime(r.reviewed_at) }}</p>
                        <p v-if="r.note" class="mt-1 whitespace-pre-line text-ink">{{ r.note }}</p>
                    </li>
                    <li v-for="v in [...item.revisions].reverse()" :key="`v${v.id}`" class="rounded-xl border border-dashed border-line p-3 text-sm text-ink-soft">
                        Versi {{ v.version }} · {{ v.source === 'unggah_ulang' ? 'diunggah ulang' : v.source === 'ekstraksi' ? 'hasil ekstraksi' : 'dari editor' }} oleh {{ v.uploader }} · {{ formatDateTime(v.uploaded_at) }}
                    </li>
                </ol>
            </section>
        </div>
    </div>
</template>
