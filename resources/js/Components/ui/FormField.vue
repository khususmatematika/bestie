<script setup lang="ts">
import { useId } from 'vue';

defineProps<{
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
}>();

const id = useId();
</script>

<template>
    <div>
        <label :for="id" class="label">
            {{ label }}<span v-if="required" class="ml-0.5 text-danger-500" aria-hidden="true">*</span>
        </label>
        <slot :id="id" :invalid="!!error" :described-by="error ? `${id}-err` : hint ? `${id}-hint` : undefined" />
        <p v-if="error" :id="`${id}-err`" class="error-text" role="alert">{{ error }}</p>
        <p v-else-if="hint" :id="`${id}-hint`" class="hint">{{ hint }}</p>
    </div>
</template>
