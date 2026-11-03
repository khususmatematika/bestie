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
    <svg viewBox="0 0 260 250" overflow="visible" class="pc" :class="{ 'pc-walking': walking }" role="img" aria-label="Ilustrasi guru menarik kartu login" xmlns="http://www.w3.org/2000/svg">
        <!-- bayangan -->
        <ellipse cx="108" cy="244" rx="62" ry="6" fill="rgba(0,0,0,0.28)" />

        <defs>
            <linearGradient id="pc-shirt" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0" stop-color="#d2bf8e" />
                <stop offset="1" stop-color="#b9a46c" />
            </linearGradient>
        </defs>

        <g class="pc-body">
            <!-- kaki belakang -->
            <g class="pc-leg-b">
                <path d="M112 152 L136 224" stroke="#3a2418" stroke-width="25" stroke-linecap="round" fill="none" />
                <path d="M112 152 L136 224" stroke="#8f7c4c" stroke-width="21" stroke-linecap="round" fill="none" />
                <path d="M122 232 Q124 224 138 226 L146 238 L118 240 Z" fill="#2b1a12" stroke="#3a2418" stroke-width="1.6" stroke-linejoin="round" />
            </g>
            <!-- kaki depan -->
            <g class="pc-leg-f">
                <path d="M108 152 L86 226" stroke="#3a2418" stroke-width="25" stroke-linecap="round" fill="none" />
                <path d="M108 152 L86 226" stroke="#a08d5a" stroke-width="21" stroke-linecap="round" fill="none" />
                <path d="M62 238 Q64 226 84 228 L96 236 L96 240 L62 241 Z" fill="#2b1a12" stroke="#3a2418" stroke-width="1.6" stroke-linejoin="round" />
            </g>

            <!-- tubuh: kemeja PDH KORPRI khaki, dimasukkan ke celana -->
            <path d="M74 108 Q92 90 118 99 L128 160 Q108 170 94 160 Z" fill="url(#pc-shirt)" stroke="#3a2418" stroke-width="2.2" stroke-linejoin="round" />
            <!-- lipatan & bayangan kain -->
            <path d="M118 104 L126 158" stroke="#6f5f33" stroke-width="5" opacity="0.35" stroke-linecap="round" />
            <path d="M100 134 Q110 140 124 138" stroke="#6f5f33" stroke-width="1.6" fill="none" opacity="0.45" />
            <path d="M96 146 Q108 152 126 148" stroke="#6f5f33" stroke-width="1.4" fill="none" opacity="0.4" />
            <!-- kerah terbuka -->
            <path d="M82 99 L97 116 L101 98 Q92 92 82 99 Z" fill="#dccda4" />
            <path d="M82 99 L97 116 L101 98" stroke="#8f7f52" stroke-width="1" fill="none" />
            <!-- plaket + kancing -->
            <path d="M97 116 L106 164" stroke="#6f5f33" stroke-width="1.4" />
            <circle cx="99" cy="124" r="1.5" fill="#f3ead0" />
            <circle cx="101" cy="136" r="1.5" fill="#f3ead0" />
            <circle cx="103" cy="148" r="1.5" fill="#f3ead0" />
            <!-- tanda pangkat di bahu -->
            <g transform="rotate(-24 86 103)">
                <rect x="78" y="99" width="15" height="6" rx="1.4" fill="#16255f" stroke="#f5c518" stroke-width="1" />
                <rect x="82" y="100.2" width="2" height="3.6" fill="#f5c518" />
                <rect x="86" y="100.2" width="2" height="3.6" fill="#f5c518" />
            </g>
            <!-- saku dada bertutup -->
            <path d="M80 131 L94 134 L94 143 Q87 147 80 143 Z" fill="#c2ad78" stroke="#6f5f33" stroke-width="0.9" />
            <circle cx="87" cy="141" r="1.2" fill="#f3ead0" />
            <!-- papan nama (dada kanan) -->
            <rect x="109" y="124" width="14" height="5.4" rx="1.2" fill="#f3f4f8" stroke="#aab2cf" stroke-width="0.6" />
            <path d="M111 126.7 H121" stroke="#5a6285" stroke-width="1" stroke-linecap="round" />
            <!-- ikat pinggang -->
            <path d="M93 158 Q108 167 128 160 L128 166 Q108 174 93 165 Z" fill="#0d0d10" />
            <rect x="105" y="160" width="9" height="7" rx="1.6" fill="#f5c518" stroke="#a97a0c" stroke-width="0.8" transform="rotate(8 109 163)" />

            <!-- lengan belakang (memegang tali) -->
            <g class="pc-arm-b">
                <path d="M104 108 L124 126 L150 129" stroke="#3a2418" stroke-width="17.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                <path d="M104 108 L117 121" stroke="#3a2418" stroke-width="21.5" stroke-linecap="round" fill="none" />
                <path d="M104 108 L124 126 L150 129" stroke="#f6c49a" stroke-width="13" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                <path d="M104 108 L117 121" stroke="#c2ad78" stroke-width="17" stroke-linecap="round" fill="none" />
                <path d="M112 117 L122 127" stroke="#6f5f33" stroke-width="2" opacity="0.5" />
                <circle cx="152" cy="129" r="7.5" fill="#f6c49a" stroke="#3a2418" stroke-width="1.8" />
            </g>

            <!-- kepala: gaya kartun chibi (kepala besar), menghadap kiri -->
            <g class="pc-head">
                <g transform="translate(74 100) scale(1.3) translate(-74 -100)">
                    <rect x="68" y="88" width="14" height="14" rx="5" fill="#eaa877" stroke="#3a2418" stroke-width="1.6" />
                    <!-- telinga -->
                    <ellipse cx="94" cy="74" rx="4.6" ry="6" fill="#f6c49a" stroke="#3a2418" stroke-width="1.6" />
                    <path d="M93 72 Q96 74.5 93.4 77.5" stroke="#e08a5f" stroke-width="1.3" fill="none" stroke-linecap="round" />
                    <!-- wajah -->
                    <path d="M50 68 Q49 48 74 47 Q98 48 97 68 Q97 90 76 94 Q54 94 50 68 Z" fill="#f9cfa6" stroke="#3a2418" stroke-width="2" stroke-linejoin="round" />
                    <!-- rambut gelap berantakan -->
                    <path d="M47 66 Q40 40 60 34 Q62 24 76 30 Q84 22 94 32 Q106 36 100 58 Q99 66 96 72 Q95 58 90 54 Q86 60 74 56 Q66 60 58 52 Q56 62 54 70 Q50 72 47 66 Z" fill="#3b2a24" stroke="#3a2418" stroke-width="2" stroke-linejoin="round" />
                    <path d="M62 38 Q70 33 78 36" stroke="#5b4238" stroke-width="2.4" stroke-linecap="round" fill="none" />
                    <path d="M84 36 Q92 36 95 44" stroke="#5b4238" stroke-width="2.2" stroke-linecap="round" fill="none" />
                    <!-- mata besar, berkedip -->
                    <g class="pc-eye">
                        <ellipse cx="62.5" cy="72" rx="4.4" ry="5.8" fill="#2a1710" />
                        <circle cx="61.2" cy="70" r="1.7" fill="#ffffff" />
                        <ellipse cx="82" cy="72" rx="4" ry="5.5" fill="#2a1710" />
                        <circle cx="80.8" cy="70" r="1.4" fill="#ffffff" />
                    </g>
                    <!-- alis -->
                    <path d="M56 58.5 Q62 56 68 58.5" stroke="#3b2a24" stroke-width="2" stroke-linecap="round" fill="none" />
                    <path d="M76 58.5 Q82 56 88 59" stroke="#3b2a24" stroke-width="2" stroke-linecap="round" fill="none" />
                    <!-- pipi & hidung & senyum -->
                    <ellipse cx="58" cy="83" rx="4.2" ry="2.6" fill="#f08a78" opacity="0.5" />
                    <ellipse cx="88" cy="83" rx="3.6" ry="2.4" fill="#f08a78" opacity="0.45" />
                    <path d="M72 80.5 Q74 82 72.6 83.4" stroke="#d8895c" stroke-width="1.4" stroke-linecap="round" fill="none" />
                    <path d="M64 86.5 Q72 94 81 86.5 Q72 89 64 86.5 Z" fill="#c8402f" stroke="#3a2418" stroke-width="1.3" stroke-linejoin="round" />
                    <path d="M68 89.4 Q72 92 76.5 89.4 Q72 88.6 68 89.4 Z" fill="#f08a78" />
                </g>
            </g>

            <!-- lengan depan (memegang tali) -->
            <g class="pc-arm-f">
                <path d="M98 108 L122 132 L172 128" stroke="#3a2418" stroke-width="17.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                <path d="M98 108 L113 123" stroke="#3a2418" stroke-width="21.5" stroke-linecap="round" fill="none" />
                <path d="M98 108 L122 132 L172 128" stroke="#f6c49a" stroke-width="13" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                <path d="M98 108 L113 123" stroke="#c2ad78" stroke-width="17" stroke-linecap="round" fill="none" />
                <path d="M108 118 L118 128" stroke="#6f5f33" stroke-width="2" opacity="0.5" />
                <circle cx="174" cy="128" r="7.5" fill="#f6c49a" stroke="#3a2418" stroke-width="1.8" />
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
    90%,
    100% {
        transform: scaleY(1);
    }
    94% {
        transform: scaleY(0.08);
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
        animation: pc-blink 3.4s infinite;
    }
}
</style>
