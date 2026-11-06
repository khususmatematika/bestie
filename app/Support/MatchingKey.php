<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Kunci soal menjodohkan dengan pengecoh. Disimpan di answer_key sebagai teks:
 *   A=Surabaya; B=Bandung; C=Semarang
 *   Pengecoh: Malang; Medan
 * Baris pertama = pasangan (huruf pernyataan = pilihan benar); baris "Pengecoh:" = pilihan yang tidak dipakai.
 * Pilihan jawaban yang ditampilkan ke peserta = semua pasangan + pengecoh (urut alami, tidak membocorkan kunci).
 */
final class MatchingKey
{
    /**
     * @return array{pairs: array<string, string>, distractors: list<string>, choices: list<string>}
     */
    public static function parse(string $key): array
    {
        $pairs = [];
        $distractors = [];

        foreach (preg_split('/\R/u', $key) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^(?:pilihan\s+)?pengecoh\s*[:：]\s*(.*)$/iu', $line, $m) === 1) {
                $distractors = [...$distractors, ...self::split($m[1])];

                continue;
            }

            foreach (self::split($line) as $part) {
                if (preg_match('/^([A-Ea-e])\s*[=:]\s*(.+)$/us', $part, $m) === 1) {
                    $pairs[mb_strtoupper($m[1])] = trim($m[2]);
                }
            }
        }

        $choices = array_values(array_unique([...array_values($pairs), ...$distractors]));
        usort($choices, static fn (string $a, string $b): int => strnatcasecmp($a, $b));

        return ['pairs' => $pairs, 'distractors' => $distractors, 'choices' => $choices];
    }

    /**
     * @param  array<string, string>  $pairs
     * @param  list<string>  $distractors
     */
    public static function build(array $pairs, array $distractors): string
    {
        $parts = [];

        foreach ($pairs as $letter => $value) {
            $parts[] = $letter.'='.$value;
        }

        $key = implode('; ', $parts);
        $distractors = array_values(array_filter(array_map('trim', $distractors), static fn (string $d): bool => $d !== ''));

        return $distractors === [] ? $key : $key."\nPengecoh: ".implode('; ', $distractors);
    }

    /**
     * Pisah dengan ";" tanpa memotong di dalam equation \( ... \) (LaTeX boleh memuat ";" dan tanda kurung).
     *
     * @return list<string>
     */
    public static function split(string $text): array
    {
        [$masked, $map] = self::mask($text);

        return array_values(array_filter(
            array_map(static fn (string $p): string => self::unmask(trim($p), $map), explode(';', $masked)),
            static fn (string $p): bool => $p !== '',
        ));
    }

    /**
     * Ganti tiap equation \( ... \) dengan penanda agar regex/pemisah tidak salah membaca tanda kurung atau ";" di dalamnya.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    public static function mask(string $text): array
    {
        $map = [];
        $i = 0;
        $masked = preg_replace_callback('/\\\\\(.*?\\\\\)|\\\\\[.*?\\\\\]/us', static function (array $m) use (&$map, &$i): string {
            $token = "\u{E000}".$i++."\u{E001}";
            $map[$token] = $m[0];

            return $token;
        }, $text) ?? $text;

        return [$masked, $map];
    }

    /**
     * @param  array<string, string>  $map
     */
    public static function unmask(string $text, array $map): string
    {
        return $map === [] ? $text : strtr($text, $map);
    }
}
