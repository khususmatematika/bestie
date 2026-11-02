<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { formatDateTime } from '@/Composables/useFormat';
import FormField from '@/Components/ui/FormField.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps<{
    profile: {
        name: string;
        email: string | null;
        nip: string | null;
        phone: string | null;
        locale: string;
        theme: string;
        last_login_at: string | null;
    };
}>();

const info = useForm({ name: props.profile.name });
const password = useForm({ current_password: '', password: '', password_confirmation: '' });

function saveInfo(): void {
    info.put('/profil', { preserveScroll: true });
}

function savePassword(): void {
    password.put('/profil/password', { preserveScroll: true, onSuccess: () => password.reset() });
}
</script>

<template>
    <Head title="Profil" />
    <PageHeader title="Profil & keamanan" description="Kelola data akun dan kata sandi Anda." />

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card p-6">
            <h2 class="text-base font-bold text-ink">Data akun</h2>
            <form class="mt-5 space-y-4" @submit.prevent="saveInfo">
                <FormField v-slot="{ id, invalid }" label="Nama lengkap" :error="info.errors.name" required>
                    <input :id="id" v-model="info.name" class="input" :class="{ 'input-error': invalid }" required />
                </FormField>
                <FormField v-slot="{ id }" label="Email" hint="Email dan NIP hanya dapat diubah oleh Admin.">
                    <input :id="id" :value="profile.email ?? '—'" class="input" disabled />
                </FormField>
                <FormField v-slot="{ id }" label="NIP">
                    <input :id="id" :value="profile.nip ?? '—'" class="input font-mono" disabled />
                </FormField>
                <p class="text-xs text-ink-faint">Login terakhir: {{ formatDateTime(profile.last_login_at) }} WIB</p>
                <button type="submit" class="btn-primary" :disabled="info.processing">
                    <LoaderCircle v-if="info.processing" class="size-4 animate-spin" /> Simpan
                </button>
            </form>
        </section>

        <section class="card p-6">
            <h2 class="text-base font-bold text-ink">Ganti kata sandi</h2>
            <form class="mt-5 space-y-4" @submit.prevent="savePassword">
                <FormField v-slot="{ id, invalid }" label="Kata sandi saat ini" :error="password.errors.current_password">
                    <input :id="id" v-model="password.current_password" type="password" class="input" :class="{ 'input-error': invalid }" autocomplete="current-password" />
                </FormField>
                <FormField v-slot="{ id, invalid }" label="Kata sandi baru" :error="password.errors.password" hint="Minimal 10 karakter, huruf besar, huruf kecil, dan angka.">
                    <input :id="id" v-model="password.password" type="password" class="input" :class="{ 'input-error': invalid }" autocomplete="new-password" />
                </FormField>
                <FormField v-slot="{ id }" label="Ulangi kata sandi baru">
                    <input :id="id" v-model="password.password_confirmation" type="password" class="input" autocomplete="new-password" />
                </FormField>
                <button type="submit" class="btn-primary" :disabled="password.processing">
                    <LoaderCircle v-if="password.processing" class="size-4 animate-spin" /> Ganti kata sandi
                </button>
            </form>
        </section>
    </div>
</template>
