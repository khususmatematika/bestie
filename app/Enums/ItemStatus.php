<?php

declare(strict_types=1);

namespace App\Enums;

enum ItemStatus: string
{
    case BelumDitelaah = 'belum_ditelaah';
    case Disetujui = 'disetujui';
    case PerluGanti = 'perlu_ganti';
    case Direvisi = 'direvisi';

    public function label(): string
    {
        return match ($this) {
            self::BelumDitelaah => 'Belum ditelaah',
            self::Disetujui => 'Disetujui',
            self::PerluGanti => 'Perlu ganti',
            self::Direvisi => 'Direvisi',
        };
    }
}
