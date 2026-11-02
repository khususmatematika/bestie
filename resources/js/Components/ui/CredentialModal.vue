<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Check, Copy, TriangleAlert } from '@lucide/vue';
import { ref, watch } from 'vue';
import type { Credential } from '@/types';
import Modal from './Modal.vue';

/**
 * Menampilkan kata sandi awal SATU KALI setelah akun dibuat / kata sandi direset.
 * Data datang dari flash sesi dan tidak disimpan di klien setelah ditutup.
 */
const page = usePage();
const credential = ref<Credential | null>(null);
const copied = ref(false);

watch(
    () => page.props.flash?.credential,
    (value) => {
        if (value) {
            credential.value = value;
            copied.value = false;
        }
    },
    { immediate: true },
);

async function copy(): Promise<void> {
    if (!credential.value) return;

    const text = `Akun BESTIE — ${credential.value.name}\nAlamat: ${window.location.origin}/login\nMasuk dengan: ${credential.value.nip ?? credential.value.email}\nKata sandi: ${credential.value.password}`;

    try {
        await navigator.clipboard.writeText(text);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2500);
    } catch {
        /* clipboard dapat diblokir; pengguna tetap bisa menyalin manual */
    }
}
</script>

<template>
    <Modal :open="!!credential" title="Kata sandi awal" description="Berikan kepada guru yang bersangkutan." :closeable="false" @close="credential = null">
        <div v-if="credential" class="space-y-4">
            <div class="flex gap-3 rounded-xl bg-warning-50 p-3.5 text-sm text-warning-700 dark:bg-warning-500/15 dark:text-warning-500">
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <p>Kata sandi ini <strong>hanya ditampilkan sekali</strong>. Salin sekarang (tombol Salin menyertakan alamat login dan akun).</p>
            </div>

            <dl class="divide-y divide-line rounded-xl border border-line text-sm">
                <div class="flex justify-between gap-4 px-4 py-2.5">
                    <dt class="text-ink-soft">Nama</dt>
                    <dd class="text-right font-semibold text-ink">{{ credential.name }}</dd>
                </div>
                <div v-if="credential.email" class="flex justify-between gap-4 px-4 py-2.5">
                    <dt class="text-ink-soft">Email</dt>
                    <dd class="break-all text-right font-semibold text-ink">{{ credential.email }}</dd>
                </div>
                <div v-if="credential.nip" class="flex justify-between gap-4 px-4 py-2.5">
                    <dt class="text-ink-soft">NIP</dt>
                    <dd class="font-mono font-semibold text-ink">{{ credential.nip }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 bg-surface-2 px-4 py-3">
                    <dt class="text-ink-soft">Kata sandi</dt>
                    <dd class="select-all font-mono text-lg font-bold tracking-wider text-primary-700 dark:text-primary-300">
                        {{ credential.password }}
                    </dd>
                </div>
            </dl>
        </div>

        <template #footer>
            <button type="button" class="btn-secondary" @click="copy">
                <component :is="copied ? Check : Copy" class="size-4" />
                {{ copied ? 'Tersalin' : 'Salin' }}
            </button>
            <button type="button" class="btn-primary" @click="credential = null">Sudah saya catat</button>
        </template>
    </Modal>
</template>
