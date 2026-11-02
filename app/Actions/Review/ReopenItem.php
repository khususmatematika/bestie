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
use App\Support\Notify;
use Illuminate\Support\Facades\DB;

/**
 * Validator membuka kembali nomor yang sudah disetujui selama paket belum dikunci; alasan wajib dan tercatat
 * (FR-REV-10, R-07). Paket yang tadinya FIX kembali ke PERLU_REVISI.
 */
final class ReopenItem
{
    use LoadsReviewContext;

    public function __construct(private readonly RecalculatePackageProgress $recalculate)
    {
    }

    public function handle(QuestionItem $item, User $validator, ?string $reason): QuestionItem
    {
        $reason = $this->requireNote($reason, 'Alasan membuka kembali wajib diisi (minimal 10 karakter).');

        return DB::transaction(function () use ($item, $validator, $reason): QuestionItem {
            [$package, $item] = $this->context($item, $validator);

            if ($item->status !== ItemStatus::Disetujui) {
                throw new WorkflowException("Hanya nomor yang sudah disetujui yang dapat dibuka kembali (status nomor {$item->number}: {$item->status->label()}).");
            }

            $now = now();

            ItemReview::query()->create([
                'item_id' => $item->id,
                'revision_id' => $item->current_revision_id,
                'validator_id' => $validator->id,
                'decision' => ReviewDecision::BukaUlang,
                'checklist' => null,
                'note' => $reason,
                'reviewed_at' => $now,
            ]);

            $item->update(['status' => ItemStatus::PerluGanti, 'reviewed_at' => $now]);

            $this->startReview($package);
            $this->recalculate->handle($package);

            ActivityLogger::log('item.reopened', $package, ['number' => $item->number], $validator);
            Notify::to($package->teacher_id, 'Nomor '.$item->number.' dibuka kembali', 'Validator '.$validator->name.' membuka kembali nomor pada paket "'.$package->title.'".', '/teacher/packages/'.$package->id, 'minta_ganti');

            return $item;
        });
    }
}
