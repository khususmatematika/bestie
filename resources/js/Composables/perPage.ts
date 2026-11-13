export const PER_PAGE_OPTIONS = [
    { value: '15', label: '15' },
    { value: '30', label: '30' },
    { value: '50', label: '50' },
    { value: '100', label: '100' },
    { value: 'all', label: 'Semua' },
];

/** Nilai per_page pada URL saat ini (undefined bila bawaan), agar pencarian/penyaringan tidak mengembalikannya ke 15. */
export function currentPerPage(): string | undefined {
    if (typeof window === 'undefined') return undefined;
    const v = new URLSearchParams(window.location.search).get('per_page');

    return v && v !== '15' && PER_PAGE_OPTIONS.some((o) => o.value === v) ? v : undefined;
}
