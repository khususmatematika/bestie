<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\QuestionType;

/**
 * Menerjemahkan baris kisi-kisi (hasil pembacaan Excel dengan judul kolom snake_case)
 * menjadi data per nomor: capaian pembelajaran, materi, indikator, level, bentuk, kunci, bobot.
 */
final class BlueprintRowsParser
{
    /** @var array<string, list<string>> */
    private const ALIASES = [
        'no' => ['no_soal', 'nomor_soal', 'no_butir', 'nomor_butir', 'nomor', 'no'],
        'cp' => ['capaian_pembelajaran', 'cp', 'kd', 'kompetensi_dasar', 'tujuan_pembelajaran', 'tp'],
        'materi' => ['materi', 'materi_pokok', 'ruang_lingkup_materi'],
        'submateri' => ['submateri', 'sub_materi'],
        'indikator' => ['indikator_soal', 'indikator', 'indikator_pencapaian'],
        'level' => ['level_kognitif', 'level', 'level_kognitif_c1_c6', 'level_kognisi'],
        'bentuk' => ['bentuk_soal', 'bentuk', 'tipe', 'jenis_soal'],
        'kunci' => ['kunci', 'kunci_jawaban', 'kunci_pedoman', 'kunci_pedoman_penskoran', 'pedoman_penskoran'],
        'bobot' => ['bobot', 'skor', 'bobot_soal'],
    ];

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, warnings: list<string>}
     */
    public function parse(array $rows): array
    {
        $result = [];
        $warnings = [];
        $rows = $this->inheritMerged($rows);

        foreach ($rows as $index => $row) {
            $line = $index + 2; // baris 1 = judul kolom
            $number = $this->number($this->pick($row, 'no'));

            if ($number === null) {
                if ($this->hasContent($row)) {
                    $warnings[] = "Kisi-kisi baris {$line}: kolom nomor kosong/tidak valid, baris dilewati.";
                }

                continue;
            }

            if (isset($result[$number])) {
                $warnings[] = "Kisi-kisi: nomor {$number} muncul lebih dari sekali; baris pertama yang dipakai.";

                continue;
            }

            $bobot = $this->pick($row, 'bobot');
            $shape = $this->text($this->pick($row, 'bentuk'));
            $type = QuestionType::fromInput($shape);

            if ($shape !== '' && $type === null) {
                $warnings[] = "Kisi-kisi nomor {$number}: bentuk soal '{$shape}' tidak dikenali.";
            }

            $result[$number] = [
                'blueprint' => [
                    'cp' => $this->text($this->pick($row, 'cp')),
                    'materi' => $this->text($this->pick($row, 'materi')),
                    'submateri' => $this->text($this->pick($row, 'submateri')),
                    'indikator' => $this->text($this->pick($row, 'indikator')),
                    'level' => $this->level($this->text($this->pick($row, 'level'))),
                    'bobot' => is_numeric(str_replace(',', '.', $this->text($bobot))) ? (float) str_replace(',', '.', $this->text($bobot)) : null,
                ],
                'type' => $type?->value,
                'kunci' => $this->text($this->pick($row, 'kunci')),
            ];
        }

        ksort($result);

        return ['rows' => $result, 'warnings' => $warnings];
    }

    /**
     * Sel yang digabung vertikal (Capaian Pembelajaran, Materi, Submateri) hanya terisi pada baris pertama.
     * Baris berikutnya mewarisi nilai di atasnya; kolom yang lebih rinci direset saat kolom di atasnya berganti.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function inheritMerged(array $rows): array
    {
        $last = ['cp' => '', 'materi' => '', 'submateri' => ''];
        $out = [];

        foreach ($rows as $row) {
            $cp = $this->text($this->pick($row, 'cp'));
            $materi = $this->text($this->pick($row, 'materi'));
            $sub = $this->text($this->pick($row, 'submateri'));

            if ($cp === '' && $this->hasContent($row)) {
                $cp = $last['cp'];
                $fresh = false;
            } else {
                $fresh = $cp !== '';
            }

            if ($materi === '' && ! $fresh) {
                $materi = $last['materi'];
            } elseif ($materi !== '') {
                $fresh = true;
            }

            if ($sub === '' && ! $fresh) {
                $sub = $last['submateri'];
            }

            $last = ['cp' => $cp, 'materi' => $materi, 'submateri' => $sub];

            foreach (['cp' => $cp, 'materi' => $materi, 'submateri' => $sub] as $field => $value) {
                $key = self::ALIASES[$field][0];
                $row[$key] = $value;
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function pick(array $row, string $field): mixed
    {
        foreach (self::ALIASES[$field] as $alias) {
            if (array_key_exists($alias, $row) && $row[$alias] !== null && $row[$alias] !== '') {
                return $row[$alias];
            }
        }

        return null;
    }

    /**
     * Level kognitif dibakukan menurut kodenya ("C3 aplikasi" / "C3Aplikasi" / "c3" => "C3 Aplikasi").
     */
    private function level(string $text): string
    {
        if (preg_match('/\bC\s?([1-6])\b|^C([1-6])/iu', $text, $m) !== 1) {
            return $text;
        }

        $code = (int) ($m[1] !== '' ? $m[1] : $m[2]);
        $names = [1 => 'Pengetahuan', 2 => 'Pemahaman', 3 => 'Aplikasi', 4 => 'Analisis', 5 => 'Evaluasi', 6 => 'Kreasi'];

        return 'C'.$code.' '.$names[$code];
    }

    private function number(mixed $value): ?int
    {
        $text = $this->text($value);

        return preg_match('/^\d{1,3}(?:\.0+)?$/', $text) === 1 && (int) $text > 0 ? (int) $text : null;
    }

    private function text(mixed $value): string
    {
        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hasContent(array $row): bool
    {
        foreach ($row as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }
}
