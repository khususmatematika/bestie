<?php

declare(strict_types=1);

namespace App\Enums;

enum SemesterStatus: string
{
    case Rencana = 'rencana';
    case Aktif = 'aktif';
    case Arsip = 'arsip';

    public function label(): string
    {
        return match ($this) {
            self::Rencana => 'Direncanakan',
            self::Aktif => 'Aktif',
            self::Arsip => 'Arsip',
        };
    }
}
