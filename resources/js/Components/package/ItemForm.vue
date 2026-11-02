<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Paperclip } from '@lucide/vue';
import { computed, ref } from 'vue';
import MathText from '@/Components/package/MathText.vue';
import FormField from '@/Components/ui/FormField.vue';
import type { ItemDetail, TypeOption } from '@/types';

const props = defineProps<{
    item: ItemDetail;
    types: TypeOption[];
    /** draft = sunting langsung (PUT); revise = unggah ulang nomor yang diminta diganti (POST, versi baru). */
    mode: 'draft' | 'revise';
    /** Catatan terakhir validator, ditampilkan saat revisi. */
    note?: string | null;
}>();

const emit = defineEmits<{ done: []; cancel: [] }>();

const LETTERS = ['A', 'B', 'C', 'D', 'E'] as const;

const imageMap = ref<Record<string, string>>({ ...(props.item.image_map ?? {}) });
const pasteError = ref('');
const pasting = ref(false);

function csrf(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}

/** Tempel gambar (Ctrl+V / screenshot) langsung ke kolom: diunggah lalu disisipkan sebagai penanda ⟦img:ID⟧ di posisi kursor. */
async function onPaste(e: ClipboardEvent, field: string): Promise<void> {
    const data = e.clipboardData;
    const files = Array.from(data?.files ?? []).filter((f) => f.type.startsWith('image/'));

    // Teks biasa tetap ditempel seperti biasa; hanya tempelan gambar murni yang dicegat.
    if (files.length === 0 || (data?.getData('text/plain') ?? '').trim() !== '') return;

    e.preventDefault();
    const el = e.target as HTMLInputElement | HTMLTextAreaElement;
    const start = el.selectionStart ?? el.value.length;
    const end = el.selectionEnd ?? start;
    pasteError.value = '';
    pasting.value = true;
    let tokens = '';

    try {
        for (const file of files) {
            const body = new FormData();
            body.append('image', file, file.name || 'tempel.png');
            const res = await fetch(`/teacher/items/${props.item.id}/images`, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: { 'X-XSRF-TOKEN': csrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = (await res.json().catch(() => ({}))) as { id?: number; url?: string; message?: string };

            if (!res.ok || !json.id || !json.url) {
                pasteError.value = json.message ?? 'Gambar tidak dapat diunggah.';
                break;
            }

            imageMap.value = { ...imageMap.value, [String(json.id)]: json.url };
            tokens += `⟦img:${json.id}⟧`;
        }
    } catch {
        pasteError.value = 'Gambar tidak dapat diunggah. Periksa koneksi lalu coba lagi.';
    } finally {
        pasting.value = false;
    }

    if (tokens === '') return;

    const apply = (v: string): string => v.slice(0, start) + tokens + v.slice(end);

    if (field === 'stem') form.stem = apply(form.stem);
    else if (field === 'scoring_guide') form.scoring_guide = apply(form.scoring_guide);
    else form.options[field] = apply(form.options[field] ?? '');
}

const form = useForm({
    type: props.item.type,
    stem: props.item.stem,
    options: Object.fromEntries(LETTERS.map((l) => [l, props.item.options[l] ?? ''])) as Record<string, string>,
    answer_key: props.item.answer_key,
    scoring_guide: props.item.scoring_guide,
    blueprint: {
        cp: props.item.blueprint.cp,
        materi: props.item.blueprint.materi,
        submateri: props.item.blueprint.submateri,
        indikator: props.item.blueprint.indikator,
        level: props.item.blueprint.level,
        bobot: props.item.blueprint.bobot as number | null,
    },
    attachment: null as File | null,
});

const matching = computed(() => {
    if (form.type !== 'menjodohkan') return null;
    const pairs = new Set<string>();
    const values: string[] = [];
    const distractors: string[] = [];
    for (const raw of form.answer_key.split(/\r?\n/)) {
        const line = raw.trim();
        const d = /^(?:pilihan\s+)?pengecoh\s*[:：]\s*(.*)$/i.exec(line);
        if (d) {
            distractors.push(...d[1].split(';').map((x) => x.trim()).filter(Boolean));
            continue;
        }
        for (const part of line.split(';')) {
            const m = /^([A-Ea-e])\s*[=:]\s*(.+)$/s.exec(part.trim());
            if (m) {
                pairs.add(m[1].toUpperCase());
                values.push(m[2].trim());
            }
        }
    }
    const statements = Object.values(form.options).filter((t) => String(t ?? '').trim() !== '').length;
    return { statements, choices: new Set([...values, ...distractors]).size, pairs: pairs.size };
});

const hasOptions = computed(() => props.types.find((t) => t.value === form.type)?.has_options ?? false);
const keyHint = computed(() => (form.type === 'benar_salah' ? 'B (Benar) atau S (Salah); bila ada beberapa pernyataan: A=B; B=S; C=B' : form.type === 'menjodohkan' ? 'Baris 1: pasangan benar, mis. A=Surabaya; B=Bandung. Baris 2: Pengecoh: Malang; Medan (pilihan jawaban tambahan)' : form.type === 'pg_kompleks' ? 'Beberapa huruf dipisah koma, mis. A,C' : hasOptions.value ? 'Satu huruf, mis. B' : 'Jawaban singkat / kata kunci'));

function err(key: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[key];
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => emit('done') };

    if (props.mode === 'draft') {
        form
            .transform(({ attachment: _omit, ...rest }) => rest)
            .put(`/teacher/items/${props.item.id}`, options);
    } else {
        form.transform((data) => data).post(`/teacher/items/${props.item.id}/revision`, { ...options, forceFormData: true });
    }
}

function pickFile(event: Event): void {
    form.attachment = (event.target as HTMLInputElement).files?.[0] ?? null;
}
</script>

<template>
    <form :id="`item-form-${item.id}`" class="space-y-5" @submit.prevent="submit">
        <p v-if="mode === 'revise' && note" class="rounded-xl bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-500" role="note">
            <span class="font-bold">Catatan validator:</span> {{ note }}
        </p>

        <fieldset class="space-y-4">
            <legend class="text-sm font-bold text-ink">Kisi-kisi</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField v-slot="{ id, invalid }" label="Capaian pembelajaran" :error="err('blueprint.cp')">
                    <input :id="id" v-model="form.blueprint.cp" class="input" :class="{ 'input-error': invalid }" />
                </FormField>
                <FormField v-slot="{ id, invalid }" label="Materi" :error="err('blueprint.materi')">
                    <input :id="id" v-model="form.blueprint.materi" class="input" :class="{ 'input-error': invalid }" />
                </FormField>
                <FormField v-slot="{ id, invalid }" label="Submateri" :error="err('blueprint.submateri')">
                    <input :id="id" v-model="form.blueprint.submateri" class="input" :class="{ 'input-error': invalid }" />
                </FormField>
            </div>
            <FormField v-slot="{ id, invalid }" label="Indikator soal" :error="err('blueprint.indikator')" required>
                <textarea :id="id" v-model="form.blueprint.indikator" rows="2" class="input" :class="{ 'input-error': invalid }" />
            </FormField>
            <div class="grid gap-4 sm:grid-cols-3">
                <FormField v-slot="{ id, invalid }" label="Level kognitif" :error="err('blueprint.level')">
                    <input :id="id" v-model="form.blueprint.level" list="levels" class="input" :class="{ 'input-error': invalid }" placeholder="mis. C3 Aplikasi" />
                    <datalist id="levels"><option v-for="l in ['C1', 'C2', 'C3', 'C4', 'C5', 'C6', 'LOTS', 'HOTS']" :key="l" :value="l" /></datalist>
                </FormField>
                <FormField v-slot="{ id, invalid }" label="Bentuk soal" :error="form.errors.type" required>
                    <select :id="id" v-model="form.type" class="input" :class="{ 'input-error': invalid }">
                        <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                </FormField>
                <FormField v-slot="{ id, invalid }" label="Bobot" :error="err('blueprint.bobot')">
                    <input :id="id" v-model.number="form.blueprint.bobot" type="number" min="0" step="0.5" class="input" :class="{ 'input-error': invalid }" />
                </FormField>
            </div>
        </fieldset>

        <fieldset class="space-y-4">
            <legend class="text-sm font-bold text-ink">Soal</legend>
            <FormField v-slot="{ id, invalid }" label="Teks soal" :error="form.errors.stem" hint="Gambar: salin (Ctrl+V) lalu tempel langsung di kolom ini, termasuk pada pilihan jawaban. Rumus ditulis di antara \\( dan \\) dengan sintaks LaTeX, mis. \\(\\frac{15}{56}\\). Penanda ⟦img:…⟧ adalah gambar; hapus penanda untuk membuang gambar dari teks." required>
                <textarea :id="id" v-model="form.stem" rows="5" class="input font-serif" :class="{ 'input-error': invalid }" @paste="onPaste($event, 'stem')" />
            </FormField>
            <div v-if="/\\\(|⟦img:/.test(form.stem)" class="rounded-xl border border-dashed border-line bg-surface-2 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Pratinjau</p>
                <p class="mt-1 font-serif text-ink"><MathText :text="form.stem" :images="imageMap" /></p>
            </div>

            <div v-if="hasOptions || form.type === 'benar_salah'" class="space-y-2">
                <p class="label">{{ form.type === 'benar_salah' ? 'Pernyataan (opsional, bila ada beberapa pernyataan Benar/Salah)' : form.type === 'menjodohkan' ? 'Pernyataan' : 'Pilihan jawaban' }}</p>
                <div v-for="letter in LETTERS" :key="letter" class="flex items-center gap-2">
                    <span class="w-6 text-center text-sm font-bold text-primary-700 dark:text-primary-300">{{ letter }}</span>
                    <input v-model="form.options[letter]" class="input font-serif" :aria-label="`Pilihan ${letter}`" @paste="onPaste($event, letter)" />
                </div>
                <p v-if="form.errors.options" class="error-text" role="alert">{{ form.errors.options }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <FormField v-slot="{ id, invalid }" :label="hasOptions ? 'Kunci jawaban' : 'Kunci / jawaban singkat'" :error="form.errors.answer_key" :hint="keyHint" :required="hasOptions">
                    <textarea v-if="form.type === 'menjodohkan'" :id="id" v-model="form.answer_key" rows="3" class="input" :class="{ 'input-error': invalid }" />
                    <input v-else :id="id" v-model="form.answer_key" class="input" :class="{ 'input-error': invalid }" />
                </FormField>
                <p v-if="matching" class="self-end rounded-xl px-3 py-2 text-xs font-semibold" :class="matching.choices > matching.statements ? 'bg-success-50 text-success-700 dark:bg-success-500/10' : 'bg-warning-50 text-warning-700 dark:bg-warning-500/10'" role="status">
                    {{ matching.statements }} pernyataan · {{ matching.choices }} pilihan jawaban<template v-if="matching.choices <= matching.statements"> — tambahkan minimal 1 pengecoh agar pilihan lebih banyak daripada pernyataan.</template>
                </p>
            </div>
            <FormField v-slot="{ id, invalid }" label="Pedoman penskoran" :error="form.errors.scoring_guide" :hint="hasOptions ? 'Opsional untuk pilihan ganda.' : 'Wajib untuk uraian/isian bila kunci kosong.'">
                <textarea :id="id" v-model="form.scoring_guide" rows="3" class="input" :class="{ 'input-error': invalid }" @paste="onPaste($event, 'scoring_guide')" />
            </FormField>
        </fieldset>

        <div v-if="mode === 'revise'">
            <label class="label" for="item-attachment">Lampiran (opsional)</label>
            <label for="item-attachment" class="flex cursor-pointer items-center gap-2 rounded-xl border border-dashed border-line px-4 py-3 text-sm text-ink-soft hover:bg-surface-2">
                <Paperclip class="size-4" /> {{ form.attachment ? form.attachment.name : 'Pilih berkas (docx, xlsx, pdf, png, jpg)' }}
            </label>
            <input id="item-attachment" type="file" class="sr-only" accept=".docx,.xlsx,.pdf,.png,.jpg,.jpeg" @change="pickFile" />
            <p v-if="form.errors.attachment" class="error-text" role="alert">{{ form.errors.attachment }}</p>
        </div>

        <div class="flex justify-end gap-2 border-t border-line pt-4">
            <button type="button" class="btn-secondary" @click="emit('cancel')">Batal</button>
            <button type="submit" class="btn-primary" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                {{ mode === 'draft' ? 'Simpan nomor' : 'Unggah ulang nomor' }}
            </button>
        </div>
    </form>
</template>
