<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Services\Documents\DocxBuilder;
use App\Support\ReviewChecklist;

/**
 * Isi halaman rekap validasi pada DOCX (lanskap): judul, identitas, tabel per nomor dengan sepuluh kolom daftar periksa
 * (biru = terpenuhi, merah = tidak terpenuhi, abu = belum ditelaah), kemiripan tertinggi, keterangan, dan tanda tangan.
 */
final class RecapSection
{
    public const BLUE = '1D4ED8';

    public const RED = 'DC2626';

    /**
     * @param  array<string, mixed>  $data  hasil RecapData::build()
     */
    public static function render(DocxBuilder $doc, array $data): void
    {
        $doc->paragraph('REKAP VALIDASI SOAL', ['bold' => true, 'align' => 'center', 'size' => 14, 'after' => 120]);

        // Identitas: dua pasang label-nilai per baris.
        $identity = $data['identity'];
        $label = ['fill' => 'EEF2FF', 'bold' => true, 'align' => 'left', 'size' => 10];
        $rows = [];

        for ($i = 0; $i < count($identity); $i += 2) {
            $a = $identity[$i];
            $b = $identity[$i + 1] ?? ['', ''];
            $rows[] = [$label + ['text' => $a[0]], ['text' => $a[1], 'bold' => false, 'align' => 'left', 'fill' => 'FFFFFF', 'size' => 10], $label + ['text' => $b[0]], ['text' => $b[1], 'bold' => false, 'align' => 'left', 'fill' => 'FFFFFF', 'size' => 10]];
        }

        $doc->grid([15, 35, 15, 35], $rows, 0, 10);

        // Tabel utama.
        $head = static fn (string $t): array => ['text' => $t, 'fill' => '1E3A8A', 'color' => 'FFFFFF', 'bold' => true, 'align' => 'center', 'size' => 8];
        $header = [$head('No'), $head('Bentuk'), $head('Status')];

        foreach (ReviewChecklist::SHORT as $short) {
            $header[] = $head($short) + ['vertical' => true];
        }

        array_push($header, $head('Kemiripan tertinggi'), $head('Riwayat catatan validator'), $head('Waktu validasi'), $head('Versi'));

        $table = [$header];

        foreach ($data['rows'] as $r) {
            $row = [
                ['text' => (string) $r['number'], 'align' => 'center', 'bold' => true],
                ['text' => $r['type'], 'size' => 8],
                ['text' => $r['status'], 'size' => 8],
            ];

            foreach ($r['checks'] as $met) {
                $row[] = match ($met) {
                    true => ['text' => '✓', 'fill' => self::BLUE, 'color' => 'FFFFFF', 'bold' => true, 'align' => 'center', 'size' => 10],
                    false => ['text' => '✗', 'fill' => self::RED, 'color' => 'FFFFFF', 'bold' => true, 'align' => 'center', 'size' => 10],
                    default => ['text' => '–', 'fill' => 'F3F4F6', 'color' => '6B7280', 'align' => 'center'],
                };
            }

            $score = $r['similarity'];
            $fill = match (RecapData::level($score)) {
                'tinggi' => 'FECACA',
                'sedang' => 'FEF08A',
                default => 'FFFFFF',
            };

            array_push(
                $row,
                ['text' => $score === null ? '—' : $score.'%', 'bold' => true, 'align' => 'center', 'fill' => $fill, 'size' => 9],
                ['text' => $r['note'], 'size' => 8],
                ['text' => $r['validated_at'], 'size' => 8, 'align' => 'center'],
                ['text' => $r['version'], 'align' => 'center', 'size' => 8],
            );

            $table[] = $row;
        }

        if ($data['rows'] === []) {
            $table[] = array_merge([['text' => 'Belum ada nomor pada paket ini.']], array_fill(0, count($header) - 1, ''));
        }

        $doc->grid([3.5, 6, 6, ...array_fill(0, 10, 3.6), 6.5, 30, 7.5, 3.5], $table, 1);

        // Keterangan.
        $doc->paragraph('Keterangan', ['bold' => true, 'size' => 9, 'after' => 20]);
        $doc->paragraph('Kolom daftar periksa: biru (✓) = terpenuhi; merah (✗) = tidak terpenuhi; abu (–) = belum ditelaah. Kemiripan tertinggi = persentase kemiripan terbesar dengan arsip, paket Fix semester lama, atau paket lain pada periode yang sama (merah muda ≥ 70%, kuning ≥ 50%).', ['size' => 8, 'after' => 20]);

        $n = 0;
        $parts = [];

        foreach (ReviewChecklist::SHORT as $key => $short) {
            $parts[] = (++$n).'. '.$short.' = '.ReviewChecklist::ITEMS[$key];
        }

        $doc->paragraph(implode('; ', $parts).'.', ['size' => 8, 'after' => 160]);

        // Tanda tangan.
        $doc->paragraph('Dicetak: '.RecapRows::stamp(now()), ['size' => 9, 'align' => 'right', 'after' => 0]);
        $doc->paragraph('Validator,', ['size' => 10, 'align' => 'right', 'after' => 600]);
        $doc->paragraph($data['validator'] !== '' ? $data['validator'] : '(................................)', ['size' => 10, 'bold' => true, 'align' => 'right', 'after' => 0]);
    }
}
