<?php

declare(strict_types=1);

namespace App\Enums;

enum PeriodType: string
{
    case Sts = 'sts';
    case Sas = 'sas';

    public function label(): string
    {
        return match ($this) {
            self::Sts => 'Sumatif Tengah Semester',
            self::Sas => 'Sumatif Akhir Semester',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Sts => 'STS',
            self::Sas => 'SAS',
        };
    }
}
