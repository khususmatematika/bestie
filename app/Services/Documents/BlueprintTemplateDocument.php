<?php

declare(strict_types=1);

namespace App\Services\Documents;

/**
 * Template kisi-kisi dalam Word (.docx): satu tabel berjudul kolom standar (lanskap) dengan contoh nomor 1–8.
 * Dibaca BESTIE lewat DocumentRows::fromTables(); kolom WAJIB (Indikator Soal, No. Soal, Bentuk Soal) berjudul oranye.
 */
final class BlueprintTemplateDocument
{
    /** Indeks kolom wajib pada BlueprintTemplateWorkbook::HEADINGS. */
    private const REQUIRED = [4, 5, 7];

    public function build(string $path): void
    {
        $d = new DocxBuilder('Times New Roman', 11, 1.5, null, 'Template kisi-kisi — BESTIE', true);

        $d->paragraph('TEMPLATE KISI-KISI SOAL', ['bold' => true, 'size' => 16, 'align' => 'center', 'after' => 40]);
        $d->paragraph('BESTIE — Berkas Evaluasi Sumatif : Telaah, Integrasi, Eksekusi', ['italic' => true, 'size' => 10, 'align' => 'center', 'after' => 120]);

        $box = ['fill' => 'EEF2FF', 'align' => 'left', 'bold' => false, 'size' => 9];
        $d->grid([100], [[$box + ['text' => implode("\n", [
            'CARA MEMAKAI (teks di luar tabel tidak dibaca; baris contoh nomor 1–8 boleh dihapus)',
            '• WAJIB diisi: Indikator Soal, No. Soal, dan Bentuk Soal (judul kolom berwarna oranye). Capaian Pembelajaran, Materi, Submateri, dan Level Kognitif OPSIONAL.',
            '• No. Soal = nomor pada naskah (angka saja). Kolom inilah yang dicocokkan dengan nomor di naskah. Satu nomor satu baris.',
            '• Bentuk Soal: PG = Pilihan Ganda · PGK = Pilihan Ganda Kompleks · BS = Benar/Salah · MJD = Menjodohkan · IS = Isian Singkat · Uraian.',
            '• Level Kognitif: C1 Pengetahuan · C2 Pemahaman · C3 Aplikasi · C4 Analisis · C5 Evaluasi · C6 Kreasi.',
            '• Capaian Pembelajaran/Materi/Submateri yang sama untuk beberapa nomor cukup ditulis pada baris pertamanya; baris di bawah yang dikosongkan otomatis mewarisi.',
            '• Jangan mengubah judul kolom dan jangan menambah tabel lain sebelum tabel kisi-kisi.',
        ])]]], 0, 9);

        $d->paragraph('', ['after' => 80]);

        $head = [];

        foreach (BlueprintTemplateWorkbook::HEADINGS as $i => $text) {
            $head[] = in_array($i, self::REQUIRED, true)
                ? ['text' => $text, 'fill' => 'C2410C', 'color' => 'FFFFFF', 'bold' => true, 'align' => 'center']
                : ['text' => $text, 'fill' => '1E3A8A', 'color' => 'FFFFFF', 'bold' => true, 'align' => 'center'];
        }

        $rows = [$head];

        foreach (BlueprintTemplateWorkbook::SAMPLE as $i => [$cp, $materi, $sub, $indikator, $no, $level, $bentuk]) {
            $rows[] = [(string) ($i + 1), $cp, $materi, $sub, $indikator, (string) $no, $level, $bentuk];
        }

        $d->grid([5, 16, 13, 13, 28, 8, 9, 8], $rows, 1, 9);

        $d->save($path);
    }
}
