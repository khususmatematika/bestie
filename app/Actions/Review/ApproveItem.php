<?php

declare(strict_types=1);

namespace App\Actions\Review;

use App\Actions\Package\RecalculatePackageProgress;
use App\Enums\ItemStatus;
use App\Enums\ReviewDecision;
use App\Exceptions\WorkflowException;
use App\Models\ItemReview;
use App\Models\QuestionItem;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\ReviewChecklist;
use Illuminate\Support\Facades\DB;

/**
 * Validator menyetujui satu nomor (FR-REV-02, R-13). Paket otomatis FIX saat 100% disetujui (R-06).
 */
final class ApproveItem
{
    use LoadsReviewContext;

    public function __construct(private readonly RecalculatePackageProgress $recalculate)
    {
    }

    /**
     * @param  array<string, mixed>  $checklist
     */
    public function handle(QuestionItem $item, User $validator, array $checklist = [], ?string $note = null): QuestionItem
    {
        return DB::transaction(function () use ($item, $validator, $checklist, $note): QuestionItem {
            [$package, $item] = $this->context($item, $validator);

            if (! in_array($item->status, [ItemStatus::BelumDitelaah, ItemStatus::Direvisi], true)) {
                throw new WorkflowException("Nomor {$item->number} berstatus '{$item->status->label()}' dan tidak dapat disetujui.");
            }

            $now = now();

            ItemReview::query()->create([
                'item_id' => $item->id,
                'revision_id' => $item->current_revision_id,
                'validator_id' => $validator->id,
                'decision' => ReviewDecision::Setuju,
                'checklist' => ReviewChecklist::normalize($checklist),
                'note' => trim((string) $note) === '' ? null : mb_substr(trim((string) $note), 0, 5000),
                'reviewed_at' => $now,
            ]);

            $item->update(['status' => ItemStatus::Disetujui, 'reviewed_at' => $now]);

            $this->startReview($package);
            $this->recalculate->handle($package);

            ActivityLogger::log('item.approved', $package, ['number' => $item->number], $validator);

            return $item;
        });
    }
}
