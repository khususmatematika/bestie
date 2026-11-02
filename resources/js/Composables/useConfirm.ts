import { reactive } from 'vue';

interface ConfirmState {
    open: boolean;
    title: string;
    message: string;
    confirmLabel: string;
    danger: boolean;
    resolve: ((value: boolean) => void) | null;
}

const state = reactive<ConfirmState>({
    open: false,
    title: '',
    message: '',
    confirmLabel: 'Ya, lanjutkan',
    danger: false,
    resolve: null,
});

/**
 * Dialog konfirmasi global (rules.md §8.2: aksi destruktif wajib konfirmasi).
 * Pemakaian: if (await confirm({ title: '...', message: '...', danger: true })) { ... }
 */
export function useConfirm() {
    function confirm(options: { title: string; message: string; confirmLabel?: string; danger?: boolean }): Promise<boolean> {
        return new Promise((resolve) => {
            state.title = options.title;
            state.message = options.message;
            state.confirmLabel = options.confirmLabel ?? 'Ya, lanjutkan';
            state.danger = options.danger ?? false;
            state.resolve = resolve;
            state.open = true;
        });
    }

    function answer(value: boolean): void {
        state.open = false;
        state.resolve?.(value);
        state.resolve = null;
    }

    return { state, confirm, answer };
}
