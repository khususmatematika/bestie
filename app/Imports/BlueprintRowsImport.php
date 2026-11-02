<?php

declare(strict_types=1);

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Pembaca kisi-kisi (Excel/CSV). Judul kolom otomatis menjadi snake_case
 * (mis. "Indikator Soal" => indikator_soal). Dipakai lewat Excel::toArray().
 */
final class BlueprintRowsImport implements ToArray, WithHeadingRow
{
    /**
     * Maatwebsite Excel 4 mewajibkan impor bertipe Import. Baris dibaca lewat Excel::toArray(), jadi tidak diproses di sini.
     *
     * @param  array<array-key, mixed>  $array
     */
    public function array(array $array): void
    {
    }
}
