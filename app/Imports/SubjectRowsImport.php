<?php

declare(strict_types=1);

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Pembaca baris impor mapel. Dipakai lewat Excel::toArray().
 * Judul kolom otomatis menjadi snake_case (mis. "Kode Mapel" => kode_mapel).
 */
final class SubjectRowsImport implements ToArray, WithHeadingRow
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
