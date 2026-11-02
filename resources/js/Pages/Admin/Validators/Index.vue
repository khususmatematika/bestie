<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { LoaderCircle, Search, ShieldCheck, ShieldOff, SlidersHorizontal } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useConfirm } from '@/Composables/useConfirm';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Modal from '@/Components/ui/Modal.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StatusBadge from '@/Components/ui/StatusBadge.vue';

interface Row {
    id: number;
    name: string;
    email: string | null;
    nip: string | null;
    is_validator: boolean;
    subject_ids: number[];
}

const props = defineProps<{
    teachers: Row[];
    subjects: { id: number; name: string; code: string }[];
    semester: { id: number; label: string } | null;
    filters: { search: string };
}>();

const { confirm } = useConfirm();
const search = ref(props.filters.search);
const scopeFor = ref<Row | null>(null);
const selected = ref<number[]>([]);
const saving = ref(false);

let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get('/admin/validators', { search: value || undefined }, { preserveState: true, replace: true }), 350);
});

async function toggle(t: Row): Promise<void> {
    const ok = await confirm({
        title: t.is_validator ? `Cabut peran validator ${t.name}?` : `Jadikan ${t.name} validator?`,
        message: t.is_validator
            ? 'Guru ini tidak lagi dapat menerima paket soal untuk divalidasi. Cakupan mapelnya ikut dihapus.'
            : 'Guru ini akan memiliki dua peran (Guru dan Validator) dan dapat berpindah peran dari menu profil.',
        confirmLabel: t.is_validator ? 'Cabut peran' : 'Jadikan validator',
        danger: t.is_validator,
    });

    if (ok) router.post(`/admin/validators/${t.id}/toggle`, {}, { preserveScroll: true });
}

function openScope(t: Row): void {
    scopeFor.value = t;
    selected.value = [...t.subject_ids];
}

function saveScope(): void {
    if (!scopeFor.value) return;

    router.put(
        `/admin/validators/${scopeFor.value.id}/scopes`,
        { subject_ids: selected.value },
        {
            preserveScroll: true,
            onStart: () => (saving.value = true),
            onFinish: () => (saving.value = false),
            onSuccess: () => (scopeFor.value = null),
        },
    );
}
</script>

<template>
    <Head title="Validator" />
    <PageHeader title="Penunjukan validator" description="Langkah 2: pilih validator dari guru yang sudah terdaftar. Guru memilih validator saat mengirim paket soal." />

    <section class="card overflow-hidden">
        <div class="border-b border-line p-4">
            <div class="relative max-w-sm">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-faint" />
                <input v-model="search" type="search" class="input pl-10" placeholder="Cari guru…" aria-label="Cari guru" />
            </div>
        </div>

        <div v-if="teachers.length" class="table-wrap">
            <table class="table">
                <thead><tr><th>Guru</th><th>Status</th><th>Cakupan mapel ({{ semester?.label ?? 'tanpa semester' }})</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>
                    <tr v-for="t in teachers" :key="t.id">
                        <td>
                            <p class="font-bold">{{ t.name }}</p>
                            <p class="text-xs text-ink-faint">{{ t.nip ?? t.email }}</p>
                        </td>
                        <td><StatusBadge v-if="t.is_validator" status="validator" /><span v-else class="text-xs text-ink-faint">Guru</span></td>
                        <td class="text-sm text-ink-soft">
                            <template v-if="t.is_validator">
                                {{ t.subject_ids.length ? `${t.subject_ids.length} mapel dipilih` : 'Semua mapel' }}
                            </template>
                            <template v-else>—</template>
                        </td>
                        <td>
                            <div class="flex justify-end gap-1.5">
                                <button v-if="t.is_validator && semester" type="button" class="btn-secondary btn-sm" @click="openScope(t)"><SlidersHorizontal class="size-3.5" /> Cakupan</button>
                                <button type="button" class="btn-sm" :class="t.is_validator ? 'btn-secondary' : 'btn-primary'" @click="toggle(t)">
                                    <component :is="t.is_validator ? ShieldOff : ShieldCheck" class="size-3.5" />
                                    {{ t.is_validator ? 'Cabut' : 'Jadikan validator' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else title="Belum ada guru" description="Input atau impor akun guru terlebih dahulu pada menu Akun Guru." />
    </section>

    <Modal :open="!!scopeFor" :title="`Cakupan validasi — ${scopeFor?.name ?? ''}`" description="Kosongkan semua centang jika validator boleh menerima paket dari semua mapel." @close="scopeFor = null">
        <ul class="grid max-h-80 gap-1 overflow-y-auto sm:grid-cols-2">
            <li v-for="s in subjects" :key="s.id">
                <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink hover:bg-surface-2">
                    <input v-model="selected" type="checkbox" :value="s.id" class="size-4 accent-primary-600" />
                    <span>{{ s.name }} <span class="font-mono text-[11px] text-ink-faint">{{ s.code }}</span></span>
                </label>
            </li>
        </ul>
        <template #footer>
            <button type="button" class="btn-secondary" @click="scopeFor = null">Batal</button>
            <button type="button" class="btn-primary" :disabled="saving" @click="saveScope">
                <LoaderCircle v-if="saving" class="size-4 animate-spin" /> Simpan
            </button>
        </template>
    </Modal>
</template>
