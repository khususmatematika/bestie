<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ status: number }>();

function back(): void {
    window.history.back();
}

const copy = computed(() => {
    switch (props.status) {
        case 403:
            return { title: 'Akses ditolak', text: 'Anda tidak memiliki izin untuk membuka halaman ini.' };
        case 404:
            return { title: 'Halaman tidak ditemukan', text: 'Alamat yang Anda tuju tidak tersedia atau sudah dipindahkan.' };
        case 419:
            return { title: 'Sesi berakhir', text: 'Sesi Anda sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.' };
        case 429:
            return { title: 'Terlalu banyak permintaan', text: 'Mohon tunggu sebentar sebelum mencoba kembali.' };
        case 503:
            return { title: 'Sedang pemeliharaan', text: 'Aplikasi sedang diperbarui. Silakan coba beberapa saat lagi.' };
        default:
            return { title: 'Terjadi kesalahan', text: 'Maaf, ada masalah pada server. Tim kami sudah mencatatnya.' };
    }
});
</script>

<template>
    <Head :title="copy.title" />

    <div class="text-center">
        <p class="text-7xl font-extrabold tracking-tight text-primary-600">{{ status }}</p>
        <h1 class="mt-4 text-2xl font-extrabold text-ink">{{ copy.title }}</h1>
        <p class="mt-2 text-sm text-ink-soft">{{ copy.text }}</p>
        <div class="mt-8 flex justify-center gap-2">
            <a href="/" class="btn-primary">Kembali ke beranda</a>
            <button type="button" class="btn-secondary" @click="back">Halaman sebelumnya</button>
        </div>
    </div>
</template>
