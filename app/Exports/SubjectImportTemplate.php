<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * Template impor mapel: kode, nama, kategori (umum/pilihan), dan tingkat kelas.
 */
final class SubjectImportTemplate extends StringValueBinder implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithTitle
{
    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['kode_mapel', 'nama_mapel', 'kategori', 'tingkat'];
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return [
            ['MAT', 'Matematika', 'umum', 'X,XI,XII'],
            ['FIS', 'Fisika', 'pilihan', 'XI,XII'],
            ['SENI', 'Seni Musik', 'umum', 'X'],
        ];
    }

    public function title(): string
    {
        return 'Mapel';
    }
}
