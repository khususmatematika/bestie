<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Ilustrasi orisinal: dua guru memindahkan kartu login. Yang di depan (mode "pull") menarik dengan tali sambil
 * berjalan mundur menghadap kartu, yang di belakang (mode "push") mendorong sambil berjalan maju. Saat halaman dibuka mereka berjalan
 * (siklus langkah dengan lutut menekuk), lalu berhenti, bernapas, dan berkedip. Bila perangkat memilih
 * "kurangi gerakan", yang tampil hanya pose akhir.
 */
withDefaults(defineProps<{ mode?: 'pull' | 'push' }>(), { mode: 'pull' });

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
    <template v-if="mode === 'pull'">
    <svg viewBox="0 0 260 250" overflow="visible" class="pc pc-pull" :class="{ 'pc-walking': walking }" role="img" aria-label="Ilustrasi guru menarik kartu login sambil berjalan mundur" xmlns="http://www.w3.org/2000/svg">
        <g transform="translate(60 0)">
        <ellipse cx="98" cy="236" rx="52" ry="5.5" fill="rgba(0,0,0,0.28)" />
        <defs>
            <linearGradient id="pc-shirt-pull" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0" stop-color="#d2bf8e" />
                <stop offset="1" stop-color="#b9a46c" />
            </linearGradient>
        </defs>

        <g class="pc-body">
            <g class="pc-thigh pc-leg-b">
                <path d="M100 154 L100 190" stroke="#3a2418" stroke-width="27" stroke-linecap="round" fill="none" />
                <path d="M100 154 L100 190" stroke="#8f7c4c" stroke-width="23" stroke-linecap="round" fill="none" />
                <g class="pc-shin">
                    <path d="M100 190 L100 226" stroke="#3a2418" stroke-width="23" stroke-linecap="round" fill="none" />
                    <path d="M100 190 L100 226" stroke="#8f7c4c" stroke-width="19" stroke-linecap="round" fill="none" />
                    <path d="M108 221 L108 233 L80 234 Q75 234 76 229 Q79 223 93 221 Z" fill="#2b1a12" stroke="#3a2418" stroke-width="1.6" stroke-linejoin="round" />
                </g>
            </g>
            <g class="pc-thigh pc-leg-f">
                <path d="M100 154 L100 190" stroke="#3a2418" stroke-width="27" stroke-linecap="round" fill="none" />
                <path d="M100 154 L100 190" stroke="#a08d5a" stroke-width="23" stroke-linecap="round" fill="none" />
                <g class="pc-shin">
                    <path d="M100 190 L100 226" stroke="#3a2418" stroke-width="23" stroke-linecap="round" fill="none" />
                    <path d="M100 190 L100 226" stroke="#a08d5a" stroke-width="19" stroke-linecap="round" fill="none" />
                    <path d="M108 221 L108 233 L80 234 Q75 234 76 229 Q79 223 93 221 Z" fill="#2b1a12" stroke="#3a2418" stroke-width="1.6" stroke-linejoin="round" />
                </g>
            </g>

            <g class="pc-upper pc-lean-pull">
                <g class="pc-arm-b">
                    <path d="M98 114 L86 138 L70 148" stroke="#3a2418" stroke-width="16.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                    <path d="M98 114 L93 127" stroke="#3a2418" stroke-width="21" stroke-linecap="round" fill="none" />
                    <path d="M98 114 L86 138 L70 148" stroke="#e6ad82" stroke-width="12.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                    <path d="M98 114 L93 127" stroke="#c2ad78" stroke-width="17" stroke-linecap="round" fill="none" />
                    <circle cx="70" cy="148" r="7.2" fill="#e6ad82" stroke="#3a2418" stroke-width="1.8" />
                </g>
                <!-- badan tegak: kemeja khaki dimasukkan ke celana -->
                <path d="M83 108 Q100 100 117 108 L116 156 Q100 162 84 156 Z" fill="url(#pc-shirt-pull)" stroke="#3a2418" stroke-width="2.2" stroke-linejoin="round" />
                <path d="M112 112 L115 156" stroke="#6f5f33" stroke-width="4" opacity="0.3" stroke-linecap="round" />
                <path d="M90 100 L100 118 L108 99 Q99 94 90 100 Z" fill="#dccda4" stroke="#8f7f52" stroke-width="1" stroke-linejoin="round" />
                <path d="M100 118 L100 158" stroke="#6f5f33" stroke-width="1.4" />
                <circle cx="100" cy="127" r="1.5" fill="#f3ead0" />
                <circle cx="100" cy="139" r="1.5" fill="#f3ead0" />
                <circle cx="100" cy="150" r="1.5" fill="#f3ead0" />
                <path d="M86 130 L97 132 L97 142 Q91 146 86 142 Z" fill="#c2ad78" stroke="#6f5f33" stroke-width="0.9" />
                <rect x="104" y="125" width="11" height="4.6" rx="1.1" fill="#f3f4f8" stroke="#aab2cf" stroke-width="0.6" />
                <path d="M83.5 151 Q100 157.5 116.5 151 L116.5 158 Q100 164.5 83.5 158 Z" fill="#2b1a12" stroke="#3a2418" stroke-width="1.2" stroke-linejoin="round" />
                <rect x="88" y="152.5" width="9.5" height="7.4" rx="1.6" fill="#e9b830" stroke="#a97a0c" stroke-width="0.9" />
                <path d="M91 156.2 H95" stroke="#a97a0c" stroke-width="1.2" stroke-linecap="round" />
                <g class="pc-arm-f">
                    <path d="M104 114 L92 137 L66 144" stroke="#3a2418" stroke-width="16.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                    <path d="M104 114 L98 126" stroke="#3a2418" stroke-width="21" stroke-linecap="round" fill="none" />
                    <path d="M104 114 L92 137 L66 144" stroke="#f6c49a" stroke-width="12.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                    <path d="M104 114 L98 126" stroke="#c2ad78" stroke-width="17" stroke-linecap="round" fill="none" />
                    <circle cx="66" cy="144" r="7.2" fill="#f6c49a" stroke="#3a2418" stroke-width="1.8" />
                </g>
            <g class="pc-head">
                <g transform="translate(24 8)">
                <g transform="translate(74 100) scale(1.3) translate(-74 -100)">
                    <rect x="68" y="88" width="14" height="14" rx="5" fill="#e0a070" stroke="#3a2418" stroke-width="1.6" />
                    <ellipse cx="94" cy="76" rx="4.6" ry="6" fill="#f0b98a" stroke="#3a2418" stroke-width="1.6" />
                    <path d="M93 73 Q96 76 93.4 79.5" stroke="#d58a5d" stroke-width="1.3" fill="none" stroke-linecap="round" />
                    <path d="M50 68 Q49 48 74 47 Q98 48 97 68 Q97 90 76 94 Q54 94 50 68 Z" fill="#f2c093" stroke="#3a2418" stroke-width="2" stroke-linejoin="round" />
                    <!-- rambut hitam rapi, disisir ke belakang, cambang tipis -->
                    <path d="M48 70 Q44 42 66 38 Q86 32 97 48 Q101 58 98 74 Q96 62 90 56 Q80 49 66 50 Q57 53 54 64 Q53 70 52 76 Q49 76 48 70 Z" fill="#141015" stroke="#3a2418" stroke-width="2" stroke-linejoin="round" />
                    <path d="M58 44 Q70 38 86 42" stroke="#3a3138" stroke-width="2.4" stroke-linecap="round" fill="none" />
                    <path d="M92 60 Q96 66 95 78" stroke="#141015" stroke-width="2.6" stroke-linecap="round" fill="none" />
                    <!-- alis tebal -->
                    <path d="M55.5 62.5 Q62 58.5 69 62" stroke="#141015" stroke-width="2.8" stroke-linecap="round" fill="none" />
                    <path d="M76.5 62 Q83 58.5 89 62.5" stroke="#141015" stroke-width="2.6" stroke-linecap="round" fill="none" />
                    <g class="pc-eye">
                        <ellipse cx="62.5" cy="72" rx="3.4" ry="4.6" fill="#1b110c" />
                        <circle cx="61.3" cy="70.2" r="1.3" fill="#ffffff" />
                        <ellipse cx="82" cy="72" rx="3.2" ry="4.4" fill="#1b110c" />
                        <circle cx="80.9" cy="70.2" r="1.2" fill="#ffffff" />
                    </g>
                    <ellipse cx="58" cy="83" rx="4" ry="2.4" fill="#e9857a" opacity="0.4" />
                    <ellipse cx="88" cy="83" rx="3.4" ry="2.2" fill="#e9857a" opacity="0.35" />
                    <path d="M72 79.5 Q75 82.4 72.2 84.4" stroke="#c9804f" stroke-width="1.5" stroke-linecap="round" fill="none" />
                    <!-- kumis tipis dan senyum -->
                    <path d="M62.5 85.6 Q72 83.2 81.5 85.6 Q72 86.8 62.5 85.6 Z" fill="#141015" />
                    <path d="M65 88.6 Q72.5 94 80.5 88.6" stroke="#8f3a32" stroke-width="2.2" stroke-linecap="round" fill="none" />
                </g>
                </g>
            </g>
            </g>
        </g>
        <!-- tali ke kartu (digambar di sisi kiri; seluruh karakter dicerminkan sehingga menghadap kartu) -->
        <path d="M62 145 Q0 164 -60 148" stroke="#c79348" stroke-width="3.4" stroke-linecap="round" fill="none" />
        <path d="M54 146 Q0 163 -60 148" stroke="#e6bb73" stroke-width="1.2" stroke-linecap="round" stroke-dasharray="3 4" fill="none" />
        <circle cx="-61" cy="148" r="4.5" fill="#c79348" />
        </g>
    </svg>
    </template>
    <template v-else>
    <svg viewBox="0 0 260 250" overflow="visible" class="pc pc-push" :class="{ 'pc-walking': walking }" role="img" aria-label="Ilustrasi guru mendorong kartu login" xmlns="http://www.w3.org/2000/svg">
        <ellipse cx="98" cy="236" rx="52" ry="5.5" fill="rgba(0,0,0,0.28)" />
        <defs>
            <linearGradient id="pc-shirt-push" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0" stop-color="#d2bf8e" />
                <stop offset="1" stop-color="#b9a46c" />
            </linearGradient>
        </defs>

        <g class="pc-body">
            <g class="pc-thigh pc-leg-b">
                <path d="M100 154 L100 190" stroke="#3a2418" stroke-width="27" stroke-linecap="round" fill="none" />
                <path d="M100 154 L100 190" stroke="#8f7c4c" stroke-width="23" stroke-linecap="round" fill="none" />
                <g class="pc-shin">
                    <path d="M100 190 L100 226" stroke="#3a2418" stroke-width="23" stroke-linecap="round" fill="none" />
                    <path d="M100 190 L100 226" stroke="#8f7c4c" stroke-width="19" stroke-linecap="round" fill="none" />
                    <path d="M108 221 L108 233 L80 234 Q75 234 76 229 Q79 223 93 221 Z" fill="#2b1a12" stroke="#3a2418" stroke-width="1.6" stroke-linejoin="round" />
                </g>
            </g>
            <g class="pc-thigh pc-leg-f">
                <path d="M100 154 L100 190" stroke="#3a2418" stroke-width="27" stroke-linecap="round" fill="none" />
                <path d="M100 154 L100 190" stroke="#a08d5a" stroke-width="23" stroke-linecap="round" fill="none" />
                <g class="pc-shin">
                    <path d="M100 190 L100 226" stroke="#3a2418" stroke-width="23" stroke-linecap="round" fill="none" />
                    <path d="M100 190 L100 226" stroke="#a08d5a" stroke-width="19" stroke-linecap="round" fill="none" />
                    <path d="M108 221 L108 233 L80 234 Q75 234 76 229 Q79 223 93 221 Z" fill="#2b1a12" stroke="#3a2418" stroke-width="1.6" stroke-linejoin="round" />
                </g>
            </g>

            <g class="pc-upper pc-lean-push">
                <g class="pc-arm-b">
                <path d="M96 114 L56 122 L14 111" stroke="#3a2418" stroke-width="16.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                <path d="M96 114 L84 117" stroke="#3a2418" stroke-width="21" stroke-linecap="round" fill="none" />
                <path d="M96 114 L56 122 L14 111" stroke="#e6ad82" stroke-width="12.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                <path d="M96 114 L84 117" stroke="#c2ad78" stroke-width="17" stroke-linecap="round" fill="none" />
                <circle cx="13" cy="110" r="7.2" fill="#e6ad82" stroke="#3a2418" stroke-width="1.8" />
                </g>
                <!-- badan tegak: kemeja khaki dimasukkan ke celana -->
                <path d="M83 108 Q100 100 117 108 L116 156 Q100 162 84 156 Z" fill="url(#pc-shirt-push)" stroke="#3a2418" stroke-width="2.2" stroke-linejoin="round" />
                <path d="M112 112 L115 156" stroke="#6f5f33" stroke-width="4" opacity="0.3" stroke-linecap="round" />
                <path d="M90 100 L100 118 L108 99 Q99 94 90 100 Z" fill="#dccda4" stroke="#8f7f52" stroke-width="1" stroke-linejoin="round" />
                <path d="M100 118 L100 158" stroke="#6f5f33" stroke-width="1.4" />
                <circle cx="100" cy="127" r="1.5" fill="#f3ead0" />
                <circle cx="100" cy="139" r="1.5" fill="#f3ead0" />
                <circle cx="100" cy="150" r="1.5" fill="#f3ead0" />
                <path d="M86 130 L97 132 L97 142 Q91 146 86 142 Z" fill="#c2ad78" stroke="#6f5f33" stroke-width="0.9" />
                <rect x="104" y="125" width="11" height="4.6" rx="1.1" fill="#f3f4f8" stroke="#aab2cf" stroke-width="0.6" />
                <path d="M83.5 151 Q100 157.5 116.5 151 L116.5 158 Q100 164.5 83.5 158 Z" fill="#2b1a12" stroke="#3a2418" stroke-width="1.2" stroke-linejoin="round" />
                <rect x="88" y="152.5" width="9.5" height="7.4" rx="1.6" fill="#e9b830" stroke="#a97a0c" stroke-width="0.9" />
                <path d="M91 156.2 H95" stroke="#a97a0c" stroke-width="1.2" stroke-linecap="round" />
                <g class="pc-arm-f">
                <path d="M100 115 L58 126 L12 114" stroke="#3a2418" stroke-width="16.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                <path d="M100 115 L88 119" stroke="#3a2418" stroke-width="21" stroke-linecap="round" fill="none" />
                <path d="M100 115 L58 126 L12 114" stroke="#f6c49a" stroke-width="12.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                <path d="M100 115 L88 119" stroke="#c2ad78" stroke-width="17" stroke-linecap="round" fill="none" />
                <circle cx="11" cy="113" r="7.2" fill="#f6c49a" stroke="#3a2418" stroke-width="1.8" />
                </g>
            <g class="pc-head">
                <g transform="translate(24 8)">
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
            </g>
            </g>
        </g>
    </svg>
    </template>
</template>

<style>
.pc {
    overflow: visible;
    filter: drop-shadow(0 10px 14px rgba(0, 0, 0, 0.25));
}

.pc-thigh,
.pc-shin,
.pc-upper,
.pc-arm-b,
.pc-arm-f,
.pc-head,
.pc-body {
    transform-box: view-box;
    transition: transform 0.5s ease-out;
}

.pc-thigh {
    transform-origin: 100px 154px;
}

.pc-shin {
    transform-origin: 100px 190px;
}

.pc-upper {
    transform-origin: 100px 156px;
}

.pc-pull {
    transform: scaleX(-1);
}

.pc-lean-pull {
    transform: rotate(7deg);
}

.pc-lean-push {
    transform: rotate(-12deg);
}

/* berdiri: sedikit melangkah (satu kaki di depan) */
.pc-leg-f {
    transform: rotate(7deg);
}

.pc-leg-b {
    transform: rotate(-9deg);
}

.pc-arm-b,
.pc-arm-f {
    transform-origin: 100px 114px;
}

.pc-head {
    transform-origin: 98px 108px;
}

.pc-eye {
    transform-box: fill-box;
    transform-origin: center;
}

@keyframes pc-thigh-walk {
    0%,
    100% {
        transform: rotate(27deg);
    }
    50% {
        transform: rotate(-24deg);
    }
}

@keyframes pc-shin-walk {
    0%,
    100% {
        transform: rotate(0deg);
    }
    25% {
        transform: rotate(-6deg);
    }
    50% {
        transform: rotate(-10deg);
    }
    68% {
        transform: rotate(-58deg);
    }
    85% {
        transform: rotate(-30deg);
    }
}

@keyframes pc-bob {
    0%,
    50%,
    100% {
        transform: translateY(0);
    }
    25%,
    75% {
        transform: translateY(-3px);
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

@keyframes pc-push-sway {
    0%,
    100% {
        transform: rotate(-12deg);
    }
    50% {
        transform: rotate(-14.5deg);
    }
}

@keyframes pc-pull-sway {
    0%,
    100% {
        transform: rotate(7deg);
    }
    50% {
        transform: rotate(9.5deg);
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
    .pc-walking .pc-thigh {
        animation: pc-thigh-walk 1.2s ease-in-out infinite;
    }

    .pc-walking .pc-leg-b {
        animation-delay: -0.6s;
    }

    .pc-walking .pc-shin {
        animation: pc-shin-walk 1.2s ease-in-out infinite;
    }

    .pc-walking .pc-leg-b .pc-shin {
        animation-delay: -0.6s;
    }

    /* yang di depan berjalan mundur: siklus langkah diputar balik */
    .pc-pull.pc-walking .pc-thigh,
    .pc-pull.pc-walking .pc-shin {
        animation-direction: reverse;
    }

    .pc-walking .pc-body {
        animation: pc-bob 0.6s ease-in-out infinite;
    }

    .pc:not(.pc-walking) .pc-head {
        animation: pc-breathe 3.2s ease-in-out infinite;
    }

    .pc:not(.pc-walking) .pc-lean-push {
        animation: pc-push-sway 2.4s ease-in-out infinite;
    }

    .pc:not(.pc-walking) .pc-lean-pull {
        animation: pc-pull-sway 2.8s ease-in-out infinite;
    }

    .pc-eye {
        animation: pc-blink 3.4s infinite;
    }
}
</style>
