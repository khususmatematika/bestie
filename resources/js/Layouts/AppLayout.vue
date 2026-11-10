<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeftRight, ChevronDown, ChevronRight, LogOut, Menu, PanelLeftClose, PanelLeftOpen, School, UserRound, X } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ImpersonationBar from '@/Components/layout/ImpersonationBar.vue';
import NotificationBell from '@/Components/layout/NotificationBell.vue';
import SwitchAccountModal from '@/Components/layout/SwitchAccountModal.vue';
import ThemeToggle from '@/Components/layout/ThemeToggle.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import CredentialModal from '@/Components/ui/CredentialModal.vue';
import FlashToasts from '@/Components/ui/FlashToasts.vue';
import type { RoleKey } from '@/types';
import { navFor, roleHome, roleLabels } from './nav';

const page = usePage();

const auth = computed(() => page.props.auth);
const context = computed(() => page.props.context);
const activeRole = computed<RoleKey | null>(() => auth.value?.active_role ?? null);
const groups = computed(() => navFor(activeRole.value));
const roles = computed(() => auth.value?.roles ?? []);
const currentPath = computed(() => page.url.split('?')[0]);

const sidebarOpen = ref(false);

// Sidebar desktop dapat diciutkan menjadi ikon saja; pilihan diingat di peramban.
const collapsed = ref(false);

try {
    collapsed.value = localStorage.getItem('bestie.sidebar') === 'collapsed';
} catch {
    /* penyimpanan tidak tersedia */
}

function toggleCollapsed(): void {
    collapsed.value = !collapsed.value;

    try {
        localStorage.setItem('bestie.sidebar', collapsed.value ? 'collapsed' : 'open');
    } catch {
        /* abaikan */
    }
}
// Kategori menu dapat dilipat agar seluruh menu terlihat tanpa gulir; kategori berisi halaman aktif selalu terbuka.
const closedGroups = ref<string[]>([]);

try {
    const raw = localStorage.getItem('bestie.navgroups');
    if (raw) closedGroups.value = JSON.parse(raw) as string[];
} catch {
    /* abaikan */
}

function groupHasActive(group: { items: { href: string; match?: string }[] }): boolean {
    return group.items.some((i) => isActive(i.href, i.match));
}

function isGroupOpen(group: { title?: string; items: { href: string; match?: string }[] }): boolean {
    if (!group.title) return true;
    return !closedGroups.value.includes(group.title);
}

// Berpindah halaman membuka kategori halaman tujuan; selebihnya pengguna bebas melipat/membuka.
function openActiveGroup(): void {
    const active = groups.value.find((g) => g.title && groupHasActive(g));
    if (active?.title && closedGroups.value.includes(active.title)) {
        closedGroups.value = closedGroups.value.filter((t) => t !== active.title);
    }
}

onMounted(openActiveGroup);

function toggleGroup(title: string): void {
    closedGroups.value = closedGroups.value.includes(title) ? closedGroups.value.filter((t) => t !== title) : [...closedGroups.value, title];

    try {
        localStorage.setItem('bestie.navgroups', JSON.stringify(closedGroups.value));
    } catch {
        /* abaikan */
    }
}

// Tooltip cepat untuk rel ikon (sidebar ciut); posisi tetap agar tidak terpotong area gulir menu.
const tip = ref<{ label: string; top: number } | null>(null);

function showTip(event: Event, label: string): void {
    if (!collapsed.value) return;
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    tip.value = { label, top: rect.top + rect.height / 2 };
}

function hideTip(): void {
    tip.value = null;
}

const userMenuOpen = ref(false);
const switchOpen = ref(false);
const canSwitchAccount = computed(() => activeRole.value === 'admin' && !auth.value?.impersonating);

function openSwitchAccount(): void {
    userMenuOpen.value = false;
    switchOpen.value = true;
}
const userMenu = ref<HTMLElement | null>(null);

function isActive(href: string, match?: string): boolean {
    const base = match ?? href;
    return currentPath.value === href || currentPath.value.startsWith(`${base}/`) || (match !== undefined && currentPath.value.startsWith(match));
}

function switchPeriod(event: Event): void {
    const id = Number((event.target as HTMLSelectElement).value);
    router.post('/semester/switch', { period_id: id }, { preserveScroll: true });
}

function switchRole(role: RoleKey): void {
    userMenuOpen.value = false;
    if (role !== activeRole.value) router.post('/role/switch', { role });
}

function logout(): void {
    router.post('/logout');
}

function onDocClick(event: MouseEvent): void {
    if (userMenu.value && !userMenu.value.contains(event.target as Node)) userMenuOpen.value = false;
}

onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));

// Tutup sidebar mobile setiap berpindah halaman.
watch(
    () => page.url,
    () => {
        sidebarOpen.value = false;
        openActiveGroup();
    },
);
</script>

<template>
    <div class="min-h-full lg:grid lg:transition-[grid-template-columns] lg:duration-200" :class="collapsed ? 'lg:grid-cols-[4.5rem_1fr]' : 'lg:grid-cols-[17rem_1fr]'">
        <a href="#konten-utama" class="sr-only z-[70] rounded-lg bg-white px-4 py-2 text-sm font-bold text-primary-800 shadow-pop focus:not-sr-only focus:fixed focus:left-3 focus:top-3">Lewati ke konten</a>
        <!-- Sidebar -->
        <div v-if="sidebarOpen" class="fixed inset-0 z-30 bg-slate-950/50 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false" />

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-[17rem] -translate-x-full flex-col bg-primary-800 text-primary-100 transition-transform duration-200 lg:sticky lg:top-0 lg:z-10 lg:h-screen lg:w-auto lg:translate-x-0"
            :class="{ 'translate-x-0': sidebarOpen }"
            aria-label="Menu utama"
        >
            <div class="flex h-16 shrink-0 items-center justify-between px-5" :class="{ 'lg:justify-center lg:px-0': collapsed }">
                <Link :href="activeRole ? roleHome[activeRole] : '/'" class="flex items-center gap-3" aria-label="BESTIE — beranda">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-white/15 text-white">
                        <School class="size-5" />
                    </span>
                    <span class="leading-tight" :class="{ 'lg:hidden': collapsed }">
                        <span class="block text-base font-extrabold tracking-tight text-white">BESTIE</span>
                        <span class="block max-w-[10rem] truncate text-[11px] text-primary-200">{{ page.props.app.school }}</span>
                    </span>
                </Link>
                <button type="button" class="btn-icon text-primary-100 hover:bg-white/10 hover:text-white lg:hidden" aria-label="Tutup menu" @click="sidebarOpen = false">
                    <X class="size-5" />
                </button>
            </div>

            <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" :class="{ 'lg:space-y-0 lg:px-2 lg:py-2': collapsed }" @scroll.passive="hideTip">
                <div v-for="(group, gi) in groups" :key="gi" :class="{ 'lg:border-t lg:border-white/10 lg:pt-1 lg:mt-1': collapsed && gi > 0 }">
                    <button
                        v-if="group.title"
                        type="button"
                        class="mb-1 flex w-full items-center justify-between rounded-lg px-3 py-1.5 text-[11px] font-bold uppercase tracking-wider text-primary-300/80 transition hover:bg-white/5 hover:text-white"
                        :class="{ 'lg:hidden': collapsed }"
                        :aria-expanded="isGroupOpen(group)"
                        @click="toggleGroup(group.title)"
                    >
                        {{ group.title }}
                        <component :is="isGroupOpen(group) ? ChevronDown : ChevronRight" class="size-3.5" />
                    </button>
                    <ul class="space-y-0.5" :class="{ 'lg:space-y-0': collapsed }" v-show="isGroupOpen(group) || collapsed">
                        <li v-for="item in group.items" :key="item.href">
                            <Link
                                :href="item.href"
                                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                                :aria-label="item.label"
                                @mouseenter="showTip($event, item.label)"
                                @mouseleave="hideTip"
                                @focus="showTip($event, item.label)"
                                @blur="hideTip"
                                :class="[isActive(item.href, item.match) ? 'bg-white text-primary-800 shadow-soft' : 'text-primary-100 hover:bg-white/10 hover:text-white', collapsed ? 'lg:justify-center lg:px-0 lg:py-[clamp(4px,calc((100vh-400px)/28),14px)]' : '']"
                                :aria-current="isActive(item.href, item.match) ? 'page' : undefined"
                            >
                                <component :is="item.icon" class="size-[18px] shrink-0" />
                                <span :class="{ 'lg:hidden': collapsed }">{{ item.label }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="shrink-0 border-t border-white/10 px-5 py-3 text-[11px] text-primary-300 lg:hidden">BESTIE · Berkas Evaluasi Sumatif</div>
        </aside>

        <!-- Konten -->
        <div class="flex min-h-screen min-w-0 flex-col">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-surface/85 px-4 backdrop-blur sm:px-6">
                <button type="button" class="btn-icon lg:hidden" aria-label="Buka menu" @click="sidebarOpen = true">
                    <Menu class="size-5" />
                </button>
                <button type="button" class="btn-icon hidden lg:inline-flex" :aria-label="collapsed ? 'Perluas menu' : 'Ciutkan menu'" :aria-pressed="collapsed" @click="toggleCollapsed">
                    <component :is="collapsed ? PanelLeftOpen : PanelLeftClose" class="size-5" />
                </button>

                <!-- Pemilih semester / periode -->
                <div v-if="context && context.periods.length" class="flex min-w-0 items-center gap-2">
                    <label for="period-switch" class="sr-only">Periode asesmen</label>
                    <select
                        id="period-switch"
                        class="input !w-auto max-w-[15rem] truncate !py-1.5 !text-xs font-semibold sm:max-w-xs sm:!text-sm"
                        :value="context.period?.id"
                        @change="switchPeriod"
                    >
                        <option v-for="p in context.periods" :key="p.id" :value="p.id">
                            {{ p.label }}{{ p.semester_status === 'arsip' ? ' (arsip)' : '' }}{{ p.status === 'ditutup' ? ' · ditutup' : '' }}
                        </option>
                    </select>
                </div>
                <p v-else class="text-sm text-ink-faint">Belum ada periode asesmen</p>

                <div class="ml-auto flex items-center gap-1.5">
                    <NotificationBell />
                    <ThemeToggle />

                    <div ref="userMenu" class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-xl py-1 pl-1 pr-2 transition hover:bg-surface-2"
                            :aria-expanded="userMenuOpen"
                            aria-haspopup="menu"
                            @click.stop="userMenuOpen = !userMenuOpen"
                        >
                            <span class="flex size-8 items-center justify-center rounded-full bg-primary-600 text-xs font-bold text-white">
                                {{ auth?.user.initials }}
                            </span>
                            <span class="hidden text-left leading-tight sm:block">
                                <span class="block max-w-[10rem] truncate text-sm font-semibold text-ink">{{ auth?.user.name }}</span>
                                <span class="block text-[11px] text-ink-faint">{{ activeRole ? roleLabels[activeRole] : '' }}</span>
                            </span>
                            <ChevronDown class="size-4 text-ink-faint" />
                        </button>

                        <div v-if="userMenuOpen" role="menu" class="card absolute right-0 mt-2 w-64 overflow-hidden p-1.5 shadow-pop">
                            <div class="px-3 py-2">
                                <p class="truncate text-sm font-semibold text-ink">{{ auth?.user.name }}</p>
                                <p class="truncate text-xs text-ink-faint">{{ auth?.user.email ?? auth?.user.nip }}</p>
                            </div>

                            <template v-if="roles.length > 1">
                                <p class="px-3 pb-1 pt-2 text-[11px] font-bold uppercase tracking-wider text-ink-faint">Masuk sebagai</p>
                                <button
                                    v-for="role in roles"
                                    :key="role"
                                    type="button"
                                    role="menuitemradio"
                                    :aria-checked="role === activeRole"
                                    class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-surface-2"
                                    :class="role === activeRole ? 'font-bold text-primary-700 dark:text-primary-300' : 'text-ink'"
                                    @click="switchRole(role)"
                                >
                                    {{ roleLabels[role] }}
                                    <span v-if="role === activeRole" class="size-2 rounded-full bg-primary-600" />
                                </button>
                                <div class="my-1 border-t border-line" />
                            </template>

                            <button v-if="canSwitchAccount" type="button" role="menuitem" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-ink hover:bg-surface-2" @click="openSwitchAccount">
                                <ArrowLeftRight class="size-4 text-ink-faint" /> Ganti akun
                            </button>
                            <Link href="/profil" role="menuitem" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-ink hover:bg-surface-2" @click="userMenuOpen = false">
                                <UserRound class="size-4 text-ink-faint" /> Profil &amp; keamanan
                            </Link>
                            <button type="button" role="menuitem" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-500/10" @click="logout">
                                <LogOut class="size-4" /> Keluar
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Banner periode ditutup / arsip -->
            <div
                v-if="context?.period?.status === 'ditutup'"
                class="border-b border-warning-500/30 bg-warning-50 px-4 py-2 text-center text-xs font-semibold text-warning-700 dark:bg-warning-500/10 dark:text-warning-500 sm:px-6"
            >
                Periode ini sudah ditutup — data bersifat hanya-baca.
            </div>

            <main id="konten-utama" tabindex="-1" class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 outline-none sm:px-6 sm:py-8">
                <slot />
            </main>
        </div>

        <div
            v-if="tip && collapsed"
            class="pointer-events-none fixed left-[4.9rem] z-[60] hidden -translate-y-1/2 whitespace-nowrap rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs font-semibold text-white shadow-pop lg:block"
            :style="{ top: `${tip.top}px` }"
            role="tooltip"
        >
            {{ tip.label }}
        </div>

        <FlashToasts />
        <CredentialModal />
        <SwitchAccountModal v-if="canSwitchAccount" :open="switchOpen" @close="switchOpen = false" />
        <ImpersonationBar />
        <ConfirmDialog />
    </div>
</template>
