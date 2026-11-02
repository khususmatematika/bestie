<?php

declare(strict_types=1);

namespace App\Enums;

enum SemesterTerm: string
{
    case Ganjil = 'ganjil';
    case Genap = 'genap';

    public function label(): string
    {
        return match ($this) {
            self::Ganjil => 'Ganjil',
            self::Genap => 'Genap',
        };
    }
}
