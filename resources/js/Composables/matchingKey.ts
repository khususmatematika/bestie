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

/** Urai kunci menjodohkan ("A=Surabaya; B=Bandung" + baris "Pengecoh: Malang; Medan") menjadi pasangan dan pengecoh. */
export function parseMatchingKey(key: string): { pairs: Record<string, string>; distractors: string[] } {
    const pairs: Record<string, string> = {};
    const distractors: string[] = [];

    for (const raw of key.split(/\r?\n/)) {
        const line = raw.trim();
        const d = /^(?:pilihan\s+)?pengecoh\s*[:：]\s*(.*)$/i.exec(line);

        if (d) {
            distractors.push(...splitKey(d[1]));
            continue;
        }

        for (const part of splitKey(line)) {
            const m = /^([A-Ea-e])\s*[=:]\s*(.+)$/s.exec(part.trim());

            if (m) pairs[m[1].toUpperCase()] = m[2].trim();
        }
    }

    return { pairs, distractors };
}

/** Kebalikan parseMatchingKey; format sama dengan App\Support\MatchingKey::build. */
export function buildMatchingKey(pairs: Record<string, string>, distractors: string[]): string {
    const parts = Object.entries(pairs)
        .filter(([, v]) => v.trim() !== '')
        .map(([l, v]) => `${l}=${v.trim()}`);
    const extra = distractors.map((d) => d.trim()).filter(Boolean);
    const key = parts.join('; ');

    return extra.length ? `${key}\nPengecoh: ${extra.join('; ')}` : key;
}

export type TF = 'B' | 'S' | '';

/** Urai kunci benar/salah: "B"/"S" (satu jawaban) atau "A=B; B=S" (per pernyataan). */
export function parseTrueFalseKey(key: string): { single: TF; map: Record<string, TF> } {
    const map: Record<string, TF> = {};

    for (const part of splitKey(key)) {
        const m = /^([A-Ea-e])\s*[=:]\s*(b|s|benar|salah)/i.exec(part.trim());

        if (m) map[m[1].toUpperCase()] = m[2][0].toUpperCase() as TF;
    }

    const s = /^\s*(b|s|benar|salah)\b/i.exec(key);

    return { single: Object.keys(map).length === 0 && s ? (s[1][0].toUpperCase() as TF) : '', map };
}
