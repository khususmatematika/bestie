<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Validator = 'validator';
    case Teacher = 'teacher';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Validator => 'Validator Soal',
            self::Teacher => 'Guru',
        };
    }

    /**
     * Urutan prioritas peran aktif bawaan saat login.
     *
     * @return list<self>
     */
    public static function priority(): array
    {
        return [self::Admin, self::Validator, self::Teacher];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
