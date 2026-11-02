<?php

declare(strict_types=1);

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Pembaca baris impor akun guru. Dipakai lewat Excel::toArray(), sehingga
 * baris dapat diproses satu per satu oleh action ImportTeachers.
 *
 * Judul kolom otomatis diubah menjadi snake_case (mis. "Kode Mapel" => kode_mapel).
 */
final class TeacherRowsImport implements ToArray, WithHeadingRow
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
