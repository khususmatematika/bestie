<?php

declare(strict_types=1);

namespace App\Services\Export;

use Carbon\CarbonInterface;

/**
 * Pemformat waktu untuk rekap dan dokumen ekspor (zona waktu aplikasi).
 */
final class RecapRows
{
    public static function stamp(?CarbonInterface $time): string
    {
        return $time === null ? '—' : $time->copy()->timezone((string) config('banksoal.timezone', 'Asia/Jakarta'))->format('d/m/Y H:i');
    }
}
