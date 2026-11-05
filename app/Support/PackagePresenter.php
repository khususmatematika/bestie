<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ItemReview;
use App\Models\PackageFile;
use App\Models\QuestionItem;
use App\Models\QuestionPackage;

/**
 * Bentuk data paket/butir yang dikirim ke halaman Vue. Semua relasi yang dipakai WAJIB sudah dimuat
 * (lazy loading dimatikan di non-produksi).
 */
final class PackagePresenter
{
    /**
     * Relasi minimum untuk ringkasan paket.
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return ['subject:id,name,code', 'gradeLevel:id,name', 'assessmentPeriod.semester.year', 'teacher:id,name', 'validator:id,name'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(QuestionPackage $package): array
    {
        return [
            'id' => $package->id,
            'title' => $package->title,
            'status' => $package->status->value,
            'status_label' => $package->status->label(),
            'progress' => $package->progress_percent,
            'total_items' => $package->total_items,
            'approved_items' => $package->approved_items,
            'subject' => $package->subject->name,
            'subject_code' => $package->subject->code,
            'grade' => $package->gradeLevel->name,
            'period' => $package->assessmentPeriod->name,
            'teacher' => $package->teacher->name,
            'validator' => $package->validator?->name,
            'is_self_validated' => $package->is_self_validated,
            'submitted_at' => $package->submitted_at?->toIso8601String(),
            'first_reviewed_at' => $package->first_reviewed_at?->toIso8601String(),
            'fixed_at' => $package->fixed_at?->toIso8601String(),
            'locked_at' => $package->locked_at?->toIso8601String(),
        ];
    }

    /**
     * Butir lengkap (isi + riwayat). Relasi reviews.validator dan revisions harus sudah dimuat.
     *
     * @return array<string, mixed>
     */
    public static function item(QuestionItem $item): array
    {
        return [
            'id' => $item->id,
            'number' => $item->number,
            'status' => $item->status->value,
            'status_label' => $item->status->label(),
            'type' => $item->type->value,
            'type_label' => $item->type->label(),
            'blueprint' => [
                'cp' => $item->blueprint['cp'] ?? '',
                'materi' => $item->blueprint['materi'] ?? '',
                'submateri' => $item->blueprint['submateri'] ?? '',
                'indikator' => $item->blueprint['indikator'] ?? '',
                'level' => $item->blueprint['level'] ?? '',
                'bobot' => $item->blueprint['bobot'] ?? null,
            ],
            'stem' => (string) $item->stem,
            'options' => (object) ($item->options ?? []),
            'answer_key' => (string) $item->answer_key,
            'scoring_guide' => (string) $item->scoring_guide,
            'image_map' => (object) collect($item->images ?? [])->mapWithKeys(static fn (int $id): array => [(string) $id => route('packages.files.download', [$item->package_id, $id]).'?inline=1'])->all(),
            'images' => self::unreferencedImages($item),
            'missing' => $item->missingFields(),
            'reviewed_at' => $item->reviewed_at?->toIso8601String(),
            'resubmitted_at' => $item->resubmitted_at?->toIso8601String(),
            'version' => $item->revisions->max('version') ?? 1,
            'reviews' => $item->reviews->map(static fn (ItemReview $r): array => [
                'id' => $r->id,
                'decision' => $r->decision->value,
                'decision_label' => $r->decision->label(),
                'note' => $r->note,
                'checklist' => $r->checklist,
                'validator' => $r->validator->name,
                'reviewed_at' => $r->reviewed_at->toIso8601String(),
            ])->values()->all(),
            'revisions' => $item->revisions->map(static fn ($rev): array => [
                'id' => $rev->id,
                'version' => $rev->version,
                'source' => $rev->source,
                'uploaded_at' => $rev->uploaded_at->toIso8601String(),
                'uploader' => $rev->uploader->name,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function file(PackageFile $file): array
    {
        $report = $file->extraction_report ?? [];

        return [
            'id' => $file->id,
            'kind' => $file->kind->value,
            'kind_label' => $file->kind->label(),
            'name' => $file->original_name,
            'size' => $file->size,
            'extraction_status' => $file->extraction_status->value,
            'extraction_label' => $file->extraction_status->label(),
            'error' => $report['error'] ?? null,
            'warnings' => array_values(array_unique([...($report['warnings'] ?? []), ...($report['package_warnings'] ?? [])])),
            'uploaded_at' => $file->created_at?->toIso8601String(),
        ];
    }

    /**
     * URL gambar yang tidak dirujuk penanda ⟦img:ID⟧ pada teks (ditampilkan di bawah soal).
     *
     * @return list<string>
     */
    private static function unreferencedImages(QuestionItem $item): array
    {
        $text = $item->stem.' '.implode(' ', array_filter((array) ($item->options ?? []), 'is_string')).' '.$item->answer_key.' '.$item->scoring_guide;
        preg_match_all('/⟦img:(\d+)⟧/u', $text, $m);
        $used = array_map('intval', $m[1]);

        return collect($item->images ?? [])
            ->reject(static fn (int $id): bool => in_array($id, $used, true))
            ->map(static fn (int $id): string => route('packages.files.download', [$item->package_id, $id]).'?inline=1')
            ->values()
            ->all();
    }
}
