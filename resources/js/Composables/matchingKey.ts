/** Pisah teks dengan ";" tanpa memotong di dalam equation \( ... \) (LaTeX boleh memuat ";"). */
export function splitKey(text: string): string[] {
    const parts: string[] = [];
    let current = '';
    let depth = 0;

    for (let i = 0; i < text.length; i++) {
        const two = text.slice(i, i + 2);

        if (two === '\\(' || two === '\\[') {
            depth++;
            current += two;
            i++;
        } else if ((two === '\\)' || two === '\\]') && depth > 0) {
            depth--;
            current += two;
            i++;
        } else if (text[i] === ';' && depth === 0) {
            parts.push(current);
            current = '';
        } else {
            current += text[i];
        }
    }

    parts.push(current);

    return parts.map((p) => p.trim()).filter(Boolean);
}
