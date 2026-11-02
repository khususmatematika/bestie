<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, ShieldAlert } from '@lucide/vue';
import FormField from '@/Components/ui/FormField.vue';

const form = useForm({ current_password: '', password: '', password_confirmation: '' });

function submit(): void {
    form.put('/ganti-password', { onFinish: () => form.reset() });
}
</script>

<template>
    <Head title="Ganti kata sandi" />

    <div>
        <div class="mb-5 flex size-12 items-center justify-center rounded-2xl bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-500">
            <ShieldAlert class="size-6" />
        </div>
        <h1 class="text-2xl font-extrabold tracking-tight text-ink">Buat kata sandi baru</h1>
        <p class="mt-2 text-sm text-ink-soft">Demi keamanan naskah soal, kata sandi awal dari Admin wajib diganti sebelum Anda melanjutkan.</p>

        <form class="mt-7 space-y-5" novalidate @submit.prevent="submit">
            <FormField v-slot="{ id, invalid, describedBy }" label="Kata sandi saat ini (dari Admin)" :error="form.errors.current_password">
                <input :id="id" v-model="form.current_password" type="password" class="input" :class="{ 'input-error': invalid }" :aria-describedby="describedBy" autocomplete="current-password" required />
            </FormField>

            <FormField v-slot="{ id, invalid, describedBy }" label="Kata sandi baru" :error="form.errors.password" hint="Minimal 10 karakter, kombinasi huruf besar, huruf kecil, dan angka.">
                <input :id="id" v-model="form.password" type="password" class="input" :class="{ 'input-error': invalid }" :aria-describedby="describedBy" autocomplete="new-password" required />
            </FormField>

            <FormField v-slot="{ id }" label="Ulangi kata sandi baru">
                <input :id="id" v-model="form.password_confirmation" type="password" class="input" autocomplete="new-password" required />
            </FormField>

            <button type="submit" class="btn-primary w-full py-3" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                Simpan &amp; lanjutkan
            </button>
            <button type="button" class="btn-ghost w-full" @click="router.post('/logout')">Keluar</button>
        </form>
    </div>
</template>
