<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bell, BellRing, CheckCheck, CircleCheck, RefreshCw, RotateCcw, Send, Timer } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { formatDateTime } from '@/Composables/useFormat';

interface Note {
    id: string;
    title: string;
    body: string;
    url: string;
    kind: string;
    read: boolean;
    created_at: string | null;
}

const page = usePage();
const unread = ref(Number(page.props.notifications_unread ?? 0));
const open = ref(false);
const items = ref<Note[]>([]);
const loading = ref(false);
const root = ref<HTMLElement | null>(null);
let timer: ReturnType<typeof setInterval> | undefined;

const icons: Record<string, unknown> = { paket_baru: Send, minta_ganti: RotateCcw, unggah_ulang: RefreshCw, fix: CircleCheck, tenggat: Timer };
const label = computed(() => (unread.value > 0 ? `Notifikasi, ${unread.value} belum dibaca` : 'Notifikasi'));

function csrf(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}

async function call(url: string, method = 'GET'): Promise<{ unread: number; items?: Note[] } | null> {
    try {
        const res = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': csrf() },
        });

        return res.ok ? await res.json() : null;
    } catch {
        return null;
    }
}

async function refresh(withList: boolean): Promise<void> {
    if (withList) loading.value = true;
    const data = await call('/notifications');
    loading.value = false;

    if (data) {
        unread.value = data.unread;
        if (withList) items.value = data.items ?? [];
    }
}

async function toggle(): Promise<void> {
    open.value = !open.value;
    if (open.value) await refresh(true);
}

async function go(n: Note): Promise<void> {
    open.value = false;
    if (!n.read) {
        const data = await call(`/notifications/${n.id}/read`, 'POST');
        if (data) unread.value = data.unread;
    }
    router.visit(n.url);
}

async function readAll(): Promise<void> {
    const data = await call('/notifications/read-all', 'POST');
    if (data) {
        unread.value = 0;
        items.value = items.value.map((n) => ({ ...n, read: true }));
    }
}

function onDocClick(e: MouseEvent): void {
    if (root.value && !root.value.contains(e.target as Node)) open.value = false;
}

function onKey(e: KeyboardEvent): void {
    if (e.key === 'Escape') open.value = false;
}

// Hitungan dari server ikut diperbarui tiap berpindah halaman; selebihnya dicek berkala.
watch(() => page.props.notifications_unread, (v) => (unread.value = Number(v ?? 0)));

onMounted(() => {
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onKey);
    timer = setInterval(() => {
        if (!document.hidden) void refresh(open.value);
    }, 90_000);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick);
    document.removeEventListener('keydown', onKey);
    if (timer) clearInterval(timer);
});
</script>

<template>
    <div ref="root" class="relative">
        <button type="button" class="btn-icon relative" :aria-label="label" aria-haspopup="dialog" :aria-expanded="open" @click.stop="toggle">
            <component :is="unread > 0 ? BellRing : Bell" class="size-[18px]" />
            <span v-if="unread > 0" class="absolute -right-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full bg-danger-600 px-1 text-[10px] font-bold leading-4 text-white" aria-hidden="true">{{ unread > 99 ? '99+' : unread }}</span>
        </button>

        <div v-if="open" role="dialog" aria-label="Notifikasi" class="card absolute right-0 z-40 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden shadow-pop">
            <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
                <p class="text-sm font-bold text-ink">Notifikasi</p>
                <button v-if="unread > 0" type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline" @click="readAll"><CheckCheck class="size-3.5" /> Tandai semua dibaca</button>
            </div>

            <p v-if="loading" class="px-4 py-6 text-center text-sm text-ink-soft" role="status">Memuat…</p>
            <p v-else-if="!items.length" class="px-4 py-8 text-center text-sm text-ink-soft">Belum ada notifikasi.</p>
            <ul v-else class="max-h-96 divide-y divide-line overflow-y-auto">
                <li v-for="n in items" :key="n.id">
                    <button type="button" class="flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-surface-2" :class="{ 'bg-primary-50/60 dark:bg-primary-500/10': !n.read }" @click="go(n)">
                        <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-surface-2 text-primary-600"><component :is="icons[n.kind] ?? Bell" class="size-4" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm text-ink" :class="n.read ? 'font-medium' : 'font-bold'">{{ n.title }}</span>
                            <span class="mt-0.5 block text-xs text-ink-soft">{{ n.body }}</span>
                            <span class="mt-1 block text-[11px] text-ink-faint">{{ formatDateTime(n.created_at) }}</span>
                        </span>
                        <span v-if="!n.read" class="mt-1.5 size-2 shrink-0 rounded-full bg-primary-600" aria-label="Belum dibaca" />
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
