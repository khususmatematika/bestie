<?php

declare(strict_types=1);

namespace App\Enums;

enum PeriodStatus: string
{
    case Buka = 'buka';
    case Ditutup = 'ditutup';

    public function label(): string
    {
        return match ($this) {
            self::Buka => 'Dibuka',
            self::Ditutup => 'Ditutup',
        };
    }
}
