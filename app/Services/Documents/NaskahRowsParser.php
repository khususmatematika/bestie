<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\QuestionType;
use App\Support\ItemContent;

/**
 * Menerjemahkan naskah dalam bentuk tabel Excel/CSV (satu baris per nomor) menjadi butir soal.
 * Kolom: No, Soal, Opsi A..E (atau A..E), Kunci, Bentuk, Pedoman, Bobot. Judul kolom toleran terhadap variasi penulisan.
 * Keluarannya sama dengan NaskahParser::parse().
 */
final class NaskahRowsParser
{
    /** @var array<string, list<string>> */
    private const ALIASES = [
        'no' => ['no', 'nomor', 'nomor_soal', 'no_soal'],
        'stem' => ['soal', 'teks_soal', 'butir_soal', 'pertanyaan', 'stimulus_soal', 'soal_pertanyaan'],
        'kunci' => ['kunci', 'kunci_jawaban', 'jawaban'],
        'bentuk' => ['bentuk', 'bentuk_soal', 'tipe', 'jenis_soal'],
        'pedoman' => ['pedoman', 'pedoman_penskoran', 'rubrik'],
        'bobot' => ['bobot', 'skor', 'bobot_soal'],
    ];

    /**
     * @param  list<array<string, mixed>>  $rows  baris dengan kunci snake_case
     * @return array{items: array<int, array<string, mixed>>, warnings: list<string>}
     */
    public function parse(array $rows): array
    {
        $items = [];
        $warnings = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $number = $this->number($this->pick($row, 'no'));

            if ($number === null) {
                if (array_filter($row, static fn (mixed $v): bool => trim((string) $v) !== '') !== []) {
                    $warnings[] = "Naskah baris {$line}: kolom nomor kosong/tidak valid, baris dilewati.";
                }

                continue;
            }

            if (isset($items[$number])) {
                $warnings[] = "Naskah: nomor {$number} muncul lebih dari sekali; baris pertama yang dipakai.";

                continue;
            }

            $options = [];

            foreach (ItemContent::OPTION_LETTERS as $letter) {
                $value = $row['opsi_'.mb_strtolower($letter)] ?? $row['pilihan_'.mb_strtolower($letter)] ?? $row[mb_strtolower($letter)] ?? null;
                $text = ItemContent::text($value ?? '', 2000);

                if ($text !== '') {
                    $options[$letter] = $text;
                }
            }

            $shape = trim((string) $this->pick($row, 'bentuk'));
            $type = $shape !== '' ? QuestionType::fromInput($shape) : null;

            if ($shape !== '' && $type === null) {
                $warnings[] = "Naskah nomor {$number}: bentuk soal '{$shape}' tidak dikenali.";
            }

            if ($type === null) {
                $type = $options !== [] ? QuestionType::PilihanGanda : null;
            }

            $weight = $this->pick($row, 'bobot');

            $items[$number] = [
                'stem' => ItemContent::text($this->pick($row, 'stem') ?? '', 8000),
                'options' => $options,
                'answer_key' => ItemContent::text($this->pick($row, 'kunci') ?? '', 1000),
                'scoring_guide' => ItemContent::text($this->pick($row, 'pedoman') ?? '', 4000),
                'type' => $type?->value,
                'bobot' => is_numeric($weight) ? (float) $weight : null,
            ];
        }

        ksort($items);

        return ['items' => $items, 'warnings' => $warnings];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function pick(array $row, string $field): mixed
    {
        foreach (self::ALIASES[$field] as $alias) {
            if (isset($row[$alias]) && trim((string) $row[$alias]) !== '') {
                return $row[$alias];
            }
        }

        return null;
    }

    private function number(mixed $value): ?int
    {
        if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\s*\d{1,3}(\.0+)?\s*$/', $value) === 1)) {
            $n = (int) $value;

            return $n >= 1 && $n <= 999 ? $n : null;
        }

        return null;
    }
}
