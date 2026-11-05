<?php

declare(strict_types=1);

namespace App\Enums;

enum PeriodType: string
{
    case Sas = 'sas';
    case Sat = 'sat';
    case Saj = 'saj';

    /** Jenis lama (data yang sudah ada tetap terbaca), tidak lagi ditawarkan pada formulir. */
    case Sts = 'sts';

    public function label(): string
    {
        return match ($this) {
            self::Sas => 'Sumatif Akhir Semester',
            self::Sat => 'Sumatif Akhir Tahun',
            self::Saj => 'Sumatif Akhir Jenjang',
            self::Sts => 'Sumatif Tengah Semester (lama)',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Sas => 'SAS',
            self::Sat => 'SAT',
            self::Saj => 'SAJ',
            self::Sts => 'STS',
        };
    }

    /**
     * Jenis yang dapat dipilih admin saat membuat periode.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return [self::Sas, self::Sat, self::Saj];
    }
}
