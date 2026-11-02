const TZ = 'Asia/Jakarta';

/** Waktu dari server berformat ISO 8601 (UTC); ditampilkan dalam zona Asia/Jakarta. */
export function formatDateTime(iso: string | null | undefined): string {
    if (!iso) return '—';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '—';

    return new Intl.DateTimeFormat('id-ID', {
        timeZone: TZ,
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(date);
}

/** Tanggal murni (YYYY-MM-DD) tanpa pergeseran zona waktu. */
export function formatDate(value: string | null | undefined): string {
    if (!value) return '—';
    const [y, m, d] = value.slice(0, 10).split('-').map(Number);
    if (!y || !m || !d) return '—';

    return new Intl.DateTimeFormat('id-ID', {
        timeZone: 'UTC',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(new Date(Date.UTC(y, m - 1, d)));
}

/** Sisa hari menuju tanggal tertentu (negatif = sudah lewat). */
export function daysUntil(value: string | null | undefined): number | null {
    if (!value) return null;
    const [y, m, d] = value.slice(0, 10).split('-').map(Number);
    if (!y || !m || !d) return null;

    const now = new Date();
    const today = Date.UTC(now.getFullYear(), now.getMonth(), now.getDate());

    return Math.round((Date.UTC(y, m - 1, d) - today) / 86_400_000);
}
