<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Format dokumen hasil unduhan yang diatur Admin (FR-EXP-02), digabung dengan nilai bawaan.
 */
final class ExportFormat
{
    /**
     * @return array{font: string, font_size: int, margin_cm: float, kop_top: string, kop_extra: string, show_logo: bool, footer_text: string, doc_prefix: string, show_key_sheet: bool, show_blueprint: bool, show_recap: bool}
     */
    public static function get(): array
    {
        $defaults = (array) config('banksoal.settings_defaults.export_format', []);
        $stored = Settings::get('export_format');
        $merged = array_merge($defaults, is_array($stored) ? $stored : []);

        return [
            'font' => (string) $merged['font'],
            'font_size' => max(8, min(18, (int) $merged['font_size'])),
            'margin_cm' => max(1.0, min(4.0, (float) $merged['margin_cm'])),
            // Kop kosong berarti memakai bawaan (identitas sekolah pada konfigurasi).
            'kop_top' => trim((string) ($merged['kop_top'] ?? '')) !== '' ? (string) $merged['kop_top'] : (string) ($defaults['kop_top'] ?? ''),
            'kop_extra' => trim((string) ($merged['kop_extra'] ?? '')) !== '' ? (string) $merged['kop_extra'] : (string) ($defaults['kop_extra'] ?? ''),
            'show_logo' => (bool) ($merged['show_logo'] ?? true),
            'footer_text' => (string) $merged['footer_text'],
            'doc_prefix' => (string) $merged['doc_prefix'],
            'show_key_sheet' => (bool) $merged['show_key_sheet'],
            'show_blueprint' => (bool) $merged['show_blueprint'],
            'show_recap' => (bool) $merged['show_recap'],
        ];
    }

    /** @return list<string> */
    public static function fonts(): array
    {
        return ['Times New Roman', 'Arial', 'Calibri', 'Cambria', 'Tahoma', 'Verdana'];
    }
}
