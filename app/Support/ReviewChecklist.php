<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Daftar periksa telaah per nomor (FR-REV-03).
 */
final class ReviewChecklist
{
    /** @var array<string, string> */
    public const ITEMS = [
        'indikator' => 'Sesuai indikator pada kisi-kisi',
        'materi' => 'Materi/konsep benar',
        'bahasa' => 'Bahasa jelas dan baku',
        'kunci' => 'Kunci jawaban benar',
        'distraktor' => 'Pengecoh berfungsi (untuk pilihan ganda)',
        'level' => 'Level kognitif sesuai',
        'bias' => 'Bebas bias/SARA dan konten sensitif',
        'stimulus' => 'Stimulus ada dan dibutuhkan saat mengerjakan soal',
        'homogen' => 'Pilihan jawaban homogen',
        'soal_dibutuhkan' => 'Soal dibutuhkan saat mencari jawaban',
    ];

    /** Judul kolom ringkas untuk rekap (urutan sama dengan ITEMS). @var array<string, string> */
    public const SHORT = [
        'indikator' => 'Indikator',
        'materi' => 'Materi',
        'bahasa' => 'Bahasa',
        'kunci' => 'Kunci',
        'distraktor' => 'Pengecoh',
        'level' => 'Level',
        'bias' => 'Bebas bias',
        'stimulus' => 'Stimulus',
        'homogen' => 'Pilihan homogen',
        'soal_dibutuhkan' => 'Soal dibutuhkan',
    ];

    /**
     * Hanya kunci yang dikenal, nilainya dipaksa boolean.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, bool>
     */
    public static function normalize(array $raw): array
    {
        $result = [];

        foreach (array_keys(self::ITEMS) as $key) {
            $result[$key] = filter_var($raw[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        return $result;
    }
}
