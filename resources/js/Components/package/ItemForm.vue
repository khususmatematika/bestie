<script setup lang="ts">
import { vAutogrow } from '@/Composables/autogrow';
import { buildMatchingKey, parseMatchingKey, parseTrueFalseKey, splitKey, type TF } from '@/Composables/matchingKey';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Plus, X } from '@lucide/vue';
import FilePicker from '@/Components/ui/FilePicker.vue';
import { computed, nextTick, reactive, ref, watch } from 'vue';
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

    setField(field, apply(getField(field)));
}

/** Format teks: bungkus pilihan dengan <b>, <i>, atau <u> (Ctrl+B / Ctrl+I / Ctrl+U atau tombol di atas kolom). */
function getField(name: string): string {
    if (name === 'stem') return form.stem;
    if (name === 'scoring_guide') return form.scoring_guide;
    if (name === 'answer_key') return form.answer_key;
    if (name.startsWith('ans:')) return pairAns[name.slice(4)] ?? '';
    if (name.startsWith('dis:')) return distractors.value[Number(name.slice(4))] ?? '';

    return form.options[name] ?? '';
}

function setField(name: string, value: string): void {
    if (name === 'stem') form.stem = value;
    else if (name === 'scoring_guide') form.scoring_guide = value;
    else if (name === 'answer_key') form.answer_key = value;
    else if (name.startsWith('ans:')) pairAns[name.slice(4)] = value;
    else if (name.startsWith('dis:')) distractors.value[Number(name.slice(4))] = value;
    else form.options[name] = value;
}

const activeField = ref<string | null>(null);

function trackFocus(e: FocusEvent): void {
    const name = (e.target as HTMLElement | null)?.getAttribute?.('data-fmt');
    if (name) activeField.value = name;
}

function applyFormat(tag: 'b' | 'i' | 'u' | 'sub' | 'sup'): void {
    const name = activeField.value;
    const el = name ? (document.querySelector(`[data-fmt="${name}"]`) as HTMLInputElement | HTMLTextAreaElement | null) : null;
    if (!name || !el) return;

    const start = el.selectionStart ?? 0;
    const end = el.selectionEnd ?? start;
    const value = getField(name);
    const open = `<${tag}>`;
    const close = `</${tag}>`;
    const picked = value.slice(start, end);
    const already = picked.startsWith(open) && picked.endsWith(close);
    const inner = already ? picked.slice(open.length, picked.length - close.length) : picked;
    const next = already ? inner : open + picked + close;

    setField(name, value.slice(0, start) + next + value.slice(end));
    nextTick(() => {
        el.focus();
        const caret = picked === '' && !already ? start + open.length : start;
        el.setSelectionRange(caret, picked === '' && !already ? caret : start + next.length);
    });
}

function onFormatKey(e: KeyboardEvent): void {
    if (!(e.ctrlKey || e.metaKey) || e.altKey) return;
    if (!(e.target as HTMLElement | null)?.getAttribute?.('data-fmt')) return;

    // Seperti Word: Ctrl+= subscript, Ctrl+Shift+= superscript; Ctrl+B/I/U tebal/miring/garis bawah.
    let tag: 'b' | 'i' | 'u' | 'sub' | 'sup' | null = null;
    const k = e.key.toLowerCase();

    if (!e.shiftKey && (k === 'b' || k === 'i' || k === 'u')) tag = k;
    else if (k === '=' && !e.shiftKey) tag = 'sub';
    else if (k === '+' || (k === '=' && e.shiftKey)) tag = 'sup';

    if (tag === null) return;
    e.preventDefault();
    activeField.value = (e.target as HTMLElement).getAttribute('data-fmt');
    applyFormat(tag);
}

// Palet rumus: "|" menandai posisi kursor. Di luar \( \) rumus otomatis dibungkus \( \).
const showMath = ref(false);
const MATH_TOOLS: { label: string; tex: string; title: string }[] = [
    { label: 'a/b', tex: '\\frac{|}{}', title: 'Pecahan' },
    { label: '√', tex: '\\sqrt{|}', title: 'Akar kuadrat' },
    { label: 'ⁿ√', tex: '\\sqrt[|]{}', title: 'Akar pangkat n' },
    { label: 'xⁿ', tex: '^{|}', title: 'Pangkat' },
    { label: 'xₙ', tex: '_{|}', title: 'Indeks' },
    { label: '∑', tex: '\\sum_{|}^{}', title: 'Sigma' },
    { label: '∫', tex: '\\int_{|}^{}', title: 'Integral' },
    { label: 'lim', tex: '\\lim_{x \\to |}', title: 'Limit' },
    { label: 'log', tex: '\\log_{|}', title: 'Logaritma' },
    { label: 'sin', tex: '\\sin |', title: 'Sinus' },
    { label: 'cos', tex: '\\cos |', title: 'Cosinus' },
    { label: 'tan', tex: '\\tan |', title: 'Tangen' },
    { label: '°', tex: '^{\\circ}|', title: 'Derajat' },
    { label: '±', tex: '\\pm |', title: 'Plus minus' },
    { label: '×', tex: '\\times |', title: 'Kali' },
    { label: '÷', tex: '\\div |', title: 'Bagi' },
    { label: '≤', tex: '\\leq |', title: 'Kurang dari sama dengan' },
    { label: '≥', tex: '\\geq |', title: 'Lebih dari sama dengan' },
    { label: '≠', tex: '\\neq |', title: 'Tidak sama dengan' },
    { label: '≈', tex: '\\approx |', title: 'Hampir sama dengan' },
    { label: 'π', tex: '\\pi |', title: 'Pi' },
    { label: 'θ', tex: '\\theta |', title: 'Theta' },
    { label: 'α', tex: '\\alpha |', title: 'Alfa' },
    { label: 'β', tex: '\\beta |', title: 'Beta' },
    { label: '∞', tex: '\\infty |', title: 'Tak hingga' },
    { label: '→', tex: '\\rightarrow |', title: 'Panah' },
    { label: '( )', tex: '\\left(|\\right)', title: 'Kurung besar' },
    { label: 'x̄', tex: '\\overline{|}', title: 'Garis atas' },
];

function insertMath(tex: string): void {
    const name = activeField.value ?? 'stem';
    const el = document.querySelector(`[data-fmt="${name}"]`) as HTMLInputElement | HTMLTextAreaElement | null;
    if (!el) return;

    const start = el.selectionStart ?? el.value.length;
    const end = el.selectionEnd ?? start;
    const value = getField(name);
    const before = value.slice(0, start);
    const inside = (before.match(/\\\(/g)?.length ?? 0) > (before.match(/\\\)/g)?.length ?? 0);
    const picked = value.slice(start, end);
    const body = tex.includes('|') ? tex.replace('|', picked) : tex;
    const snippet = inside ? body : `\\(${body}\\)`;
    const caretInSnippet = (inside ? 0 : 2) + (tex.includes('|') ? tex.indexOf('|') + picked.length : body.length);

    setField(name, before + snippet + value.slice(end));
    void nextTick(() => {
        el.focus();
        el.setSelectionRange(start + caretInSnippet, start + caretInSnippet);
    });
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

// Menjodohkan & benar/salah: kunci disusun otomatis dari isian per baris (tidak perlu mengetik "A=...; B=...").
const pairAns = reactive<Record<string, string>>({});
const distractors = ref<string[]>([]);
const tf = reactive<Record<string, TF>>({});
const tfSingle = ref<TF>('');
const rowsShown = ref(3);

function loadKeyState(): void {
    if (form.type === 'menjodohkan') {
        const p = parseMatchingKey(form.answer_key);
        for (const l of LETTERS) pairAns[l] = p.pairs[l] ?? '';
        distractors.value = [...p.distractors];
    } else if (form.type === 'benar_salah') {
        const p = parseTrueFalseKey(form.answer_key);
        for (const l of LETTERS) tf[l] = p.map[l] ?? '';
        tfSingle.value = p.single;
    }

    let last = -1;
    LETTERS.forEach((l, i) => {
        if ((form.options[l] ?? '').trim() !== '' || (pairAns[l] ?? '').trim() !== '') last = i;
    });
    rowsShown.value = Math.min(LETTERS.length, Math.max(3, last + 2));
}

loadKeyState();
watch(() => form.type, loadKeyState);
watch([pairAns, distractors], () => {
    if (form.type === 'menjodohkan') form.answer_key = buildMatchingKey({ ...pairAns }, distractors.value);
}, { deep: true });

const tfHasStatements = computed(() => LETTERS.some((l) => (form.options[l] ?? '').trim() !== ''));

function syncTf(): void {
    if (form.type !== 'benar_salah') return;
    form.answer_key = tfHasStatements.value
        ? LETTERS.filter((l) => (form.options[l] ?? '').trim() !== '' && tf[l]).map((l) => `${l}=${tf[l]}`).join('; ')
        : tfSingle.value;
}

watch([tf, tfSingle, () => form.options], syncTf, { deep: true });

function addDistractor(): void {
    distractors.value.push('');
}

function removeDistractor(i: number): void {
    distractors.value.splice(i, 1);
}

const matching = computed(() => {
    if (form.type !== 'menjodohkan') return null;
    const pairs = new Set<string>();
    const values: string[] = [];
    const distractors: string[] = [];
    for (const raw of form.answer_key.split(/\r?\n/)) {
        const line = raw.trim();
        const d = /^(?:pilihan\s+)?pengecoh\s*[:：]\s*(.*)$/i.exec(line);
        if (d) {
            distractors.push(...splitKey(d[1]));
            continue;
        }
        for (const part of splitKey(line)) {
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

</script>

<template>
    <form :id="`item-form-${item.id}`" class="space-y-5" @submit.prevent="submit" @focusin="trackFocus" @keydown="onFormatKey">
        <p v-if="mode === 'revise' && note" class="rounded-xl bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-500" role="note">
            <span class="font-bold">Catatan validator:</span> {{ note }}
        </p>

        <fieldset class="space-y-4">
            <legend class="text-sm font-bold text-ink">Kisi-kisi</legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                <textarea :id="id" v-model="form.blueprint.indikator" v-autogrow rows="2" class="input" :class="{ 'input-error': invalid }" />
            </FormField>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
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
            <div class="flex flex-wrap items-center gap-1.5" role="toolbar" aria-label="Format teks">
                <button type="button" class="btn-secondary px-2.5 py-1 text-sm font-bold" title="Tebal (Ctrl+B)" @mousedown.prevent @click="applyFormat('b')">B</button>
                <button type="button" class="btn-secondary px-2.5 py-1 text-sm italic" title="Miring (Ctrl+I)" @mousedown.prevent @click="applyFormat('i')">I</button>
                <button type="button" class="btn-secondary px-2.5 py-1 text-sm underline" title="Garis bawah (Ctrl+U)" @mousedown.prevent @click="applyFormat('u')">U</button>
                <button type="button" class="btn-secondary px-2.5 py-1 text-sm" title="Subskrip (Ctrl+=)" @mousedown.prevent @click="applyFormat('sub')">x<sub>2</sub></button>
                <button type="button" class="btn-secondary px-2.5 py-1 text-sm" title="Superskrip (Ctrl+Shift+=)" @mousedown.prevent @click="applyFormat('sup')">x<sup>2</sup></button>
                <button type="button" class="btn-secondary px-2.5 py-1 text-sm font-semibold" :aria-expanded="showMath" title="Sisipkan rumus matematika" @mousedown.prevent @click="showMath = !showMath">∑ Rumus</button>
                <span class="text-xs text-ink-faint">Blok teks pada kolom soal, pilihan, atau kunci, lalu klik tombol atau tekan Ctrl+B / I / U.</span>
            </div>
            <div v-if="showMath" class="flex flex-wrap gap-1.5 rounded-xl border border-line bg-surface-2 p-2" role="toolbar" aria-label="Rumus matematika">
                <button v-for="t in MATH_TOOLS" :key="t.title" type="button" class="btn-secondary min-w-9 px-2 py-1 text-sm" :title="t.title" @mousedown.prevent @click="insertMath(t.tex)">{{ t.label }}</button>
                <p class="w-full text-xs text-ink-faint">Klik kolom yang ingin diisi, lalu pilih simbol; rumus ditulis di antara \( dan \) dan tampil pada pratinjau. Rumus dari Word (Equation) otomatis terbaca.</p>
            </div>
            <FormField v-slot="{ id, invalid }" :label="form.type === 'menjodohkan' ? 'Stimulus / petunjuk soal' : 'Teks soal'" :error="form.errors.stem" hint="Gambar: salin (Ctrl+V) lalu tempel langsung di kolom ini, termasuk pada pilihan jawaban, pernyataan menjodohkan, dan kunci/pengecoh. Tebal/miring/garis bawah: blok teks lalu Ctrl+B / I / U. Rumus ditulis di antara \\( dan \\) dengan sintaks LaTeX, mis. \\(\\frac{15}{56}\\). Penanda ⟦(?:img|rb):…⟧ adalah gambar; hapus penanda untuk membuang gambar dari teks." required>
                <textarea :id="id" v-model="form.stem" v-autogrow data-fmt="stem" rows="5" class="input font-serif" :class="{ 'input-error': invalid }" @paste="onPaste($event, 'stem')" />
            </FormField>
            <div v-if="/\\\(|⟦(?:img|rb):|<(?:b|i|u|sub|sup)>/.test(form.stem)" class="rounded-xl border border-dashed border-line bg-surface-2 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Pratinjau</p>
                <p class="mt-1 font-serif text-ink"><MathText :text="form.stem" :images="imageMap" /></p>
            </div>

            <!-- Pilihan ganda / PG kompleks: satu kotak per pilihan -->
            <div v-if="hasOptions && form.type !== 'menjodohkan'" class="space-y-2">
                <p class="label">Pilihan jawaban</p>
                <div v-for="letter in LETTERS" :key="letter" class="flex items-start gap-2">
                    <span class="w-6 pt-2.5 text-center text-sm font-bold text-primary-700 dark:text-primary-300">{{ letter }}</span>
                    <textarea v-model="form.options[letter]" v-autogrow :data-fmt="letter" rows="1" class="input min-w-0 flex-1 font-serif" :aria-label="`Pilihan ${letter}`" @paste="onPaste($event, letter)" />
                </div>
                <p v-if="form.errors.options" class="error-text" role="alert">{{ form.errors.options }}</p>
                <div v-if="LETTERS.some((l) => /⟦(?:img|rb):|<(?:b|i|u|sub|sup)>|\\\(/.test(form.options[l] ?? ''))" class="rounded-xl border border-dashed border-line bg-surface-2 p-3 text-sm text-ink">
                    <template v-for="letter in LETTERS" :key="letter">
                        <p v-if="(form.options[letter] ?? '') !== ''" class="font-serif"><b class="mr-1 font-sans">{{ letter }}.</b><MathText :text="form.options[letter]" :images="imageMap" /></p>
                    </template>
                </div>
            </div>

            <!-- Benar/Salah: tiap pernyataan punya kotak sendiri dan tombol Benar / Salah -->
            <div v-else-if="form.type === 'benar_salah'" class="space-y-3">
                <p class="label">Pernyataan <span class="font-normal text-ink-faint">(opsional — kosongkan bila soal hanya satu pernyataan)</span></p>
                <div v-for="(letter, i) in LETTERS.slice(0, rowsShown)" :key="letter" class="rounded-xl border border-line bg-surface p-3">
                    <div class="flex items-start gap-2">
                        <span class="mt-2 grid size-6 shrink-0 place-items-center rounded-full bg-primary-50 text-xs font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">{{ i + 1 }}</span>
                        <textarea v-model="form.options[letter]" v-autogrow :data-fmt="letter" rows="1" class="input min-w-0 flex-1 font-serif" :aria-label="`Pernyataan ${i + 1}`" placeholder="Tulis pernyataan…" @paste="onPaste($event, letter)" />
                    </div>
                    <div v-if="(form.options[letter] ?? '').trim() !== ''" class="mt-2 flex items-center gap-2 pl-8" role="group" :aria-label="`Kunci pernyataan ${i + 1}`">
                        <span class="text-xs font-semibold text-ink-soft">Kunci:</span>
                        <button type="button" class="btn-sm rounded-lg border px-3 py-1 text-sm font-semibold" :class="tf[letter] === 'B' ? 'border-success-700 bg-success-700 text-white' : 'border-line bg-surface text-ink-soft hover:bg-surface-2'" :aria-pressed="tf[letter] === 'B'" @click="tf[letter] = 'B'">Benar</button>
                        <button type="button" class="btn-sm rounded-lg border px-3 py-1 text-sm font-semibold" :class="tf[letter] === 'S' ? 'border-danger-700 bg-danger-700 text-white' : 'border-line bg-surface text-ink-soft hover:bg-surface-2'" :aria-pressed="tf[letter] === 'S'" @click="tf[letter] = 'S'">Salah</button>
                    </div>
                </div>
                <button v-if="rowsShown < LETTERS.length" type="button" class="btn-secondary btn-sm" @click="rowsShown++"><Plus class="size-4" /> Tambah pernyataan</button>
                <div v-if="!tfHasStatements" class="flex flex-wrap items-center gap-2 rounded-xl border border-line bg-surface-2 p-3" role="group" aria-label="Kunci jawaban">
                    <span class="text-sm font-semibold text-ink">Kunci jawaban:</span>
                    <button type="button" class="rounded-lg border px-4 py-1.5 text-sm font-semibold" :class="tfSingle === 'B' ? 'border-success-700 bg-success-700 text-white' : 'border-line bg-surface text-ink-soft hover:bg-surface-2'" :aria-pressed="tfSingle === 'B'" @click="tfSingle = 'B'">Benar</button>
                    <button type="button" class="rounded-lg border px-4 py-1.5 text-sm font-semibold" :class="tfSingle === 'S' ? 'border-danger-700 bg-danger-700 text-white' : 'border-line bg-surface text-ink-soft hover:bg-surface-2'" :aria-pressed="tfSingle === 'S'" @click="tfSingle = 'S'">Salah</button>
                </div>
                <p v-if="form.errors.options" class="error-text" role="alert">{{ form.errors.options }}</p>
                <p v-if="form.errors.answer_key" class="error-text" role="alert">{{ form.errors.answer_key }}</p>
            </div>

            <!-- Menjodohkan: stimulus di atas, lalu pertanyaan 1 → jawaban 1 → pertanyaan 2 → jawaban 2 …, masing-masing punya kotak sendiri -->
            <div v-else-if="form.type === 'menjodohkan'" class="space-y-3">
                <p class="label">Pasangan pertanyaan dan jawaban</p>
                <div v-for="(letter, i) in LETTERS.slice(0, rowsShown)" :key="letter" class="space-y-2 rounded-xl border border-line bg-surface p-3">
                    <label class="block">
                        <span class="mb-1 flex items-center gap-1.5 text-xs font-bold text-primary-700 dark:text-primary-300"><span class="grid size-5 place-items-center rounded-full bg-primary-50 text-[11px] dark:bg-primary-500/15">{{ i + 1 }}</span> Pertanyaan {{ i + 1 }}</span>
                        <textarea v-model="form.options[letter]" v-autogrow :data-fmt="letter" rows="1" class="input font-serif" :aria-label="`Pertanyaan ${i + 1}`" @paste="onPaste($event, letter)" />
                    </label>
                    <label class="block">
                        <span class="mb-1 flex items-center gap-1.5 text-xs font-bold text-success-700"><span class="grid size-5 place-items-center rounded-full bg-success-50 text-[11px] dark:bg-success-500/10">{{ i + 1 }}</span> Jawaban {{ i + 1 }}</span>
                        <textarea v-model="pairAns[letter]" v-autogrow :data-fmt="`ans:${letter}`" rows="1" class="input font-serif" :aria-label="`Jawaban ${i + 1}`" @paste="onPaste($event, `ans:${letter}`)" />
                    </label>
                </div>
                <button v-if="rowsShown < LETTERS.length" type="button" class="btn-secondary btn-sm" @click="rowsShown++"><Plus class="size-4" /> Tambah pasangan</button>

                <div class="space-y-2 rounded-xl border border-dashed border-line bg-surface-2 p-3">
                    <p class="text-xs font-bold text-ink-soft">Pengecoh <span class="font-normal text-ink-faint">(jawaban tambahan yang tidak dipakai; minimal 1 agar pilihan lebih banyak daripada pertanyaan)</span></p>
                    <div v-for="(_, i) in distractors" :key="i" class="flex items-start gap-2">
                        <textarea v-model="distractors[i]" v-autogrow :data-fmt="`dis:${i}`" rows="1" class="input min-w-0 flex-1 font-serif" :aria-label="`Pengecoh ${i + 1}`" @paste="onPaste($event, `dis:${i}`)" />
                        <button type="button" class="btn-secondary btn-sm mt-1" :aria-label="`Hapus pengecoh ${i + 1}`" @click="removeDistractor(i)"><X class="size-4" /></button>
                    </div>
                    <button type="button" class="btn-secondary btn-sm" @click="addDistractor"><Plus class="size-4" /> Tambah pengecoh</button>
                </div>

                <p v-if="form.errors.options" class="error-text" role="alert">{{ form.errors.options }}</p>
                <p v-if="form.errors.answer_key" class="error-text" role="alert">{{ form.errors.answer_key }}</p>
                <div v-if="LETTERS.some((l) => /⟦(?:img|rb):|<(?:b|i|u|sub|sup)>|\\\(/.test((form.options[l] ?? '') + (pairAns[l] ?? '')))" class="space-y-1 rounded-xl border border-dashed border-line bg-surface-2 p-3 text-sm text-ink">
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Pratinjau</p>
                    <template v-for="(letter, i) in LETTERS.slice(0, rowsShown)" :key="letter">
                        <p v-if="(form.options[letter] ?? '') !== '' || (pairAns[letter] ?? '') !== ''" class="font-serif"><b class="mr-1 font-sans">{{ i + 1 }}.</b><MathText :text="form.options[letter] ?? ''" :images="imageMap" /> <span class="text-ink-faint">→</span> <MathText :text="pairAns[letter] ?? ''" :images="imageMap" /></p>
                    </template>
                </div>
                <p v-if="matching" class="rounded-xl px-3 py-2 text-xs font-semibold" :class="matching.choices > matching.statements ? 'bg-success-50 text-success-700 dark:bg-success-500/10' : 'bg-warning-50 text-warning-700 dark:bg-warning-500/10'" role="status">
                    {{ matching.statements }} pertanyaan · {{ matching.choices }} pilihan jawaban<template v-if="matching.choices <= matching.statements"> — tambahkan minimal 1 pengecoh agar pilihan lebih banyak daripada pertanyaan.</template>
                </p>
            </div>

            <!-- Kunci untuk bentuk lain (benar/salah & menjodohkan disusun otomatis di atas) -->
            <FormField v-if="form.type !== 'benar_salah' && form.type !== 'menjodohkan'" v-slot="{ id, invalid }" :label="hasOptions ? 'Kunci jawaban' : 'Kunci / jawaban singkat'" :error="form.errors.answer_key" :hint="keyHint" :required="hasOptions">
                <textarea :id="id" v-model="form.answer_key" v-autogrow rows="1" class="input" :class="{ 'input-error': invalid }" />
            </FormField>
            <FormField v-slot="{ id, invalid }" label="Pedoman penskoran" :error="form.errors.scoring_guide" :hint="hasOptions ? 'Opsional untuk pilihan ganda.' : 'Wajib untuk uraian/isian bila kunci kosong.'">
                <textarea :id="id" v-model="form.scoring_guide" v-autogrow data-fmt="scoring_guide" rows="3" class="input" :class="{ 'input-error': invalid }" @paste="onPaste($event, 'scoring_guide')" />
            </FormField>
        </fieldset>

        <div v-if="mode === 'revise'">
            <label class="label" for="item-attachment">Lampiran (opsional)</label>
            <FilePicker id="item-attachment" v-model="form.attachment" accept=".docx,.xlsx,.pdf,.png,.jpg,.jpeg" label="Pilih lampiran" hint="docx, xlsx, pdf, png, jpg" :invalid="!!form.errors.attachment" />
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
