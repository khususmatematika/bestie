<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Processing => 'Diproses',
            self::Done => 'Selesai',
            self::Failed => 'Gagal',
        };
    }

    public function isFinished(): bool
    {
        return $this === self::Done || $this === self::Failed;
    }
}
