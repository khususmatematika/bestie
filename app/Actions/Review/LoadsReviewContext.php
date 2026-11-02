<?php

declare(strict_types=1);

namespace App\Actions\Review;

use App\Actions\Package\PackageGuard;
use App\Enums\PackageStatus;
use App\Exceptions\WorkflowException;
use App\Models\QuestionItem;
use App\Models\QuestionPackage;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Langkah awal yang sama untuk semua keputusan validator: kunci baris, otorisasi kepemilikan (R-10),
 * pastikan paket masih boleh diubah (R-08, R-12), dan tandai paket mulai ditelaah.
 */
trait LoadsReviewContext
{
    /**
     * @return array{0: QuestionPackage, 1: QuestionItem}
     */
    private function context(QuestionItem $item, User $validator): array
    {
        $package = QuestionPackage::query()->with('assessmentPeriod.semester')->lockForUpdate()->findOrFail($item->package_id);

        Gate::forUser($validator)->authorize('review', $package);
        PackageGuard::ensureWritable($package);

        $locked = QuestionItem::query()->lockForUpdate()->findOrFail($item->id);

        return [$package, $locked];
    }

    private function startReview(QuestionPackage $package): void
    {
        if ($package->status === PackageStatus::Terkirim) {
            $package->update(['status' => PackageStatus::Ditelaah, 'first_reviewed_at' => now()]);
        }
    }

    private function requireNote(?string $note, string $message): string
    {
        $note = trim((string) $note);

        if (mb_strlen($note) < 10) {
            throw new WorkflowException($message);
        }

        return mb_substr($note, 0, 5000);
    }
}
