<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\QuestionPackage;
use App\Support\ExportFormat;
use App\Support\ReviewChecklist;
use App\Support\Settings;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Rekap validasi siap cetak (XLSX): kop, identitas, tabel dengan 10 kolom daftar periksa berwarna, kemiripan tertinggi,
 * keterangan, dan tanda tangan; A4 lanskap, lebar muat satu halaman, judul tabel berulang, nomor halaman.
 */
final class RecapWorkbook
{
    private const LAST = 'Q'; // 3 + 10 + 1 + 3 kolom

    public function build(QuestionPackage $package, string $path): void
    {
        $data = RecapData::build($package);
        $format = ExportFormat::get();

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Rekap validasi');
        $sheet->setShowGridlines(false);

        // Lebar kolom: No, Bentuk, Status, 10 daftar periksa, Kemiripan, Riwayat catatan, Waktu validasi, Versi.
        $widths = ['A' => 5, 'B' => 14, 'C' => 15] + array_fill_keys(range('D', 'M'), 6.2) + ['N' => 12, 'O' => 62, 'P' => 20, 'Q' => 7];

        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $row = 1;

        // Kop.
        $lines = KopHeader::lines($format);

        foreach ($lines as $line) {
            $sheet->mergeCells("B{$row}:".self::LAST."{$row}");
            $sheet->setCellValueExplicit("B{$row}", $line['text'], DataType::TYPE_STRING);
            $sheet->getStyle("B{$row}")->getFont()->setBold($line['bold'] ?? false)->setSize((int) $line['size'])->setName($format['font']);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        $logo = resource_path('images/kop-logo.png');

        if (($format['show_logo'] ?? true) && is_file($logo)) {
            $drawing = new Drawing;
            $drawing->setPath($logo);
            $drawing->setHeight(78);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(6);
            $drawing->setOffsetY(4);
            $drawing->setWorksheet($sheet);
        }

        $sheet->getStyle("A".($row - 1).":".self::LAST.($row - 1))->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE)->getColor()->setRGB('44546A');
        $row += 1;

        // Judul + identitas.
        $sheet->mergeCells("A{$row}:".self::LAST."{$row}");
        $sheet->setCellValue("A{$row}", 'REKAP VALIDASI SOAL');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(15);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row += 2;

        $identity = $data['identity'];

        for ($i = 0; $i < count($identity); $i += 2) {
            foreach ([[$identity[$i], 'A', 'C', 'D', 'H'], [$identity[$i + 1] ?? ['', ''], 'I', 'K', 'L', self::LAST]] as [$pair, $l1, $l2, $v1, $v2]) {
                $sheet->mergeCells("{$l1}{$row}:{$l2}{$row}");
                $sheet->mergeCells("{$v1}{$row}:{$v2}{$row}");
                $sheet->setCellValueExplicit("{$l1}{$row}", $pair[0], DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("{$v1}{$row}", $pair[1], DataType::TYPE_STRING);
                $sheet->getStyle("{$l1}{$row}:{$l2}{$row}")->getFont()->setBold(true);
                $sheet->getStyle("{$l1}{$row}:{$l2}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2FF');
                $sheet->getStyle("{$l1}{$row}:{$v2}{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('9CA3AF');
            }

            $row++;
        }

        $row += 1;

        // Tabel utama.
        $headerRow = $row;
        $heads = ['No', 'Bentuk', 'Status', ...array_values(ReviewChecklist::SHORT), 'Kemiripan tertinggi', 'Riwayat catatan validator', 'Waktu validasi', 'Versi'];

        foreach ($heads as $i => $text) {
            $sheet->setCellValueExplicit([$i + 1, $row], $text, DataType::TYPE_STRING);
        }

        $headStyle = $sheet->getStyle("A{$row}:".self::LAST.$row);
        $headStyle->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'))->setSize(9);
        $headStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
        $headStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle("D{$row}:M{$row}")->getAlignment()->setTextRotation(90);
        $sheet->getRowDimension($row)->setRowHeight(78);
        $row++;

        $first = $row;

        foreach ($data['rows'] as $r) {
            $values = [(string) $r['number'], $r['type'], $r['status']];

            foreach ($r['checks'] as $met) {
                $values[] = $met === null ? '–' : ($met ? '✓' : '✗');
            }

            $score = $r['similarity'];
            array_push($values, $score === null ? '—' : $score.'%', $r['note'], $r['validated_at'], $r['version']);

            foreach ($values as $i => $v) {
                $sheet->setCellValueExplicit([$i + 1, $row], (string) $v, DataType::TYPE_STRING);
            }

            $col = 4;

            foreach ($r['checks'] as $met) {
                $cell = $sheet->getCell([$col++, $row]);
                $style = $sheet->getStyle($cell->getCoordinate());
                $style->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($met === null ? 'FF6B7280' : 'FFFFFFFF'));
                $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($met === null ? 'F3F4F6' : ($met ? RecapSection::BLUE : RecapSection::RED));
            }

            $simFill = match (RecapData::level($score)) {
                'tinggi' => 'FECACA',
                'sedang' => 'FEF08A',
                default => 'FFFFFF',
            };
            $sheet->getStyle("N{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($simFill);
            $sheet->getStyle("N{$row}")->getFont()->setBold(true);
            $row++;
        }

        if ($data['rows'] === []) {
            $sheet->mergeCells("A{$row}:".self::LAST."{$row}");
            $sheet->setCellValue("A{$row}", 'Belum ada nomor pada paket ini.');
            $row++;
        }

        $last = $row - 1;
        $body = $sheet->getStyle("A{$headerRow}:".self::LAST."{$last}");
        $body->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('6B7280');
        $body->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle("A{$first}:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D{$first}:N{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("Q{$first}:Q{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("P{$first}:P{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("A{$first}:".self::LAST."{$last}")->getFont()->setSize(9);

        // Keterangan.
        $row += 1;
        $sheet->setCellValue("A{$row}", 'Keterangan');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(9);
        $row++;

        $notes = ['Kolom daftar periksa: biru (✓) = terpenuhi; merah (✗) = tidak terpenuhi; abu (–) = belum ditelaah. Kemiripan tertinggi = persentase kemiripan terbesar dengan arsip, paket Fix semester lama, atau paket lain pada periode yang sama (merah muda ≥ 70%, kuning ≥ 50%).'];
        $n = 0;
        $parts = [];

        foreach (ReviewChecklist::SHORT as $key => $short) {
            $parts[] = (++$n).'. '.$short.' = '.ReviewChecklist::ITEMS[$key];
        }

        $notes[] = implode('; ', $parts).'.';

        foreach ($notes as $note) {
            $sheet->mergeCells("A{$row}:".self::LAST."{$row}");
            $sheet->setCellValueExplicit("A{$row}", $note, DataType::TYPE_STRING);
            $sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("A{$row}")->getFont()->setSize(8);
            $sheet->getRowDimension($row)->setRowHeight(34);
            $row++;
        }

        // Tanda tangan.
        $row += 1;
        foreach ([['Dicetak: '.RecapRows::stamp(now()), false, 9], ['Validator,', false, 10], ['', false, 10], ['', false, 10], [$data['validator'] !== '' ? $data['validator'] : '(................................)', true, 10]] as [$text, $bold, $size]) {
            $sheet->mergeCells("N{$row}:".self::LAST."{$row}");
            $sheet->setCellValueExplicit("N{$row}", $text, DataType::TYPE_STRING);
            $sheet->getStyle("N{$row}")->getFont()->setBold($bold)->setSize($size);
            $sheet->getStyle("N{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        // Pengaturan cetak.
        $page = $sheet->getPageSetup();
        $page->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.6)->setLeft(0.4)->setRight(0.4);
        $sheet->getPageSetup()->setHorizontalCentered(true);
        $page->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
        $page->setPrintArea('A1:'.self::LAST.($row - 1));
        $sheet->getHeaderFooter()->setOddFooter('&L&8'.str_replace('&', '&&', (string) Settings::get('school_name')).' — Rekap validasi&R&8Halaman &P dari &N');

        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
    }
}
