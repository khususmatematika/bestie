<?php

declare(strict_types=1);

namespace App\Services\Similarity;

use App\Enums\PackageStatus;
use App\Models\ArchiveItem;
use App\Models\QuestionItem;
use App\Models\QuestionPackage;

/**
 * Deteksi kemiripan butir soal: bandingkan teks soal + pilihan dengan (1) arsip naskah tahun lalu yang diimpor admin,
 * (2) paket Fix/terkunci pada semester lain, dan (3) paket lain pada periode yang sama, untuk mapel yang sama.
 * Ukuran: Jaccard n-gram kata (3-gram; 2-gram untuk teks pendek) atas teks ternormalisasi.
 * Hanya penanda bantu bagi validator; keputusan tetap di tangan manusia.
 */
final class ItemSimilarity
{
    public const MIN_SCORE = 0.4;

    public const HIGH = 0.7;

    public const MEDIUM = 0.5;

    private const MAX_CANDIDATES = 1500;

    private const TOP = 5;

    /** Teks butir (soal + pilihan) yang dibandingkan. */
    public static function textOf(?string $stem, mixed $options): string
    {
        $parts = [(string) $stem];

        foreach ((array) $options as $option) {
            if (is_string($option)) {
                $parts[] = $option;
            }
        }

        return implode(' ', $parts);
    }

    /** Huruf kecil, tanpa penanda gambar/rumus dan tanda baca; spasi dirapatkan. */
    public static function normalize(string $text): string
    {
        $text = preg_replace('/⟦[^⟧]*⟧/u', ' ', $text) ?? $text;
        $text = preg_replace('/<\/?(?:b|i|u|sub|sup)>/iu', '', $text) ?? $text;
        $text = str_replace(['\\(', '\\)'], ' ', $text);
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @return array<string, true>
     */
    public static function shingles(string $normalized): array
    {
        $words = $normalized === '' ? [] : explode(' ', $normalized);
        $n = count($words) >= 6 ? 3 : 2;
        $set = [];

        if (count($words) < $n) {
            return $words === [] ? [] : [implode(' ', $words) => true];
        }

        for ($i = 0, $max = count($words) - $n; $i <= $max; $i++) {
            $set[implode(' ', array_slice($words, $i, $n))] = true;
        }

        return $set;
    }

    /**
     * @param  array<string, true>  $a
     * @param  array<string, true>  $b
     */
    public static function jaccard(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        $intersection = count(array_intersect_key($a, $b));

        return $intersection / (count($a) + count($b) - $intersection);
    }

    /**
     * Skor 0..1 antara dua butir: teks lengkap, atau teks soal saja (bobot 0,85) bila pilihan diganti.
     *
     * @param  array<string, true>  $fullA
     * @param  array<string, true>  $stemA
     */
    public static function score(array $fullA, array $stemA, string $stemB, string $fullB): float
    {
        $full = self::jaccard($fullA, self::shingles(self::normalize($fullB)));
        $stem = self::jaccard($stemA, self::shingles(self::normalize($stemB)));

        return max($full, $stem * 0.85);
    }

    /**
     * @return list<array{score: int, level: string, source: string, label: string, number: int, type: string|null, stem: string, options: array<string, string>}>
     */
    public function for(QuestionItem $item, QuestionPackage $package): array
    {
        $package->loadMissing('assessmentPeriod.semester.year');

        return $this->rank($item, iterator_to_array($this->candidates($package), false));
    }

    /**
     * Kemiripan tertinggi tiap butir pada satu paket (pembanding dimuat sekali). Kunci = id butir.
     *
     * @return array<int, array{score: int, level: string, label: string, number: int}|null>
     */
    public function topForPackage(QuestionPackage $package): array
    {
        $package->loadMissing('assessmentPeriod.semester.year');
        $candidates = iterator_to_array($this->candidates($package), false);
        $result = [];

        foreach (QuestionItem::query()->where('package_id', $package->id)->get() as $item) {
            $top = $this->rank($item, $candidates, 0.0)[0] ?? null;
            $result[$item->id] = $top === null ? null : ['score' => $top['score'], 'level' => $top['level'], 'label' => $top['label'], 'number' => $top['number']];
        }

        return $result;
    }

    /**
     * @param  list<array{source: string, label: string, number: int, type: string|null, stem: string, options: array<string, string>}>  $candidates
     * @return list<array{score: int, level: string, source: string, label: string, number: int, type: string|null, stem: string, options: array<string, string>}>
     */
    private function rank(QuestionItem $item, array $candidates, float $min = self::MIN_SCORE): array
    {
        $fullA = self::shingles(self::normalize(self::textOf($item->stem, $item->options)));
        $stemA = self::shingles(self::normalize((string) $item->stem));

        if ($fullA === []) {
            return [];
        }

        $matches = [];

        foreach ($candidates as $candidate) {
            $score = self::score($fullA, $stemA, $candidate['stem'], self::textOf($candidate['stem'], $candidate['options']));

            if ($score >= $min) {
                $matches[] = ['raw' => $score] + $candidate;
            }
        }

        usort($matches, static fn (array $a, array $b): int => $b['raw'] <=> $a['raw']);

        return array_map(static fn (array $m): array => [
            'score' => (int) round($m['raw'] * 100),
            'level' => $m['raw'] >= self::HIGH ? 'tinggi' : ($m['raw'] >= self::MEDIUM ? 'sedang' : 'rendah'),
            'source' => $m['source'],
            'label' => $m['label'],
            'number' => $m['number'],
            'type' => $m['type'],
            'stem' => $m['stem'],
            'options' => $m['options'],
        ], array_slice($matches, 0, self::TOP));
    }

    /**
     * @return \Generator<int, array{source: string, label: string, number: int, type: string|null, stem: string, options: array<string, string>}>
     */
    private function candidates(QuestionPackage $package): \Generator
    {
        $subjectId = $package->subject_id;
        $semesterId = $package->assessmentPeriod->semester_id;

        foreach (ArchiveItem::query()->with('import.gradeLevel:id,name')
            ->whereHas('import', fn ($q) => $q->where('subject_id', $subjectId))
            ->limit(self::MAX_CANDIDATES)->get() as $row) {
            $grade = $row->import->gradeLevel?->name;

            yield [
                'source' => 'arsip',
                'label' => 'Arsip '.$row->import->year_label.($grade !== null ? ' · Kelas '.$grade : ''),
                'number' => $row->number,
                'type' => $row->type,
                'stem' => $row->stem,
                'options' => $this->options($row->options),
            ];
        }

        $others = QuestionItem::query()
            ->with(['package.assessmentPeriod.semester.year', 'package.teacher:id,name', 'package.gradeLevel:id,name'])
            ->whereHas('package', function ($q) use ($subjectId, $package, $semesterId): void {
                $q->where('subject_id', $subjectId)->where('id', '!=', $package->id)->where(function ($w) use ($semesterId): void {
                    $w->where(function ($old) use ($semesterId): void {
                        $old->whereIn('status', [PackageStatus::Fix->value, PackageStatus::Terkunci->value])
                            ->whereHas('assessmentPeriod', fn ($p) => $p->where('semester_id', '!=', $semesterId));
                    })->orWhere(function ($same) use ($semesterId): void {
                        $same->where('status', '!=', PackageStatus::Draft->value)
                            ->whereHas('assessmentPeriod', fn ($p) => $p->where('semester_id', $semesterId));
                    });
                });
            })
            ->limit(self::MAX_CANDIDATES)
            ->get();

        foreach ($others as $row) {
            $other = $row->package;
            $sameSemester = $other->assessmentPeriod->semester_id === $semesterId;

            yield [
                'source' => $sameSemester ? 'periode_ini' : 'fix_lama',
                'label' => ($sameSemester ? 'Paket lain periode ini' : 'Paket Fix '.$other->assessmentPeriod->semester->label())
                    .' · '.$other->teacher->name.' · Kelas '.$other->gradeLevel->name,
                'number' => $row->number,
                'type' => $row->type->value,
                'stem' => (string) $row->stem,
                'options' => $this->options($row->options),
            ];
        }
    }

    /**
     * @return array<string, string>
     */
    private function options(mixed $options): array
    {
        return array_map('strval', array_filter((array) $options, 'is_string'));
    }
}
