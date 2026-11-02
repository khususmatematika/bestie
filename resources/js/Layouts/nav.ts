import {
    Archive,
    BookOpen,
    CalendarClock,
    CalendarDays,
    FolderOpen,
    Inbox,
    GraduationCap,
    Layers,
    LayoutDashboard,
    LifeBuoy,
    ScrollText,
    Settings,
    ShieldCheck,
    Users,
    UserCog,
} from '@lucide/vue';
import type { Component } from 'vue';
import type { RoleKey } from '@/types';

export interface NavItem {
    label: string;
    href: string;
    icon: Component;
    /** Path awalan untuk menandai menu aktif. */
    match?: string;
}

export interface NavGroup {
    title?: string;
    items: NavItem[];
}

export const roleLabels: Record<RoleKey, string> = {
    admin: 'Admin',
    validator: 'Validator Soal',
    teacher: 'Guru',
};

export const roleHome: Record<RoleKey, string> = {
    admin: '/admin/dashboard',
    validator: '/validator/dashboard',
    teacher: '/teacher/dashboard',
};

export function navFor(role: RoleKey | null): NavGroup[] {
    switch (role) {
        case 'admin':
            return [
                { items: [{ label: 'Dashboard', href: '/admin/dashboard', icon: LayoutDashboard }] },
                {
                    title: 'Akademik',
                    items: [
                        { label: 'Tahun Ajaran', href: '/admin/academic-years', icon: CalendarDays },
                        { label: 'Semester', href: '/admin/semesters', icon: Layers },
                        { label: 'Periode Asesmen', href: '/admin/periods', icon: CalendarClock },
                        { label: 'Mapel', href: '/admin/subjects', icon: BookOpen },
                        { label: 'Tingkat', href: '/admin/grade-levels', icon: GraduationCap },
                    ],
                },
                {
                    title: 'Pengguna',
                    items: [
                        { label: 'Akun Guru', href: '/admin/teachers', icon: Users, match: '/admin/teachers' },
                        { label: 'Validator', href: '/admin/validators', icon: ShieldCheck },
                        { label: 'Penugasan Mengajar', href: '/admin/assignments', icon: UserCog },
                    ],
                },
                {
                    title: 'Soal',
                    items: [
                        { label: 'Monitoring Paket', href: '/admin/packages', icon: FolderOpen, match: '/admin/packages' },
                        { label: 'Arsip Soal', href: '/admin/archive', icon: Archive },
                    ],
                },
                {
                    title: 'Sistem',
                    items: [
                        { label: 'Pengaturan', href: '/admin/settings', icon: Settings },
                        { label: 'Log Aktivitas', href: '/admin/activity-logs', icon: ScrollText },
                        { label: 'Panduan', href: '/panduan', icon: LifeBuoy },
                    ],
                },
            ];
        case 'validator':
            return [
                {
                    items: [
                        { label: 'Dashboard', href: '/validator/dashboard', icon: LayoutDashboard },
                        { label: 'Inbox Telaah', href: '/validator/inbox', icon: Inbox, match: '/validator/inbox' },
                    ],
                },
                { items: [{ label: 'Panduan', href: '/panduan', icon: LifeBuoy }] },
            ];
        case 'teacher':
            return [
                {
                    items: [
                        { label: 'Dashboard', href: '/teacher/dashboard', icon: LayoutDashboard },
                        { label: 'Paket Soal Saya', href: '/teacher/packages', icon: FolderOpen, match: '/teacher/packages' },
                    ],
                },
                { items: [{ label: 'Panduan', href: '/panduan', icon: LifeBuoy }] },
            ];
        default:
            return [];
    }
}
