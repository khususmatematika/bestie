<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\QuestionType;

/**
 * Mengurai naskah yang nomor dan pilihannya sudah berupa teks ("1. Soal…", "A. Pilihan"), misalnya hasil ekspor PDF
 * dari Word. Judul bagian ("Pilihan Ganda", "Benar/Salah", …) menentukan bentuk soal. Kunci jawaban tidak dapat dibaca
 * dari teks polos (pada DOCX kunci ditandai warna merah), sehingga kunci harus dilengkapi manual.
 */
final class TypedNaskahParser
{
    /**
     * @param  list<string>  $lines
     * @return array{items: array<int, array<string, mixed>>, warnings: list<string>}
     */
    public function parse(array $lines): array
    {
        $questions = [];
        $current = null;
        $section = null;
        $lastNumber = 0;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (($type = ExamDocxParser::sectionType($line)) !== null) {
                $section = $type;

                continue;
            }

            if (preg_match('/^(\d{1,3})\s*[.)]\s+(.+)$/us', $line, $m) === 1 && (int) $m[1] === $lastNumber + 1) {
                if ($current !== null) {
                    $questions[] = $current;
                }

                $lastNumber = (int) $m[1];
                $current = ['number' => $lastNumber, 'type' => $section, 'stem' => [$m[2]], 'options' => []];

                continue;
            }

            if ($current === null) {
                continue; // judul dokumen sebelum butir pertama
            }

            if (preg_match('/^([A-Ea-e])\s*[.)]\s+(.+)$/us', $line, $m) === 1) {
                $letter = mb_strtoupper($m[1]);
                $expected = ItemContentLetters::next($current['options']);

                if ($letter === $expected) {
                    $current['options'][$letter] = $m[2];

                    continue;
                }
            }

            if ($current['options'] === []) {
                $current['stem'][] = $line;
            } else {
                $last = array_key_last($current['options']);
                $current['options'][$last] .= ' '.$line;
            }
        }

        if ($current !== null) {
            $questions[] = $current;
        }

        $items = [];
        $warnings = [];

        foreach ($questions as $q) {
            $options = $q['options'];
            $type = $q['type'] ?? (count($options) >= 2 ? QuestionType::PilihanGanda : QuestionType::Uraian);
            $stem = trim(preg_replace('/\s+/u', ' ', implode(' ', $q['stem'])) ?? '');

            if ($type === QuestionType::BenarSalah) {
                $stem = trim(preg_replace('/\s*\(\s*B\s*\/\s*S\s*\)\s*\.?\s*$/u', '', $stem) ?? $stem);
                $options = [];
            } elseif (! $type->hasOptions()) {
                $options = [];
            }

            if ($type->hasOptions() && count($options) < 2) {
                $warnings[] = "Nomor {$q['number']}: pilihan jawaban tidak terbaca dari teks (mungkin berupa rumus/gambar); lengkapi manual.";
            }

            $items[$q['number']] = [
                'stem' => $stem,
                'options' => $options,
                'answer_key' => '',
                'scoring_guide' => '',
                'type' => $type->value,
                'bobot' => null,
                'images' => [],
            ];
        }

        if ($items !== []) {
            $warnings[] = 'Kunci jawaban tidak dapat dibaca dari teks polos/PDF. Lengkapi kunci tiap nomor, atau unggah naskah DOCX dengan kunci bertanda merah.';
        }

        return ['items' => $items, 'warnings' => $warnings];
    }
}
