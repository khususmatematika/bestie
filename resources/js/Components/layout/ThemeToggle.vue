<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { useTheme } from '@/Composables/useTheme';

const { theme, cycle } = useTheme();

const meta = computed(() => {
    switch (theme.value) {
        case 'light':
            return { icon: Sun, label: 'Tema terang' };
        case 'dark':
            return { icon: Moon, label: 'Tema gelap' };
        default:
            return { icon: Monitor, label: 'Tema mengikuti sistem' };
    }
});
</script>

<template>
    <button type="button" class="btn-icon" :title="`${meta.label} (klik untuk ganti)`" :aria-label="meta.label" @click="cycle">
        <component :is="meta.icon" class="size-[18px]" />
    </button>
</template>
