<?php

declare(strict_types=1);

namespace App\Enums;

enum FileKind: string
{
    case KisiKisi = 'kisi_kisi';
    case Naskah = 'naskah';
    case Lampiran = 'lampiran';

    public function label(): string
    {
        return match ($this) {
            self::KisiKisi => 'Kisi-kisi',
            self::Naskah => 'Naskah soal',
            self::Lampiran => 'Lampiran',
        };
    }
}
