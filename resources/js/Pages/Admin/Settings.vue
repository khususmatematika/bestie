<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import FormField from '@/Components/ui/FormField.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

interface ExportFormat {
    font: string;
    font_size: number;
    margin_cm: number;
    kop_top: string;
    kop_extra: string;
    show_logo: boolean;
    footer_text: string;
    doc_prefix: string;
    show_key_sheet: boolean;
    show_blueprint: boolean;
    show_recap: boolean;
}

const props = defineProps<{
    settings: { school_name: string; allow_self_validation: boolean; require_cross_validator_for_self: boolean; export_format: ExportFormat };
    fonts: string[];
    pdf_enabled: boolean;
}>();

const form = useForm({ ...props.settings });

function submit(): void {
    form.put('/admin/settings', { preserveScroll: true });
}
</script>

<template>
    <Head title="Pengaturan" />
    <PageHeader title="Pengaturan" description="Identitas sekolah dan kebijakan validasi." />

    <form class="card max-w-2xl space-y-6 p-6" @submit.prevent="submit">
        <FormField v-slot="{ id, invalid }" label="Nama sekolah" :error="form.errors.school_name" hint="Tampil di sidebar dan di kop naskah." required>
            <input :id="id" v-model="form.school_name" class="input" :class="{ 'input-error': invalid }" />
        </FormField>

        <fieldset class="space-y-4">
            <legend class="text-sm font-bold text-ink">Kebijakan validasi</legend>

            <label class="flex cursor-pointer items-start gap-3">
                <input v-model="form.allow_self_validation" type="checkbox" class="mt-1 size-4 accent-primary-600" />
                <span class="text-sm">
                    <span class="block font-semibold text-ink">Validator boleh memvalidasi soal sendiri</span>
                    <span class="text-ink-soft">Paket tersebut diberi label "Divalidasi Mandiri" pada semua tampilan dan dokumen.</span>
                </span>
            </label>

            <label class="flex cursor-pointer items-start gap-3" :class="{ 'opacity-50': !form.allow_self_validation }">
                <input v-model="form.require_cross_validator_for_self" type="checkbox" :disabled="!form.allow_self_validation" class="mt-1 size-4 accent-primary-600" />
                <span class="text-sm">
                    <span class="block font-semibold text-ink">Wajibkan validator silang untuk paket mandiri</span>
                    <span class="text-ink-soft">Paket validator tetap perlu ditelaah validator lain sebelum berstatus Fix.</span>
                </span>
            </label>
        </fieldset>

        <fieldset class="space-y-4 border-t border-line pt-6">
            <legend class="text-sm font-bold text-ink">Format naskah final</legend>
            <p class="text-sm text-ink-soft">Dipakai saat paket Fix dan naskah final dibuat otomatis. Hasil berupa DOCX{{ pdf_enabled ? ' dan PDF' : '' }}.</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <FormField v-slot="{ id, invalid }" label="Font" :error="form.errors['export_format.font']">
                    <select :id="id" v-model="form.export_format.font" class="input" :class="{ 'input-error': invalid }">
                        <option v-for="f in fonts" :key="f" :value="f">{{ f }}</option>
                    </select>
                </FormField>
                <FormField v-slot="{ id, invalid }" label="Ukuran font (pt)" :error="form.errors['export_format.font_size']">
                    <input :id="id" v-model.number="form.export_format.font_size" type="number" min="8" max="18" class="input" :class="{ 'input-error': invalid }" />
                </FormField>
                <FormField v-slot="{ id, invalid }" label="Margin (cm)" :error="form.errors['export_format.margin_cm']">
                    <input :id="id" v-model.number="form.export_format.margin_cm" type="number" min="1" max="4" step="0.1" class="input" :class="{ 'input-error': invalid }" />
                </FormField>
            </div>

            <FormField v-slot="{ id, invalid }" label="Baris atas kop" hint="Tercetak tebal di atas nama sekolah, satu baris per instansi (mis. Pemerintah Provinsi…, Dinas Pendidikan). Kosong = bawaan." :error="form.errors['export_format.kop_top']">
                <textarea :id="id" v-model="form.export_format.kop_top" rows="2" class="input" :class="{ 'input-error': invalid }" />
            </FormField>
            <label class="flex cursor-pointer items-center gap-3 text-sm"><input v-model="form.export_format.show_logo" type="checkbox" class="size-4 accent-primary-600" /> Tampilkan logo di kop</label>

            <FormField v-slot="{ id, invalid }" label="Baris tambahan kop" hint="Satu baris per alamat/kontak, tampil di bawah nama sekolah. Kosong = bawaan." :error="form.errors['export_format.kop_extra']">
                <textarea :id="id" v-model="form.export_format.kop_extra" rows="3" class="input" :class="{ 'input-error': invalid }" />
            </FormField>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField v-slot="{ id, invalid }" label="Awalan nomor dokumen" :error="form.errors['export_format.doc_prefix']">
                    <input :id="id" v-model="form.export_format.doc_prefix" class="input" :class="{ 'input-error': invalid }" />
                </FormField>
                <FormField v-slot="{ id, invalid }" label="Teks footer" :error="form.errors['export_format.footer_text']">
                    <input :id="id" v-model="form.export_format.footer_text" class="input" :class="{ 'input-error': invalid }" />
                </FormField>
            </div>

            <label class="flex cursor-pointer items-center gap-3 text-sm"><input v-model="form.export_format.show_key_sheet" type="checkbox" class="size-4 accent-primary-600" /> Sertakan lembar kunci jawaban</label>
            <label class="flex cursor-pointer items-center gap-3 text-sm"><input v-model="form.export_format.show_blueprint" type="checkbox" class="size-4 accent-primary-600" /> Sertakan kisi-kisi</label>
            <label class="flex cursor-pointer items-center gap-3 text-sm"><input v-model="form.export_format.show_recap" type="checkbox" class="size-4 accent-primary-600" /> Sertakan rekap validasi</label>
        </fieldset>

        <button type="submit" class="btn-primary" :disabled="form.processing">
            <LoaderCircle v-if="form.processing" class="size-4 animate-spin" /> Simpan pengaturan
        </button>
    </form>
</template>
