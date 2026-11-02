<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\QuestionItem;
use App\Models\QuestionPackage;
use App\Services\Similarity\ItemSimilarity;
use App\Support\ReviewChecklist;

/**
 * Data rekap validasi satu paket: identitas paket dan, per nomor, hasil daftar periksa validator (per butir),
 * kemiripan tertinggi, catatan, dan waktu. Dipakai oleh rekap DOCX dan XLSX agar isinya selalu sama.
 */
final class RecapData
{
    /**
     * @return array{identity: list<array{0: string, 1: string}>, rows: list<array<string, mixed>>, summary: array{total: int, approved: int}, validator: string}
     */
    public static function build(QuestionPackage $package): array
    {
        $package->loadMissing(['subject:id,name,category', 'gradeLevel:id,name,phase,phase_elective', 'assessmentPeriod.semester.year', 'teacher:id,name', 'validator:id,name']);

        $items = QuestionItem::query()
            ->with(['revisions', 'reviews.validator:id,name'])
            ->where('package_id', $package->id)
            ->orderBy('number')
            ->get();

        $similar = app(ItemSimilarity::class)->topForPackage($package);

        $rows = $items->map(static function (QuestionItem $item) use ($similar): array {
            $review = $item->reviews->sortBy('id')->last();
            $checked = $item->reviews->sortByDesc('id')->first(static fn ($r): bool => is_array($r->checklist) && $r->checklist !== []);
            $latest = $item->revisions->sortBy('version')->last();
            $reuploaded = $latest !== null && $latest->version > 1 ? $latest->uploaded_at : null;

            $checks = [];

            foreach (array_keys(ReviewChecklist::ITEMS) as $key) {
                $checks[$key] = $checked === null ? null : (bool) ($checked->checklist[$key] ?? false);
            }

            return [
                'number' => $item->number,
                'type' => $item->type->label(),
                'status' => $item->status->label(),
                'validator' => $review?->validator?->name,
                'checks' => $checks,
                'similarity' => isset($similar[$item->id]) ? $similar[$item->id]['score'] : null,
                'note' => $review?->note ?? '—',
                'validated_at' => RecapRows::stamp($item->reviewed_at),
                'reuploaded_at' => RecapRows::stamp($reuploaded),
                'version' => $latest !== null ? 'v'.$latest->version : 'v1',
            ];
        })->values()->all();

        $period = $package->assessmentPeriod;
        $category = $package->subject->category;
        $phase = $category !== null ? $package->gradeLevel->phaseFor($category) : $package->gradeLevel->phase;
        $approved = $items->filter(static fn (QuestionItem $i): bool => $i->status->value === 'disetujui')->count();

        $identity = [
            ['Mata pelajaran', $package->subject->name],
            ['Kelas / Fase', 'Kelas '.$package->gradeLevel->name.($phase ? ' / Fase '.$phase : '')],
            ['Periode asesmen', $period->name],
            ['Tahun pelajaran', $period->semester->year->name.' — Semester '.$period->semester->term->label()],
            ['Penyusun soal', $package->teacher->name],
            ['Validator', ($package->validator?->name ?? '—').($package->is_self_validated ? ' (Divalidasi Mandiri)' : '')],
            ['Status paket', $package->status->label().($package->fixed_at !== null ? ' ('.RecapRows::stamp($package->fixed_at).')' : '')],
            ['Nomor disetujui', $approved.' dari '.$items->count()],
        ];

        return [
            'identity' => $identity,
            'rows' => $rows,
            'summary' => ['total' => $items->count(), 'approved' => $approved],
            'validator' => $package->validator?->name ?? '',
        ];
    }

    /** Tingkat sorotan skor kemiripan: tinggi (>= 70), sedang (>= 50), rendah. */
    public static function level(?int $score): string
    {
        return $score === null ? 'rendah' : ($score >= 70 ? 'tinggi' : ($score >= 50 ? 'sedang' : 'rendah'));
    }
}
