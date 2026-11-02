<?php

declare(strict_types=1);

namespace App\Services\Documents;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Template kisi-kisi (.xlsx) sesuai format yang dibaca BESTIE. Lembar pertama "Kisi-kisi" (judul kolom di baris 1,
 * contoh nomor 1–8 selaras dengan template naskah); lembar kedua "Petunjuk" berisi penjelasan kolom, kode bentuk soal, dan level kognitif.
 */
final class BlueprintTemplateWorkbook
{
    public const HEADINGS = ['No.', 'Capaian Pembelajaran', 'Materi', 'Submateri', 'Indikator Soal', 'No. Soal', 'Level Kognitif', 'Bentuk Soal'];

    /** Baris contoh: [CP, materi, submateri, indikator, no soal, level, bentuk]. CP/materi/submateri kosong = sel digabung dengan baris di atasnya. */
    public const SAMPLE = [
        ['Peserta didik mampu melakukan operasi hitung bilangan', 'Bilangan', 'Pecahan', 'Disajikan operasi pecahan, peserta didik dapat menentukan hasil penjumlahannya', 1, 'C2', 'PG'],
        ['', '', '', 'Disajikan kutipan, peserta didik dapat menentukan pesan utama', 2, 'C4', 'PG'],
        ['', '', 'Bilangan prima', 'Disajikan beberapa pernyataan, peserta didik dapat memilih dua pernyataan yang benar tentang bilangan prima', 3, 'C4', 'PGK'],
        ['', '', '', 'Disajikan pernyataan, peserta didik dapat menentukan benar atau salahnya', 4, 'C1', 'BS'],
        ['', '', '', 'Disajikan pernyataan, peserta didik dapat menentukan benar atau salahnya', 5, 'C2', 'BS'],
        ['', 'Wawasan kebangsaan', 'Ibu kota provinsi', 'Disajikan nama ibu kota, peserta didik dapat menjodohkan dengan provinsinya (5 pernyataan, 7 pilihan jawaban)', 6, 'C2', 'MJD'],
        ['', '', 'Perkalian', 'Disajikan perkalian dua bilangan, peserta didik dapat menentukan hasilnya', 7, 'C1', 'IS'],
        ['Peserta didik mampu bernalar dan menyampaikan pendapat', 'Literasi', 'Manfaat membaca', 'Disajikan permasalahan, peserta didik dapat menjelaskan manfaat membaca disertai alasan', 8, 'C5', 'Uraian'],
    ];

    public function build(string $path): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Kisi-kisi');

        foreach (['A' => 6, 'B' => 34, 'C' => 22, 'D' => 22, 'E' => 52, 'F' => 10, 'G' => 12, 'H' => 12] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        foreach (self::HEADINGS as $i => $text) {
            $sheet->setCellValueExplicit([$i + 1, 1], $text, DataType::TYPE_STRING);
        }

        $head = $sheet->getStyle('A1:H1');
        $head->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
        $head->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
        $head->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        // Kolom WAJIB (Indikator Soal, No. Soal, Bentuk Soal) diberi warna oranye; kolom lain opsional.
        foreach (['E1', 'F1', 'H1'] as $cell) {
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C2410C');
        }

        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->freezePane('A2');

        $row = 2;

        foreach (self::SAMPLE as $i => [$cp, $materi, $sub, $indikator, $no, $level, $bentuk]) {
            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValueExplicit("B{$row}", $cp, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$row}", $materi, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("D{$row}", $sub, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("E{$row}", $indikator, DataType::TYPE_STRING);
            $sheet->setCellValue("F{$row}", $no);
            $sheet->setCellValueExplicit("G{$row}", $level, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("H{$row}", $bentuk, DataType::TYPE_STRING);
            $row++;
        }

        // Sel digabung pada contoh (CP baris 2–8 pertama, dst.) menunjukkan cara mengisi: cukup isi sel pertama.
        foreach (['B2:B8', 'C2:C6', 'C7:C8', 'D2:D3', 'D4:D6'] as $range) {
            $sheet->mergeCells($range);
        }

        $last = 2 + count(self::SAMPLE) - 1;
        $sheet->getStyle("A1:H{$last}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('9CA3AF');
        $sheet->getStyle("A2:H{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle("A2:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("F2:H{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Daftar pilihan (dropdown) untuk 200 baris.
        foreach (['G' => '"C1,C2,C3,C4,C5,C6"', 'H' => '"PG,PGK,BS,MJD,IS,Uraian"'] as $col => $list) {
            $v = new DataValidation;
            $v->setType(DataValidation::TYPE_LIST);
            $v->setErrorStyle(DataValidation::STYLE_WARNING);
            $v->setAllowBlank(true);
            $v->setShowDropDown(true);
            $v->setShowErrorMessage(true);
            $v->setErrorTitle('Nilai tidak dikenal');
            $v->setError($col === 'G' ? 'Gunakan C1–C6.' : 'Gunakan PG, PGK, BS, MJD, IS, atau Uraian.');
            $v->setFormula1($list);
            $sheet->setDataValidation("{$col}2:{$col}201", $v);
        }

        $page = $sheet->getPageSetup();
        $page->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageSetup()->setFitToPage(true);

        // ---- Lembar petunjuk
        $guide = $book->createSheet();
        $guide->setTitle('Petunjuk');
        $guide->getSheetView()->setZoomScale(110);
        $guide->getColumnDimension('A')->setWidth(26);
        $guide->getColumnDimension('B')->setWidth(86);

        $lines = [
            ['PETUNJUK PENGISIAN KISI-KISI', null, 'title'],
            ['WAJIB diisi: Indikator Soal, No. Soal, dan Bentuk Soal (judul kolomnya berwarna oranye). Kolom lain bersifat opsional.', null, 'note'],
            ['Isi lembar "Kisi-kisi" mulai baris 2. Baris contoh (nomor 1–8) boleh dihapus. Jangan mengubah judul kolom di baris 1.', null, 'note'],
            [null, null, 'gap'],
            ['Kolom', 'Cara mengisi', 'head'],
            ['No.', 'OPSIONAL. Nomor urut baris (tidak dipakai untuk mencocokkan).', null],
            ['Capaian Pembelajaran', 'OPSIONAL. Tulis CP/KD. Boleh menggabungkan sel (merge) ke bawah; baris di bawahnya otomatis mewarisi nilai ini.', null],
            ['Materi', 'OPSIONAL. Materi pokok. Boleh sel digabung; diwarisi seperti CP.', null],
            ['Submateri', 'OPSIONAL. Submateri. Boleh sel digabung.', null],
            ['Indikator Soal', 'WAJIB. Satu indikator per nomor soal. Rumuskan: "Disajikan …, peserta didik dapat …".', null],
            ['No. Soal', 'WAJIB. NOMOR SOAL pada naskah. Kolom inilah yang dicocokkan dengan nomor di naskah (angka saja, tanpa titik).', null],
            ['Level Kognitif', 'OPSIONAL. C1 Pengetahuan · C2 Pemahaman · C3 Aplikasi · C4 Analisis · C5 Evaluasi · C6 Kreasi.', null],
            ['Bentuk Soal', 'WAJIB. PG = Pilihan Ganda · PGK = Pilihan Ganda Kompleks · BS = Benar/Salah · MJD = Menjodohkan · IS = Isian Singkat · Uraian.', null],
            [null, null, 'gap'],
            ['Tips', null, 'head'],
            ['Sesuaikan dengan naskah', 'Jumlah baris, nomor, dan bentuk soal harus sama dengan naskah. Bila berbeda, BESTIE menampilkan peringatan sebelum paket dikirim.', null],
            ['Format file', 'Kisi-kisi dapat diunggah sebagai Excel (.xlsx), Word (.docx), atau PDF berteks. Excel paling akurat.', null],
            ['Satu nomor satu baris', 'Jangan menggabungkan sel pada kolom Indikator, No. Soal, Level, dan Bentuk; hanya CP/Materi/Submateri yang boleh digabung.', null],
        ];

        $r = 1;

        foreach ($lines as [$a, $b, $kind]) {
            if ($kind === 'gap') {
                $r++;

                continue;
            }

            if ($kind === 'title') {
                $guide->setCellValueExplicit("A{$r}", (string) $a, DataType::TYPE_STRING);
                $guide->getStyle("A{$r}")->getFont()->setBold(true)->setSize(15)->setColor(new Color('FF1E3A8A'));
            } elseif ($kind === 'note') {
                $guide->mergeCells("A{$r}:B{$r}");
                $guide->setCellValueExplicit("A{$r}", (string) $a, DataType::TYPE_STRING);
                $guide->getStyle("A{$r}")->getAlignment()->setWrapText(true);
                $guide->getRowDimension($r)->setRowHeight(34);
            } else {
                $guide->setCellValueExplicit("A{$r}", (string) $a, DataType::TYPE_STRING);
                $guide->setCellValueExplicit("B{$r}", (string) $b, DataType::TYPE_STRING);
                $range = $guide->getStyle("A{$r}:B{$r}");
                $range->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
                $range->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');
                $guide->getStyle("A{$r}")->getFont()->setBold(true);

                if ($kind === 'head') {
                    $range->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
                    $range->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
                } else {
                    $guide->getRowDimension($r)->setRowHeight(32);
                }
            }

            $r++;
        }

        $book->setActiveSheetIndex(0);
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
    }
}
