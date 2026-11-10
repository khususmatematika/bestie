<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search, UserRound } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import Modal from '@/Components/ui/Modal.vue';
import { roleLabels } from '@/Layouts/nav';
import type { RoleKey } from '@/types';

/**
 * Ganti akun (khusus admin): cari guru/validator lalu masuk sebagai peran yang dipilih.
 */
const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: [] }>();

type Candidate = { id: number; name: string; nip: string | null; email: string | null; roles: RoleKey[] };

const query = ref('');
const results = ref<Candidate[]>([]);
const loading = ref(false);
const failed = ref(false);
const starting = ref<string | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;
let seq = 0;

async function load(): Promise<void> {
    const mine = ++seq;
    loading.value = true;
    failed.value = false;

    try {
        const res = await fetch(`/admin/switch-account/users?q=${encodeURIComponent(query.value.trim())}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error('gagal');
        const json = (await res.json()) as { data: Candidate[] };
        if (mine === seq) results.value = json.data;
    } catch {
        if (mine === seq) {
            failed.value = true;
            results.value = [];
        }
    } finally {
        if (mine === seq) loading.value = false;
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            query.value = '';
            void load();
        }
    },
);

watch(query, () => {
    clearTimeout(timer);
    timer = setTimeout(() => void load(), 250);
});

onBeforeUnmount(() => clearTimeout(timer));

function enter(user: Candidate, role: RoleKey): void {
    starting.value = `${user.id}-${role}`;
    router.post(`/admin/switch-account/${user.id}`, { role }, { onFinish: () => (starting.value = null) });
}
</script>

<template>
    <Modal :open="open" title="Ganti akun" description="Masuk sebagai guru atau validator untuk membantu atau memeriksa tampilannya. Semua tindakan tercatat atas nama Anda." size="md" @close="emit('close')">
        <div class="relative">
            <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-faint" />
            <input v-model="query" type="search" class="input pl-10" placeholder="Cari nama, NIP, atau email…" aria-label="Cari akun" autocomplete="off" />
        </div>

        <ul class="mt-3 max-h-[22rem] space-y-2 overflow-y-auto" aria-live="polite">
            <li v-for="u in results" :key="u.id" class="flex flex-wrap items-center gap-3 rounded-xl border border-line p-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300"><UserRound class="size-5" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-ink">{{ u.name }}</span>
                    <span class="block truncate text-xs text-ink-faint">{{ u.nip ?? u.email ?? '—' }}</span>
                </span>
                <span class="flex flex-wrap gap-1.5">
                    <button
                        v-for="r in u.roles"
                        :key="r"
                        type="button"
                        class="btn-secondary !px-3 !py-1.5 text-xs"
                        :disabled="starting !== null"
                        @click="enter(u, r)"
                    >
                        {{ starting === `${u.id}-${r}` ? 'Masuk…' : `Sebagai ${roleLabels[r]}` }}
                    </button>
                </span>
            </li>
            <li v-if="!loading && !failed && !results.length" class="py-8 text-center text-sm text-ink-soft">Tidak ada akun guru/validator aktif yang cocok.</li>
            <li v-if="failed" class="py-8 text-center text-sm text-danger-600">Gagal memuat daftar akun. Coba lagi.</li>
            <li v-if="loading" class="py-3 text-center text-xs text-ink-faint">Memuat…</li>
        </ul>
    </Modal>
</template>
