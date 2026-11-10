<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ArrowLeftRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import { roleLabels } from '@/Layouts/nav';

/**
 * Penanda tetap saat admin sedang masuk sebagai akun lain, dengan tombol kembali ke akun admin.
 */
const page = usePage();
const auth = computed(() => page.props.auth);
const busy = ref(false);
const until = computed(() => {
    const at = auth.value?.impersonating?.expires_at;

    return at ? new Date(at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : null;
});

function leave(): void {
    busy.value = true;
    router.post('/switch-account/leave', {}, { onFinish: () => (busy.value = false) });
}
</script>

<template>
    <div v-if="auth?.impersonating" role="status" class="fixed inset-x-3 bottom-3 z-[60] mx-auto flex max-w-xl flex-wrap items-center gap-x-3 gap-y-2 rounded-2xl border border-warning-500/50 bg-warning-50 px-4 py-2.5 text-sm text-ink shadow-pop dark:bg-slate-900 sm:bottom-4">
        <ArrowLeftRight class="size-4 shrink-0 text-warning-700 dark:text-warning-500" />
        <span class="min-w-0 flex-1">
            Masuk sebagai <strong class="font-bold">{{ auth.user.name }}</strong>
            <span class="text-ink-soft">({{ auth.active_role ? roleLabels[auth.active_role] : '' }}<template v-if="until">, berakhir pukul {{ until }}</template>)</span>
        </span>
        <button type="button" class="btn-primary !px-3.5 !py-1.5 text-xs" :disabled="busy" @click="leave">{{ busy ? 'Kembali…' : `Kembali ke ${auth.impersonating.admin_name ?? 'admin'}` }}</button>
    </div>
</template>
