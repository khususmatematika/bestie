<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Services\Documents\DocxBuilder;
use App\Support\Settings;

/**
 * Kop surat dokumen DOCX: logo mengambang di kiri, baris atas, nama sekolah, alamat, lalu garis ganda.
 */
final class KopHeader
{
    /**
     * @param  array<string, mixed>  $format  hasil ExportFormat::get()
     * @return list<array{text: string, size: int, bold?: bool}>
     */
    public static function lines(array $format): array
    {
        $size = (int) $format['font_size'];
        $lines = [];

        foreach (array_filter(array_map('trim', explode("\n", (string) $format['kop_top']))) as $line) {
            $lines[] = ['text' => mb_strtoupper($line), 'size' => $size + 4, 'bold' => true];
        }

        $lines[] = ['text' => mb_strtoupper((string) Settings::get('school_name')), 'size' => $size + 8, 'bold' => true];

        foreach (array_filter(array_map('trim', explode("\n", (string) $format['kop_extra']))) as $line) {
            $lines[] = ['text' => $line, 'size' => max(8, $size - 1)];
        }

        return $lines;
    }

    /** Isi berkas logo bila logo diaktifkan dan berkasnya ada. */
    public static function logo(array $format): ?string
    {
        $path = resource_path('images/kop-logo.png');

        return ($format['show_logo'] ?? true) && is_file($path) ? (string) file_get_contents($path) : null;
    }

    /**
     * @param  array<string, mixed>  $format
     */
    public static function apply(DocxBuilder $doc, array $format): void
    {
        $doc->kop(self::logo($format), 'png', self::lines($format));
    }
}
