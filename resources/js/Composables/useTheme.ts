import { ref } from 'vue';

export type ThemeChoice = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'banksoal-theme';
const theme = ref<ThemeChoice>('light');
let initialised = false;

function prefersDark(): boolean {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function apply(choice: ThemeChoice): void {
    const dark = choice === 'dark' || (choice === 'system' && prefersDark());
    document.documentElement.classList.toggle('dark', dark);
}

function read(): ThemeChoice {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored === 'light' || stored === 'dark' || stored === 'system') return stored;
    } catch {
        /* penyimpanan dapat diblokir (mode privat) */
    }
    return 'light'; // bawaan: tema terang
}

export function useTheme() {
    if (!initialised && typeof window !== 'undefined') {
        initialised = true;
        theme.value = read();
        apply(theme.value);

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if (theme.value === 'system') apply('system');
        });
    }

    function set(choice: ThemeChoice): void {
        theme.value = choice;
        try {
            localStorage.setItem(STORAGE_KEY, choice);
        } catch {
            /* abaikan */
        }
        apply(choice);
    }

    function cycle(): void {
        const order: ThemeChoice[] = ['light', 'dark', 'system'];
        set(order[(order.indexOf(theme.value) + 1) % order.length]);
    }

    return { theme, set, cycle };
}
