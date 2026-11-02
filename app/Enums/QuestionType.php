<?php

declare(strict_types=1);

namespace App\Enums;

enum QuestionType: string
{
    case PilihanGanda = 'pg';
    case PilihanGandaKompleks = 'pg_kompleks';
    case Isian = 'isian';
    case Uraian = 'uraian';
    case Menjodohkan = 'menjodohkan';
    case BenarSalah = 'benar_salah';

    public function label(): string
    {
        return match ($this) {
            self::PilihanGanda => 'Pilihan ganda',
            self::PilihanGandaKompleks => 'Pilihan ganda kompleks',
            self::Isian => 'Isian singkat',
            self::Uraian => 'Uraian',
            self::Menjodohkan => 'Menjodohkan',
            self::BenarSalah => 'Benar/Salah',
        };
    }

    public function hasOptions(): bool
    {
        return in_array($this, [self::PilihanGanda, self::PilihanGandaKompleks, self::Menjodohkan], true);
    }

    /**
     * Uraian/isian cukup memakai pedoman penskoran sebagai kunci.
     */
    public function acceptsScoringGuideAsKey(): bool
    {
        return in_array($this, [self::Uraian, self::Isian], true);
    }

    /**
     * Menerjemahkan isian bebas ("PG", "Pilihan Ganda", "essay"…) ke tipe soal.
     */
    public static function fromInput(?string $value): ?self
    {
        $key = preg_replace('/[^a-z]+/', '', mb_strtolower(trim((string) $value))) ?? '';

        return match (true) {
            $key === '' => null,
            in_array($key, ['pg', 'pilihanganda', 'pilgan', 'pgs', 'pgbiasa'], true) => self::PilihanGanda,
            in_array($key, ['pgkompleks', 'pilihangandakompleks', 'kompleks', 'pgk', 'pgkompleks'], true) => self::PilihanGandaKompleks,
            in_array($key, ['isian', 'isiansingkat', 'esaisingkat', 'is'], true) => self::Isian,
            in_array($key, ['uraian', 'esai', 'essay', 'esay'], true) => self::Uraian,
            in_array($key, ['menjodohkan', 'jodoh', 'pasangan', 'mjd'], true) => self::Menjodohkan,
            in_array($key, ['bs', 'benarsalah', 'benarsalahb', 'truefalse', 'tf'], true) => self::BenarSalah,
            default => null,
        };
    }
}
