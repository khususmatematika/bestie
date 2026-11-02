<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { BadgeCheck, BookCheck, ClipboardCheck, Languages, ShieldCheck } from '@lucide/vue';
import ThemeToggle from '@/Components/layout/ThemeToggle.vue';
import FlashToasts from '@/Components/ui/FlashToasts.vue';
import PullCharacter from '@/Components/auth/PullCharacter.vue';
import logo from '../../images/login-logo.png';

const page = usePage();

// Halaman masuk selalu satu layar penuh tanpa gulir: kolom tengah diperkecil otomatis bila layar pendek.
const area = ref<HTMLElement | null>(null);
const col = ref<HTMLElement | null>(null);
const fit = ref(1);
let observer: ResizeObserver | undefined;

function recalc(): void {
    const a = area.value;
    const c = col.value;
    if (!a || !c) return;
    const available = a.clientHeight - 8;
    const natural = c.offsetHeight;
    fit.value = natural > 0 ? Math.min(1, Math.max(0.3, available / natural)) : 1;
}

onMounted(() => {
    document.documentElement.classList.add('auth-noscroll');
    nextTick(recalc);
    observer = new ResizeObserver(recalc);
    if (area.value) observer.observe(area.value);
    if (col.value) observer.observe(col.value);
});

onBeforeUnmount(() => {
    document.documentElement.classList.remove('auth-noscroll');
    observer?.disconnect();
});

const highlights = [
    { icon: ClipboardCheck, label: 'Validasi per nomor' },
    { icon: BookCheck, label: 'Naskah final otomatis' },
    { icon: Languages, label: 'Arsip & terjemahan' },
];

// Data contoh untuk pratinjau dekoratif (bukan data sungguhan).
const sample = [
    { no: 1, checks: [1, 1, 1, 1, 1, 1, 1, 1, 1, 1], sim: 12 },
    { no: 2, checks: [1, 1, 0, 1, 1, 1, 1, 1, 1, 1], sim: 34 },
    { no: 3, checks: [1, 1, 1, 1, 0, 1, 0, 1, 1, 1], sim: 78 },
];
</script>

<template>
    <div class="auth-hero relative isolate flex h-dvh max-h-dvh flex-col overflow-hidden">
        <!-- Latar berlapis -->
        <div class="auth-grid pointer-events-none absolute inset-0 -z-10" aria-hidden="true" />
        <div class="pointer-events-none absolute -left-40 -top-40 -z-10 size-[34rem] rounded-full bg-primary-500/40 blur-[110px]" aria-hidden="true" />
        <div class="pointer-events-none absolute -bottom-48 -right-32 -z-10 size-[36rem] rounded-full bg-accent-500/35 blur-[120px]" aria-hidden="true" />
        <div class="pointer-events-none absolute left-1/2 top-1/3 -z-10 size-[28rem] -translate-x-1/2 rounded-full bg-primary-300/20 blur-[100px]" aria-hidden="true" />

        <!-- Bilah atas -->
        <div class="flex items-center justify-between px-4 pt-4 sm:px-8 sm:pt-6">
            <p class="flex items-center gap-2 text-sm font-bold tracking-[0.2em] text-white/90">
                <span class="size-2 rounded-full bg-accent-500 shadow-[0_0_12px_2px_rgba(15,157,138,0.8)]" aria-hidden="true" /> BESTIE
            </p>
            <ThemeToggle class="!text-white/80 hover:!bg-white/10 hover:!text-white" />
        </div>

        <main id="konten-utama" ref="area" tabindex="-1" class="relative mx-auto flex min-h-0 w-full max-w-6xl flex-1 items-center justify-center px-4 py-2 outline-none sm:px-8 sm:py-4">
            <!-- Kartu pratinjau kiri (laptop ke atas) -->
            <aside class="auth-float-a pointer-events-none absolute left-2 top-[12%] hidden w-[17.5rem] -rotate-3 lg:block xl:left-6 xl:w-80" aria-hidden="true">
                <div class="rounded-2xl border border-white/20 bg-white/10 p-4 text-white shadow-2xl backdrop-blur-md">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-bold tracking-wide">Rekap validasi</p>
                        <span class="rounded-full bg-white/15 px-2 py-0.5 text-[10px] font-semibold">Matematika · X</span>
                    </div>
                    <div class="mt-3 space-y-2">
                        <div v-for="row in sample" :key="row.no" class="flex items-center gap-2">
                            <span class="w-4 text-[10px] font-bold text-white/70">{{ row.no }}</span>
                            <span class="flex flex-1 gap-[3px]">
                                <span v-for="(c, i) in row.checks" :key="i" class="h-4 flex-1 rounded-[3px]" :class="c ? 'bg-primary-400' : 'bg-danger-500'" />
                            </span>
                            <span class="w-9 rounded px-1 text-center text-[10px] font-bold" :class="row.sim >= 70 ? 'bg-red-200 text-red-900' : 'bg-white/15'">{{ row.sim }}%</span>
                        </div>
                    </div>
                    <p class="mt-3 flex items-center gap-3 text-[10px] text-white/70">
                        <span class="flex items-center gap-1"><span class="size-2 rounded-sm bg-primary-400" /> Terpenuhi</span>
                        <span class="flex items-center gap-1"><span class="size-2 rounded-sm bg-danger-500" /> Tidak</span>
                    </p>
                </div>
            </aside>

            <!-- Kartu pratinjau kanan -->
            <aside class="auth-float-b pointer-events-none absolute right-2 top-[18%] hidden w-64 rotate-3 lg:block xl:right-6 xl:w-72" aria-hidden="true">
                <div class="rounded-2xl border border-white/20 bg-white/10 p-4 text-white shadow-2xl backdrop-blur-md">
                    <div class="flex items-center gap-4">
                        <svg viewBox="0 0 36 36" class="size-16 -rotate-90">
                            <circle cx="18" cy="18" r="15.5" fill="none" class="stroke-white/20" stroke-width="3.5" />
                            <circle cx="18" cy="18" r="15.5" fill="none" class="stroke-accent-500" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="97.4" stroke-dashoffset="12" />
                        </svg>
                        <div>
                            <p class="text-2xl font-extrabold leading-none">88%</p>
                            <p class="mt-1 text-[11px] text-white/70">paket tervalidasi</p>
                        </div>
                    </div>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/15"><div class="h-full w-4/5 rounded-full bg-gradient-to-r from-primary-300 to-accent-500" /></div>
                </div>
            </aside>

            <aside class="auth-float-c pointer-events-none absolute bottom-[10%] right-6 hidden w-60 -rotate-2 lg:block xl:right-16 xl:w-64" aria-hidden="true">
                <div class="flex items-center gap-3 rounded-2xl border border-white/20 bg-white/10 p-3.5 text-white shadow-2xl backdrop-blur-md">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-accent-500/90"><BadgeCheck class="size-5" /></span>
                    <div>
                        <p class="text-xs font-bold">Soal No. 12 disetujui</p>
                        <p class="text-[11px] text-white/70">Paket Fix · naskah final siap</p>
                    </div>
                </div>
            </aside>

            <!-- Kolom tengah -->
            <div ref="col" class="auth-fit relative z-10 w-full max-w-[26rem]" :style="{ scale: fit }"><div class="auth-rise">
                <header class="mb-5 flex flex-col items-center text-center text-white">
                    <span class="relative mb-3 flex items-center justify-center">
                        <span class="absolute inset-0 -z-10 scale-[1.9] rounded-full bg-white/25 blur-2xl" aria-hidden="true" />
                        <img :src="logo" alt="Logo SMA Negeri 1 Turen" class="h-20 w-auto drop-shadow-[0_10px_22px_rgba(0,0,0,0.45)] sm:h-24 [@media(max-height:820px)]:sm:h-[4.5rem]" width="72" height="96" />
                    </span>
                    <h2 class="text-balance text-lg font-extrabold leading-snug sm:text-xl">Berkas Evaluasi Sumatif</h2>
                    <p class="text-sm font-semibold text-primary-200">Telaah · Integrasi · Eksekusi</p>
                    <p class="mt-2 rounded-full border border-white/25 [@media(max-height:820px)]:hidden bg-white/10 px-3 py-1 text-[11px] font-semibold backdrop-blur">{{ page.props.app.school }}</p>
                </header>

                <div class="pull-stage relative pl-[4.6rem] sm:pl-0">
                    <PullCharacter class="pull-char absolute bottom-14 right-[calc(100%-4.6rem)] z-10 h-[4.4rem] w-auto max-[370px]:hidden sm:right-full sm:h-36 lg:h-48 xl:h-56" />
                    <div class="pull-card rounded-3xl border border-white/40 bg-surface/95 p-6 shadow-[0_30px_80px_-20px_rgba(0,0,0,0.55)] backdrop-blur-xl sm:p-8 dark:border-white/10">
                        <slot />
                    </div>
                </div>

                <ul class="mt-5 flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 text-[11px] text-white/80 sm:text-xs lg:hidden">
                    <li v-for="h in highlights" :key="h.label" class="flex items-center gap-1.5">
                        <component :is="h.icon" class="size-3.5 text-primary-200" aria-hidden="true" />
                        {{ h.label }}
                    </li>
                </ul>
            </div></div>
        </main>

        <footer class="hidden px-4 pb-4 text-center [@media(min-height:900px)]:block text-[11px] text-white/60">© {{ new Date().getFullYear() }} Panitia Asesmen Sumatif · {{ page.props.app.school }}</footer>

        <FlashToasts />
    </div>
</template>

<style>
html.auth-noscroll,
html.auth-noscroll body {
    overflow: hidden;
    height: 100%;
}

.auth-fit {
    transform-origin: center center;
}

.auth-hero {
    background-image: linear-gradient(155deg, #0f1f55 0%, #1e3a8a 45%, #0a6b5e 120%);
}

.auth-grid {
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.06) 1px, transparent 1px);
    background-size: 44px 44px;
    mask-image: radial-gradient(ellipse at center, #000 30%, transparent 80%);
}

@keyframes auth-rise {
    from {
        opacity: 0;
        transform: translateY(14px) scale(0.985);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@keyframes auth-float {
    0%,
    100% {
        translate: 0 0;
    }
    50% {
        translate: 0 -10px;
    }
}

@keyframes pull-in {
    0% {
        transform: translateX(min(75vw, 760px));
    }
    100% {
        transform: none;
    }
}

@keyframes pull-tilt {
    0% {
        transform: rotate(1.2deg);
    }
    100% {
        transform: none;
    }
}

@media (prefers-reduced-motion: no-preference) {
    .pull-stage {
        animation: pull-in 6.5s cubic-bezier(0.33, 0.1, 0.25, 1) both;
    }

    .pull-card {
        transform-origin: 0 60%;
        animation: pull-tilt 6.5s ease-out both;
    }

    .auth-rise {
        animation: auth-rise 0.55s ease-out both;
    }

    .auth-float-a {
        animation: auth-float 7s ease-in-out infinite;
    }

    .auth-float-b {
        animation: auth-float 8.5s ease-in-out -2s infinite;
    }

    .auth-float-c {
        animation: auth-float 6.5s ease-in-out -4s infinite;
    }
}
</style>
