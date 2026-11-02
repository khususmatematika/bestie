<?php

declare(strict_types=1);

namespace App\Enums;

enum PackageStatus: string
{
    case Draft = 'draft';
    case Terkirim = 'terkirim';
    case Ditelaah = 'ditelaah';
    case PerluRevisi = 'perlu_revisi';
    case Fix = 'fix';
    case Terkunci = 'terkunci';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Terkirim => 'Terkirim',
            self::Ditelaah => 'Ditelaah',
            self::PerluRevisi => 'Perlu revisi',
            self::Fix => 'Fix',
            self::Terkunci => 'Terkunci',
        };
    }

    /**
     * Status saat validator sudah boleh melihat/menelaah paket.
     *
     * @return list<self>
     */
    public static function reviewable(): array
    {
        return [self::Terkirim, self::Ditelaah, self::PerluRevisi, self::Fix];
    }
}
