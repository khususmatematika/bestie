import '@inertiajs/core';

export type RoleKey = 'admin' | 'validator' | 'teacher';

export interface AuthUser {
    id: number;
    name: string;
    email: string | null;
    nip: string | null;
    initials: string;
    locale: string;
    theme: 'light' | 'dark' | 'system';
    must_change_password: boolean;
}

export interface PeriodOption {
    id: number;
    label: string;
    status: 'buka' | 'ditutup';
    semester_status: 'rencana' | 'aktif' | 'arsip' | null;
}

export interface Credential {
    name: string;
    email: string | null;
    nip: string | null;
    password: string;
    given?: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface PackageSummary {
    id: number;
    title: string;
    status: string;
    status_label: string;
    progress: number;
    total_items: number;
    approved_items: number;
    subject: string;
    subject_code: string;
    grade: string;
    period: string;
    teacher: string;
    validator: string | null;
    is_self_validated: boolean;
    submitted_at: string | null;
    first_reviewed_at: string | null;
    fixed_at: string | null;
    locked_at: string | null;
    late: { upload: 'terlambat' | 'lewat_tenggat' | null; revision: 'terlambat' | 'lewat_tenggat' | null; revision_items: number };
}

export interface ItemBlueprint {
    cp: string;
    materi: string;
    submateri: string;
    indikator: string;
    level: string;
    bobot: number | null;
}

export interface ReviewEntry {
    id: number;
    decision: string;
    decision_label: string;
    note: string | null;
    checklist: Record<string, boolean> | null;
    validator: string;
    reviewed_at: string;
}

export interface RevisionEntry {
    id: number;
    version: number;
    source: string;
    uploaded_at: string;
    uploader: string;
}

export interface ItemDetail {
    id: number;
    number: number;
    status: string;
    status_label: string;
    type: string;
    type_label: string;
    blueprint: ItemBlueprint;
    stem: string;
    options: Record<string, string>;
    answer_key: string;
    scoring_guide: string;
    images: string[];
    image_map: Record<string, string>;
    missing: string[];
    reviewed_at: string | null;
    resubmitted_at: string | null;
    version: number;
    reviews: ReviewEntry[];
    revisions: RevisionEntry[];
}

export interface PackageFileInfo {
    id: number;
    kind: string;
    kind_label: string;
    name: string;
    size: number;
    extraction_status: string;
    extraction_label: string;
    error: string | null;
    warnings: string[];
    uploaded_at: string | null;
}

export interface TypeOption {
    value: string;
    label: string;
    has_options: boolean;
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            app: { name: string; school: string };
            auth: { user: AuthUser; roles: RoleKey[]; active_role: RoleKey | null; impersonating: { admin_name: string | null; expires_at: string | null } | null } | null;
            context: {
                period: { id: number; label: string; status: 'buka' | 'ditutup' } | null;
                periods: PeriodOption[];
            } | null;
            notifications_unread: number;
            flash: { success: string | null; error: string | null; credential: Credential | null };
            errors: Record<string, string>;
        };
    }
}

export interface DownloadsInfo {
    recap_url: string;
    recap_docx_url: string;
    validated_url: string;
    final_url: string;
    cbt_url: string | null;
    is_final: boolean;
    final: {
        status: 'queued' | 'ready' | 'failed';
        doc_number: string | null;
        generated_at: string | null;
        sha256: string | null;
        docx_url: string | null;
        pdf_url: string | null;
        error: string | null;
        stale: boolean;
    } | null;
}
