<script setup lang="ts">
import { FileText, UploadCloud, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = withDefaults(defineProps<{ modelValue: File | null; accept?: string; id?: string; label?: string; hint?: string; invalid?: boolean }>(), {
    accept: '',
    id: undefined,
    label: 'Pilih berkas',
    hint: 'atau tarik dan lepas berkas ke sini',
    invalid: false,
});
const emit = defineEmits<{ 'update:modelValue': [file: File | null] }>();

const input = ref<HTMLInputElement | null>(null);
const dragging = ref(false);

const size = computed(() => {
    const b = props.modelValue?.size ?? 0;
    return b >= 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`;
});

// Setelah formulir dikirim induk mengosongkan nilainya: kosongkan juga input aslinya.
watch(() => props.modelValue, (v) => {
    if (v === null && input.value) input.value.value = '';
});

function pick(files: FileList | null | undefined) {
    emit('update:modelValue', files?.[0] ?? null);
}

function drop(e: DragEvent) {
    dragging.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (!file) return;
    const allowed = props.accept.split(',').map((x) => x.trim().toLowerCase()).filter(Boolean);
    if (allowed.length && !allowed.some((ext) => file.name.toLowerCase().endsWith(ext))) return;
    emit('update:modelValue', file);
}

function clear() {
    emit('update:modelValue', null);
}
</script>

<template>
    <div>
        <label
            :for="id"
            class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-dashed px-4 py-3 transition-colors focus-within:ring-2 focus-within:ring-primary-500/40"
            :class="[
                invalid ? 'border-danger-500/60' : dragging ? 'border-primary-500 bg-primary-50 dark:bg-primary-500/10' : modelValue ? 'border-success-500/60 bg-success-50/60 dark:bg-success-500/10' : 'border-line hover:border-primary-400 hover:bg-surface-2',
            ]"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="drop"
        >
            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                <FileText v-if="modelValue" class="size-5" />
                <UploadCloud v-else class="size-5" />
            </span>
            <span class="min-w-0 flex-1">
                <template v-if="modelValue">
                    <span class="block truncate text-sm font-semibold text-ink">{{ modelValue.name }}</span>
                    <span class="block text-xs text-ink-faint">{{ size }} · klik untuk mengganti</span>
                </template>
                <template v-else>
                    <span class="block text-sm font-semibold text-ink">{{ label }}</span>
                    <span class="block text-xs text-ink-faint">{{ hint }}</span>
                </template>
            </span>
            <span v-if="!modelValue" class="btn-secondary btn-sm pointer-events-none shrink-0">Pilih berkas</span>
            <input :id="id" ref="input" type="file" :accept="accept" class="sr-only" @change="pick(($event.target as HTMLInputElement).files)" />
        </label>
        <button v-if="modelValue" type="button" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-danger-700 hover:underline dark:text-danger-500" @click="clear"><X class="size-3" /> Hapus pilihan</button>
    </div>
</template>
