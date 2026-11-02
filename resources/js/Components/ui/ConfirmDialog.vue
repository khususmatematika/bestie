<script setup lang="ts">
import { useConfirm } from '@/Composables/useConfirm';
import Modal from './Modal.vue';

const { state, answer } = useConfirm();
</script>

<template>
    <Modal :open="state.open" :title="state.title" size="sm" @close="answer(false)">
        <p class="text-sm leading-relaxed text-ink-soft">{{ state.message }}</p>
        <template #footer>
            <button type="button" class="btn-secondary" @click="answer(false)">Batal</button>
            <button type="button" :class="state.danger ? 'btn-danger' : 'btn-primary'" @click="answer(true)">
                {{ state.confirmLabel }}
            </button>
        </template>
    </Modal>
</template>
