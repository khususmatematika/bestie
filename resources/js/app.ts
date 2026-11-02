import '@fontsource-variable/plus-jakarta-sans';
import '@fontsource-variable/source-serif-4';
import '@fontsource-variable/jetbrains-mono';
import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from './Layouts/AppLayout.vue';
import AuthLayout from './Layouts/AuthLayout.vue';

createInertiaApp({
    title: (title) => (title ? `${title} · BESTIE` : 'BESTIE'),
    // Plugin @inertiajs/vite mengubah ini menjadi resolver halaman otomatis.
    pages: './Pages',
    // Halaman publik memakai AuthLayout; semua halaman lain memakai AppLayout.
    layout: (name) => (name.startsWith('Auth/') || name === 'Error' ? AuthLayout : AppLayout),
    progress: { color: '#3b5bdb' },
});
