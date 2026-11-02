<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, KeyRound, LoaderCircle, LogIn, UserRound } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import FormField from '@/Components/ui/FormField.vue';

const form = useForm({ identifier: '', password: '', remember: false });
const showPassword = ref(false);
const capsLock = ref(false);

// Selama animasi guru berjalan, kolom dikunci & saran kata sandi tersimpan peramban tidak dimunculkan.
const ready = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;

onMounted(() => {
    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    timer = setTimeout(() => (ready.value = true), still ? 0 : 6700);
});

onBeforeUnmount(() => {
    if (timer !== undefined) clearTimeout(timer);
});

function submit(): void {
    form.post('/login', { onFinish: () => form.reset('password') });
}

function checkCaps(event: KeyboardEvent): void {
    capsLock.value = event.getModifierState?.('CapsLock') ?? false;
}
</script>

<template>
    <Head title="Masuk" />

    <div>
        <div class="mb-6 text-center">
            <h1 class="text-xl font-extrabold tracking-tight text-ink sm:text-2xl">Selamat datang</h1>
            <p class="mt-1.5 text-sm text-ink-soft">Masuk dengan <strong class="text-ink">email atau NIP</strong></p>
        </div>

        <form class="space-y-5" novalidate @submit.prevent="submit">
            <FormField v-slot="{ id, invalid, describedBy }" label="Email atau NIP" :error="form.errors.identifier">
                <div class="relative">
                    <UserRound class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-faint" />
                    <input
                        :id="id"
                        v-model="form.identifier"
                        type="text"
                        class="input pl-10"
                        :class="{ 'input-error': invalid }"
                        :aria-describedby="describedBy"
                        :autocomplete="ready ? 'username' : 'off'"
                        :readonly="!ready"
                        autocapitalize="none"
                        spellcheck="false"
                        required
                        placeholder="nama@sekolah.sch.id atau 1980…"
                    />
                </div>
            </FormField>

            <FormField v-slot="{ id, invalid, describedBy }" label="Kata sandi" :error="form.errors.password">
                <div class="relative">
                    <KeyRound class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-faint" />
                    <input
                        :id="id"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        class="input pl-10 pr-11"
                        :class="{ 'input-error': invalid }"
                        :aria-describedby="describedBy"
                        :autocomplete="ready ? 'current-password' : 'off'"
                        :readonly="!ready"
                        required
                        @keydown="checkCaps"
                        @keyup="checkCaps"
                    />
                    <button
                        type="button"
                        class="btn-icon absolute right-1.5 top-1/2 -translate-y-1/2"
                        :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                        @click="showPassword = !showPassword"
                    >
                        <component :is="showPassword ? EyeOff : Eye" class="size-4" />
                    </button>
                </div>
                <p v-if="capsLock" class="mt-1.5 text-xs font-semibold text-warning-700 dark:text-warning-500" role="status">Caps Lock sedang aktif.</p>
            </FormField>

            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-ink-soft">
                <input v-model="form.remember" type="checkbox" class="size-4 rounded border-line accent-primary-600" />
                Ingat saya di perangkat ini
            </label>

            <button type="submit" class="btn-primary w-full py-3 text-base" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                <LogIn v-else class="size-4" />
                {{ form.processing ? 'Memproses…' : 'Masuk' }}
            </button>
        </form>

        <p class="mt-5 rounded-xl bg-surface-2 p-3  text-center text-xs leading-relaxed text-ink-soft">
            Akun dibuat oleh Admin panitia. Lupa kata sandi? Hubungi Admin untuk diatur ulang.
        </p>
    </div>
</template>
