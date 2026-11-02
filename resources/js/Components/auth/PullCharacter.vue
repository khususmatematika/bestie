<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Ilustrasi orisinal: seorang guru menarik kartu login dengan tali. Saat halaman dibuka ia berjalan masuk
 * (kaki melangkah) menarik kartu, lalu berhenti dan bernapas/berkedip pelan. Bila perangkat memilih
 * "kurangi gerakan", yang tampil hanya pose akhir.
 */
const walking = ref(true);
let timer: ReturnType<typeof setTimeout> | undefined;

onMounted(() => {
    timer = setTimeout(() => (walking.value = false), 6600);
});

onBeforeUnmount(() => {
    if (timer !== undefined) clearTimeout(timer);
});
</script>

<template>
    <svg viewBox="0 0 260 250" class="pc" :class="{ 'pc-walking': walking }" role="img" aria-label="Ilustrasi guru menarik kartu login" xmlns="http://www.w3.org/2000/svg">
        <!-- bayangan -->
        <ellipse cx="108" cy="244" rx="62" ry="6" fill="rgba(0,0,0,0.28)" />

        <defs>
            <linearGradient id="pc-shirt" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0" stop-color="#2a3f8f" />
                <stop offset="1" stop-color="#1a2a6c" />
            </linearGradient>
        </defs>

        <g class="pc-body">
            <!-- kaki belakang -->
            <g class="pc-leg-b">
                <path d="M112 152 L136 224" stroke="#0d0d10" stroke-width="21" stroke-linecap="round" fill="none" />
                <path d="M122 232 Q124 224 138 226 L146 238 L118 240 Z" fill="#0b0b0f" />
            </g>
            <!-- kaki depan -->
            <g class="pc-leg-f">
                <path d="M108 152 L86 226" stroke="#1b1b21" stroke-width="21" stroke-linecap="round" fill="none" />
                <path d="M62 238 Q64 226 84 228 L96 236 L96 240 L62 241 Z" fill="#0b0b0f" />
            </g>

            <!-- tubuh: kemeja PDH KORPRI biru dongker, dimasukkan ke celana -->
            <path d="M74 108 Q92 90 118 99 L128 160 Q108 170 94 160 Z" fill="url(#pc-shirt)" />
            <!-- lipatan & bayangan kain -->
            <path d="M118 104 L126 158" stroke="#0f1b4a" stroke-width="5" opacity="0.35" stroke-linecap="round" />
            <path d="M100 134 Q110 140 124 138" stroke="#0f1b4a" stroke-width="1.6" fill="none" opacity="0.45" />
            <path d="M96 146 Q108 152 126 148" stroke="#0f1b4a" stroke-width="1.4" fill="none" opacity="0.4" />
            <!-- kerah terbuka -->
            <path d="M82 99 L97 116 L101 98 Q92 92 82 99 Z" fill="#e9ecf5" />
            <path d="M82 99 L97 116 L101 98" stroke="#aab2cf" stroke-width="1" fill="none" />
            <!-- plaket + kancing -->
            <path d="M97 116 L106 164" stroke="#0f1b4a" stroke-width="1.4" />
            <circle cx="99" cy="124" r="1.5" fill="#d9dcea" />
            <circle cx="101" cy="136" r="1.5" fill="#d9dcea" />
            <circle cx="103" cy="148" r="1.5" fill="#d9dcea" />
            <!-- tanda pangkat di bahu -->
            <g transform="rotate(-24 86 103)">
                <rect x="78" y="99" width="15" height="6" rx="1.4" fill="#16255f" stroke="#f5c518" stroke-width="1" />
                <rect x="82" y="100.2" width="2" height="3.6" fill="#f5c518" />
                <rect x="86" y="100.2" width="2" height="3.6" fill="#f5c518" />
            </g>
            <!-- lencana KORPRI (dada kiri) -->
            <g>
                <circle cx="86" cy="122" r="6.2" fill="#f5c518" stroke="#a97a0c" stroke-width="1" />
                <path d="M86 116.8 L87.5 120.6 L91.4 120.8 L88.3 123.1 L89.4 126.9 L86 124.7 L82.6 126.9 L83.7 123.1 L80.6 120.8 L84.5 120.6 Z" fill="#16255f" />
            </g>
            <!-- saku dada bertutup -->
            <path d="M80 131 L94 134 L94 143 Q87 147 80 143 Z" fill="#1a2b6d" stroke="#0f1b4a" stroke-width="0.9" />
            <circle cx="87" cy="141" r="1.2" fill="#d9dcea" />
            <!-- papan nama (dada kanan) -->
            <rect x="109" y="124" width="14" height="5.4" rx="1.2" fill="#f3f4f8" stroke="#aab2cf" stroke-width="0.6" />
            <path d="M111 126.7 H121" stroke="#5a6285" stroke-width="1" stroke-linecap="round" />
            <!-- ikat pinggang -->
            <path d="M93 158 Q108 167 128 160 L128 166 Q108 174 93 165 Z" fill="#0d0d10" />
            <rect x="105" y="160" width="9" height="7" rx="1.6" fill="#f5c518" stroke="#a97a0c" stroke-width="0.8" transform="rotate(8 109 163)" />

            <!-- lengan belakang (memegang tali) -->
            <g class="pc-arm-b">
                <path d="M104 108 L124 126 L150 129" stroke="#f2b583" stroke-width="13" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                <path d="M104 108 L117 121" stroke="#1d2f6f" stroke-width="17" stroke-linecap="round" fill="none" />
                <path d="M112 117 L122 127" stroke="#0f1b4a" stroke-width="2" opacity="0.5" />
                <circle cx="152" cy="129" r="7.5" fill="#f2b583" />
            </g>

            <!-- kepala -->
            <g class="pc-head">
                <rect x="66" y="88" width="14" height="16" rx="6" fill="#e6a574" />
                <circle cx="74" cy="72" r="23" fill="#f2b583" />
                <path d="M51 70 Q50 46 76 46 Q100 46 98 72 Q92 58 76 58 Q60 58 51 70 Z" fill="#1b1b1f" />
                <ellipse cx="90" cy="76" rx="4" ry="6" fill="#e6a574" />
                <path d="M51 78 Q44 82 51 87" stroke="#e6a574" stroke-width="5" stroke-linecap="round" fill="none" />
                <ellipse class="pc-eye" cx="61" cy="74" rx="2.6" ry="3" fill="#0b0b0f" />
                <path d="M54 54 Q62 49 70 53" stroke="#1b1b1f" stroke-width="3" stroke-linecap="round" fill="none" />
                <path d="M55 89 Q62 95 71 89" stroke="#8a4b2a" stroke-width="2.6" stroke-linecap="round" fill="none" />
            </g>

            <!-- lengan depan (memegang tali) -->
            <g class="pc-arm-f">
                <path d="M98 108 L122 132 L172 128" stroke="#f2b583" stroke-width="13" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                <path d="M98 108 L113 123" stroke="#1d2f6f" stroke-width="17" stroke-linecap="round" fill="none" />
                <path d="M108 118 L118 128" stroke="#0f1b4a" stroke-width="2" opacity="0.5" />
                <circle cx="174" cy="128" r="7.5" fill="#f2b583" />
            </g>
        </g>

        <!-- tali ke kartu -->
        <path d="M150 129 Q205 150 252 130" stroke="#c79348" stroke-width="3.4" stroke-linecap="round" fill="none" />
        <path d="M160 129 Q206 150 252 130" stroke="#e6bb73" stroke-width="1.2" stroke-linecap="round" stroke-dasharray="3 4" fill="none" />
        <circle cx="253" cy="130" r="4.5" fill="#c79348" />
    </svg>
</template>

<style>
.pc {
    overflow: visible;
    filter: drop-shadow(0 10px 14px rgba(0, 0, 0, 0.25));
}

.pc-leg-b,
.pc-leg-f,
.pc-arm-b,
.pc-arm-f,
.pc-head,
.pc-body {
    transform-box: view-box;
    transition: transform 0.5s ease-out;
}

.pc-leg-b,
.pc-leg-f {
    transform-origin: 112px 152px;
}

.pc-leg-b {
    transform: rotate(6deg);
}

.pc-leg-f {
    transform: rotate(-4deg);
}

.pc-arm-b,
.pc-arm-f {
    transform-origin: 100px 108px;
}

.pc-head {
    transform-origin: 74px 96px;
}

.pc-eye {
    transform-box: fill-box;
    transform-origin: center;
}

@keyframes pc-step-f {
    0%,
    100% {
        transform: rotate(13deg);
    }
    50% {
        transform: rotate(-14deg);
    }
}

@keyframes pc-step-b {
    0%,
    100% {
        transform: rotate(-14deg);
    }
    50% {
        transform: rotate(13deg);
    }
}

@keyframes pc-bob {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-2px);
    }
}

@keyframes pc-breathe {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-1.5px);
    }
}

@keyframes pc-tug {
    0%,
    100% {
        transform: rotate(0deg);
    }
    50% {
        transform: rotate(2.5deg);
    }
}

@keyframes pc-blink {
    0%,
    94%,
    100% {
        transform: scaleY(1);
    }
    97% {
        transform: scaleY(0.1);
    }
}

@media (prefers-reduced-motion: no-preference) {
    .pc-walking .pc-leg-f {
        animation: pc-step-f 1.8s ease-in-out infinite;
    }

    .pc-walking .pc-leg-b {
        animation: pc-step-b 1.8s ease-in-out infinite;
    }

    .pc-walking .pc-body {
        animation: pc-bob 0.9s ease-in-out infinite;
    }

    .pc-walking .pc-arm-f,
    .pc-walking .pc-arm-b {
        transform: rotate(0deg);
    }

    .pc:not(.pc-walking) .pc-head {
        animation: pc-breathe 3.2s ease-in-out infinite;
    }

    .pc:not(.pc-walking) .pc-arm-f {
        animation: pc-tug 2.6s ease-in-out infinite;
    }

    .pc:not(.pc-walking) .pc-arm-b {
        animation: pc-tug 2.6s ease-in-out -1.3s infinite;
    }

    .pc-eye {
        animation: pc-blink 5s infinite;
    }
}
</style>
