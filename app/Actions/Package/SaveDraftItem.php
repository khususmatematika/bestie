<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Exceptions\WorkflowException;
use App\Models\ItemRevision;
use App\Models\QuestionItem;
use App\Models\QuestionPackage;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\ItemContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Menambah / mengubah / menghapus nomor soal secara manual pada paket draft (FR-PKG-04, input manual).
 * Selama draft, perubahan menimpa revisi v1; versi baru baru dibuat saat guru mengunggah ulang (R-05).
 */
final class SaveDraftItem
{
    private const MAX_NUMBER = 200;

    public function __construct(private readonly RecalculatePackageProgress $recalculate)
    {
    }

    public function add(QuestionPackage $package, User $teacher, ?int $number = null): QuestionItem
    {
        return DB::transaction(function () use ($package, $teacher, $number): QuestionItem {
            $package = $this->lockedDraft($package, $teacher);

            $max = (int) QuestionItem::query()->where('package_id', $package->id)->max('number');
            $number ??= $max + 1;

            if ($number < 1 || $number > self::MAX_NUMBER) {
                throw new WorkflowException('Nomor soal harus antara 1 dan '.self::MAX_NUMBER.'.');
            }

            if (QuestionItem::query()->where('package_id', $package->id)->where('number', $number)->exists()) {
                throw new WorkflowException("Nomor {$number} sudah ada.");
            }

            $content = ItemContent::normalize([]);

            $item = QuestionItem::query()->create([
                'package_id' => $package->id,
                'number' => $number,
                'blueprint' => $content['blueprint'],
                'type' => $content['type'],
                'stem' => $content['stem'],
                'options' => $content['options'],
                'answer_key' => $content['answer_key'],
                'scoring_guide' => $content['scoring_guide'],
            ]);

            $this->writeRevision($item, $teacher);
            $this->recalculate->handle($package);

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(QuestionItem $item, User $teacher, array $input): QuestionItem
    {
        return DB::transaction(function () use ($item, $teacher, $input): QuestionItem {
            $this->lockedDraft(QuestionPackage::query()->findOrFail($item->package_id), $teacher);

            $item = QuestionItem::query()->lockForUpdate()->findOrFail($item->id);
            $content = ItemContent::normalize($input);

            $item->update([
                'blueprint' => $content['blueprint'],
                'type' => $content['type'],
                'stem' => $content['stem'],
                'options' => $content['options'],
                'answer_key' => $content['answer_key'],
                'scoring_guide' => $content['scoring_guide'],
                // Gambar yang penandanya sudah dihapus dari teks tidak lagi melekat pada butir.
                'images' => array_values(array_intersect((array) ($item->images ?? []), ItemContent::referencedImageIds($content))) ?: null,
            ]);

            $this->writeRevision($item, $teacher);

            return $item;
        });
    }

    public function delete(QuestionItem $item, User $teacher): void
    {
        DB::transaction(function () use ($item, $teacher): void {
            $package = $this->lockedDraft(QuestionPackage::query()->findOrFail($item->package_id), $teacher);

            QuestionItem::query()->whereKey($item->id)->delete();

            // Nomor sesudahnya maju satu (33 dihapus: 34→33, 35→34, …) agar urutan tetap rapat. Berurutan naik
            // supaya tidak bentrok dengan indeks unik (package_id, number).
            QuestionItem::query()
                ->where('package_id', $package->id)
                ->where('number', '>', $item->number)
                ->orderBy('number')
                ->get()
                ->each(static fn (QuestionItem $later) => $later->update(['number' => $later->number - 1]));

            $this->recalculate->handle($package);

            ActivityLogger::log('item.deleted_draft', $package, ['number' => $item->number], $teacher);
        });
    }

    private function lockedDraft(QuestionPackage $package, User $teacher): QuestionPackage
    {
        $package = QuestionPackage::query()->with('assessmentPeriod.semester')->lockForUpdate()->findOrFail($package->id);

        Gate::forUser($teacher)->authorize('manage', $package);
        PackageGuard::ensureWritable($package);

        if (! $package->isDraft()) {
            throw new WorkflowException('Isi nomor hanya dapat diubah langsung saat paket masih draft. Setelah dikirim, nomor diperbarui lewat unggah ulang atas permintaan validator.');
        }

        return $package;
    }

    /**
     * Menjaga revisi v1 tetap sinkron dengan isi butir selama paket masih draft.
     */
    private function writeRevision(QuestionItem $item, User $teacher): void
    {
        $revision = $item->current_revision_id === null
            ? null
            : ItemRevision::query()->find($item->current_revision_id);

        if ($revision === null) {
            $revision = ItemRevision::query()->create([
                'item_id' => $item->id,
                'version' => 1,
                'snapshot' => $item->content(),
                'source' => 'editor',
                'uploaded_by' => $teacher->id,
                'uploaded_at' => now(),
            ]);

            $item->update(['current_revision_id' => $revision->id]);

            return;
        }

        $revision->update([
            'snapshot' => $item->content(),
            'source' => 'editor',
            'uploaded_by' => $teacher->id,
            'uploaded_at' => now(),
        ]);
    }
}
