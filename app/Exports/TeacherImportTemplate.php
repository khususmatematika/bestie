<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Template resmi impor akun guru (FR-USR-03).
 * Kolom NIP & telepon dipaksa bertipe teks agar angka nol di depan tidak hilang.
 */
final class TeacherImportTemplate extends StringValueBinder implements FromArray, ShouldAutoSize, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithTitle
{
    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['nama', 'email', 'nip', 'password', 'kode_mapel', 'tingkat', 'telepon'];
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return [
            ['Contoh Guru Satu, S.Pd.', 'guru.satu@sekolah.sch.id', '198001012005011001', 'GuruSatu#2026', 'MAT', 'X,XI', '081234567890'],
            ['Contoh Guru Dua, S.Pd. (hanya NIP, sandi acak)', '', '198505052010012002', '', 'BIN,BING', 'XII', ''],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function title(): string
    {
        return 'Akun Guru';
    }
}
