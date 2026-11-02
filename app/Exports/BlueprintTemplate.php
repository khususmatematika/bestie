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
 * Template kisi-kisi standar: satu baris per nomor soal.
 */
final class BlueprintTemplate extends StringValueBinder implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithTitle
{
    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['No', 'Capaian Pembelajaran', 'Materi', 'Indikator Soal', 'Level Kognitif', 'Bentuk Soal', 'Kunci', 'Bobot'];
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return [
            ['1', 'Peserta didik mampu mengenal wilayah Indonesia', 'Ibu kota negara', 'Disajikan pernyataan, peserta didik dapat menentukan ibu kota negara Indonesia', 'C1', 'Pilihan ganda', 'B', '1'],
            ['2', 'Peserta didik mampu menganalisis permasalahan lingkungan', 'Banjir perkotaan', 'Disajikan kasus, peserta didik dapat menjelaskan penyebab banjir', 'C4', 'Uraian', '', '5'],
        ];
    }

    public function title(): string
    {
        return 'Kisi-kisi';
    }
}
