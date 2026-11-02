<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\QuestionPackage;
use App\Services\Documents\DocxBuilder;
use App\Support\ExportFormat;

/**
 * Rekap validasi siap cetak (DOCX lanskap A4): kop, identitas paket, tabel rekap, keterangan, dan kolom tanda tangan.
 */
final class RecapDocument
{
    public function build(QuestionPackage $package, string $path): void
    {
        $format = ExportFormat::get();
        $data = RecapData::build($package);

        $doc = new DocxBuilder($format['font'], $format['font_size'], min($format['margin_cm'], 1.5), null, trim($format['footer_text'].' — Rekap validasi', ' —'), true);

        KopHeader::apply($doc, $format);
        RecapSection::render($doc, $data);

        $doc->save($path);
    }
}
