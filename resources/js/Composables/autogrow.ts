import type { Directive } from 'vue';

type GrowEl = HTMLTextAreaElement & { __grow?: { run: () => void; ro: ResizeObserver } };

/**
 * v-autogrow: tinggi kotak teks mengikuti isinya (tanpa scroll), termasuk saat teks ditempel,
 * diubah lewat kode, atau lebar kotak berubah (ponsel diputar, jendela diubah ukurannya).
 */
export const vAutogrow: Directive<HTMLTextAreaElement> = {
    mounted(el: GrowEl) {
        el.style.overflowY = 'hidden';
        el.style.resize = 'none';

        let lastWidth = -1;
        const run = (): void => {
            if (el.offsetParent === null) return; // tersembunyi (mis. modal tertutup): ukur saat tampil
            el.style.height = 'auto';
            el.style.height = `${el.scrollHeight + (el.offsetHeight - el.clientHeight)}px`;
        };
        const ro = new ResizeObserver(() => {
            if (el.offsetWidth === lastWidth) return;
            lastWidth = el.offsetWidth;
            run();
        });

        el.addEventListener('input', run);
        ro.observe(el);
        el.__grow = { run, ro };
        run();
    },
    updated(el: GrowEl) {
        el.__grow?.run();
    },
    beforeUnmount(el: GrowEl) {
        el.__grow?.ro.disconnect();
        el.removeEventListener('input', el.__grow?.run as EventListener);
    },
};
