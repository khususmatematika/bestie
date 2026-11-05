<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Download, LoaderCircle, Upload } from '@lucide/vue';
import { formatDate } from '@/Composables/useFormat';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FilePicker from '@/Components/ui/FilePicker.vue';
import FormField from '@/Components/ui/FormField.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps<{
    assignments: { id: number; subject: string; code: string; grade: string }[];
    preselected: number | null;
    period: { id: number; label: string; open: boolean; upload_deadline: string | null } | null;
    max_mb: number;
}>();

const form = useForm<{ assignment_id: number | ''; kisi_kisi: File | null; naskah: File | null }>({
    assignment_id: props.assignments.some((a) => a.id === props.preselected) ? (props.preselected as number) : '',
    kisi_kisi: null,
    naskah: null,
});

function submit(): void {
    form.post('/teacher/packages', { forceFormData: true });
}
</script>

<template>
    <Head title="Buat Paket Soal" />

    <Link href="/teacher/packages" class="mb-3 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-soft hover:text-primary-600">
        <ArrowLeft class="size-4" /> Kembali ke paket saya
    </Link>

    <PageHeader title="Buat paket soal" :description="period ? `Periode: ${period.label}${period.upload_deadline ? ' · tenggat unggah ' + formatDate(period.upload_deadline) : ''}` : undefined" />

    <section v-if="!period || !period.open" class="card">
        <EmptyState title="Periode tidak menerima paket baru" description="Pilih periode asesmen yang masih dibuka pada pemilih periode di bilah atas, atau hubungi panitia asesmen." />
    </section>

    <section v-else-if="!assignments.length" class="card">
        <EmptyState title="Tidak ada mapel yang tersisa" description="Semua mapel yang Anda ampu pada semester ini sudah memiliki paket, atau admin belum menautkan Anda ke mapel." />
    </section>

    <form v-else class="grid gap-6 lg:grid-cols-5" @submit.prevent="submit">
        <div class="space-y-6 lg:col-span-3">
            <section class="card p-6">
                <h2 class="text-base font-bold text-ink">1. Pilih mapel &amp; tingkat</h2>
                <fieldset class="mt-4">
                    <legend class="sr-only">Mapel dan tingkat</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="a in assignments"
                            :key="a.id"
                            class="flex cursor-pointer items-center gap-3 rounded-xl border border-line px-4 py-3 has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 dark:has-[:checked]:bg-primary-500/10"
                        >
                            <input v-model="form.assignment_id" type="radio" :value="a.id" class="size-4 accent-primary-600" />
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-bold text-ink">{{ a.subject }}</span>
                                <span class="block text-xs text-ink-faint">Kelas {{ a.grade }} · {{ a.code }}</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="form.errors.assignment_id" class="error-text" role="alert">{{ form.errors.assignment_id }}</p>
                </fieldset>

            </section>

            <section class="card p-6">
                <h2 class="text-base font-bold text-ink">2. Unggah berkas (opsional sekarang)</h2>
                <p class="mt-1 text-sm text-ink-soft">Berkas diekstrak per nomor di latar belakang. Anda juga dapat membuat nomor secara manual di halaman paket.</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="label">Kisi-kisi <span class="font-normal text-ink-faint">(.docx atau .xlsx)</span></p>
                        <FilePicker v-model="form.kisi_kisi" accept=".xlsx,.docx,.pdf" label="Pilih berkas kisi-kisi" :invalid="!!form.errors.kisi_kisi" />
                        <p v-if="form.errors.kisi_kisi" class="error-text" role="alert">{{ form.errors.kisi_kisi }}</p>
                    </div>
                    <div>
                        <p class="label">Naskah soal <span class="font-normal text-ink-faint">(.docx atau .pdf)</span></p>
                        <FilePicker v-model="form.naskah" accept=".docx,.pdf" label="Pilih berkas naskah" :invalid="!!form.errors.naskah" />
                        <p v-if="form.errors.naskah" class="error-text" role="alert">{{ form.errors.naskah }}</p>
                    </div>
                </div>
                <p class="mt-3 text-xs text-ink-faint">Maks. {{ max_mb }} MB per berkas. Kisi-kisi: Excel, Word, atau PDF. Naskah: Word atau PDF. PDF harus berisi teks (bukan hasil pindaian). Hasil ekstraksi dari PDF perlu diperiksa.</p>
            </section>

            <button type="submit" class="btn-primary" :disabled="form.processing || form.assignment_id === ''">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                <Upload v-else class="size-4" />
                Buat paket
            </button>
        </div>

        <aside class="card h-fit p-6 lg:col-span-2">
            <h2 class="text-base font-bold text-ink">Template standar</h2>
            <p class="mt-1 text-sm text-ink-soft">Ekstraksi per nomor paling akurat memakai template berikut.</p>
            <div class="mt-4 flex flex-col gap-2">
                <a href="/templates/kisi-kisi-word" class="btn-secondary justify-start"><Download class="size-4" /> Template kisi-kisi (.docx)</a>
                <a href="/templates/kisi-kisi" class="btn-secondary justify-start"><Download class="size-4" /> Template kisi-kisi (.xlsx)</a>
                <a href="/templates/naskah" class="btn-secondary justify-start"><Download class="size-4" /> Template naskah soal (.docx)</a>
            </div>
            <ul class="mt-5 space-y-2 text-xs text-ink-soft">
                <li>• Naskah: judul bagian (Pilihan Ganda, Uraian, …), nomor &amp; pilihan memakai penomoran otomatis Word, <strong>kunci diberi warna merah</strong>. Rumus (Equation) dan gambar ikut terbaca.</li>
                <li>• Kisi-kisi: satu baris per nomor soal (CP, materi, submateri, indikator, No. Soal, level, bentuk).</li>
                <li>• Selengkapnya: <a href="/panduan" class="font-semibold text-primary-600 hover:underline">Panduan penggunaan</a>.</li>
            </ul>
        </aside>
    </form>
</template>
