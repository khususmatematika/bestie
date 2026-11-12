<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\QuestionType;

/**
 * Normalisasi isi butir soal (teks polos). Dipakai oleh ekstraksi, editor draft, dan unggah ulang
 * agar bentuk datanya selalu sama dan mudah dibandingkan.
 */
final class ItemContent
{
    public const OPTION_LETTERS = ['A', 'B', 'C', 'D', 'E'];

    /**
     * @param  array<string, mixed>  $input
     * @return array{type: string, stem: string, options: array<string, string>, answer_key: string, scoring_guide: string, blueprint: array{cp: string, materi: string, submateri: string, indikator: string, level: string, bobot: float|null}}
     */
    public static function normalize(array $input): array
    {
        $type = $input['type'] ?? null;
        $type = $type instanceof QuestionType ? $type : (QuestionType::tryFrom((string) $type) ?? QuestionType::PilihanGanda);

        $options = [];

        foreach ((array) ($input['options'] ?? []) as $letter => $text) {
            $letter = mb_strtoupper(trim((string) $letter));
            $text = self::text($text, 2000);

            if (in_array($letter, self::OPTION_LETTERS, true) && $text !== '') {
                $options[$letter] = $text;
            }
        }

        ksort($options);

        // Benar/Salah boleh memuat beberapa pernyataan (A–E) yang masing-masing dinilai B/S.
        if (! $type->hasOptions() && $type !== QuestionType::BenarSalah) {
            $options = [];
        }

        $blueprint = (array) ($input['blueprint'] ?? []);
        $bobot = $blueprint['bobot'] ?? null;

        return [
            'type' => $type->value,
            'stem' => self::text($input['stem'] ?? '', 20000),
            'options' => $options,
            'answer_key' => self::answerKey((string) ($input['answer_key'] ?? ''), $type),
            'scoring_guide' => self::text($input['scoring_guide'] ?? '', 5000),
            'blueprint' => [
                'cp' => self::text($blueprint['cp'] ?? '', 500),
                'materi' => self::text($blueprint['materi'] ?? '', 500),
                'submateri' => self::text($blueprint['submateri'] ?? '', 500),
                'indikator' => self::text($blueprint['indikator'] ?? '', 1000),
                'level' => self::text($blueprint['level'] ?? '', 30),
                'bobot' => is_numeric($bobot) && (float) $bobot > 0 ? round((float) $bobot, 2) : null,
            ],
        ];
    }

    /**
     * Teks polos: buang karakter kontrol, samakan baris baru, rapikan spasi.
     */
    public static function text(mixed $value, int $max): string
    {
        $text = is_scalar($value) ? (string) $value : '';
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        // Spasi tak-terputus hasil salin dari Word dijadikan spasi biasa agar teks dapat turun baris (layar ponsel).
        $text = str_replace(["\u{00A0}", "\u{202F}", "\u{2007}"], ' ', $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return mb_substr(trim($text), 0, $max);
    }

    /**
     * Kunci pilihan ganda dibakukan menjadi huruf besar ("b." => "B", "a & c" => "A,C").
     */
    public static function answerKey(string $raw, QuestionType $type): string
    {
        $key = self::text($raw, 1000);

        if ($type === QuestionType::PilihanGanda || $type === QuestionType::PilihanGandaKompleks) {
            if (preg_match('/^[A-Ea-e](?:\s*(?:,|;|&|dan|\/)\s*[A-Ea-e])*\.?$/u', $key) === 1) {
                preg_match_all('/[A-Ea-e]/u', $key, $letters);
                $unique = array_values(array_unique(array_map('mb_strtoupper', $letters[0])));
                sort($unique);

                return implode(',', $unique);
            }
        }

        if ($type === QuestionType::BenarSalah) {
            // Beberapa pernyataan: "A=B; B=S; C=B".
            if (str_contains($key, '=')) {
                return mb_strtoupper(preg_replace('/\s*[;,]\s*/u', '; ', trim($key)) ?? $key);
            }

            $first = mb_strtoupper(mb_substr(trim($key), 0, 1));

            return in_array($first, ['B', 'S'], true) ? $first : $key;
        }

        return $key;
    }

    /**
     * Seluruh teks butir yang dapat memuat penanda gambar: soal, pilihan, kunci (menjodohkan), pedoman penskoran.
     *
     * @param  array<string, mixed>  $content
     */
    private static function allText(array $content): string
    {
        return (string) ($content['stem'] ?? '').' '.implode(' ', array_filter((array) ($content['options'] ?? []), 'is_string')).' '.(string) ($content['answer_key'] ?? '').' '.(string) ($content['scoring_guide'] ?? '');
    }

    /**
     * Id lampiran gambar yang dirujuk penanda ⟦img:ID⟧ pada teks butir.
     *
     * @param  array<string, mixed>  $content
     * @return list<int>
     */
    public static function referencedImageIds(array $content): array
    {
        $text = self::allText($content);
        preg_match_all('/⟦img:(\d+)⟧/u', $text, $m);

        return array_values(array_unique(array_map('intval', $m[1])));
    }

    /**
     * Mengganti penanda ⟦IMG:path-di-docx⟧ menjadi ⟦img:id-lampiran⟧; gambar yang tidak tersimpan dihapus dari teks.
     *
     * @param  array<string, mixed>  $content
     * @param  array<string, int>  $map  path di DOCX => id lampiran
     * @return array<string, mixed>
     */
    public static function resolveImageTokens(array $content, array $map): array
    {
        $replace = static fn (string $text): string => preg_replace_callback(
            '/⟦IMG:([^⟧]*)⟧/u',
            static fn (array $m): string => isset($map[$m[1]]) ? '⟦img:'.$map[$m[1]].'⟧' : '',
            $text,
        ) ?? $text;

        // answer_key ikut: pada soal menjodohkan, pilihan jawaban/pengecoh boleh berupa gambar.
        foreach (['stem', 'scoring_guide', 'answer_key'] as $key) {
            if (is_string($content[$key] ?? null)) {
                $content[$key] = trim($replace($content[$key]));
            }
        }

        if (is_array($content['options'] ?? null)) {
            $content['options'] = array_map(static fn ($v): mixed => is_string($v) ? trim($replace($v)) : $v, $content['options']);
        }

        return $content;
    }

    /**
     * Daftar path gambar DOCX yang masih berupa penanda ⟦IMG:path⟧.
     *
     * @param  array<string, mixed>  $content
     * @return list<string>
     */
    public static function pendingImagePaths(array $content): array
    {
        $text = self::allText($content);
        preg_match_all('/⟦IMG:([^⟧]*)⟧/u', $text, $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * Bentuk isi untuk membandingkan perubahan: id gambar diabaikan.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function withoutImageIds(array $content): array
    {
        $strip = static fn (string $t): string => preg_replace('/⟦(?:img|IMG):[^⟧]*⟧/u', '⟦i⟧', $t) ?? $t;

        foreach (['stem', 'scoring_guide'] as $key) {
            if (is_string($content[$key] ?? null)) {
                $content[$key] = $strip($content[$key]);
            }
        }

        if (is_array($content['options'] ?? null)) {
            $content['options'] = array_map(static fn ($v): mixed => is_string($v) ? $strip($v) : $v, $content['options']);
        }

        return $content;
    }
}
