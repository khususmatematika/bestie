<?php

declare(strict_types=1);

namespace App\Enums;

enum ExtractionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Processing => 'Diproses',
            self::Done => 'Selesai',
            self::Failed => 'Gagal',
            self::Skipped => 'Tanpa ekstraksi',
        };
    }
}
