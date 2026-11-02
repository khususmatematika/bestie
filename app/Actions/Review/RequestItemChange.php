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
use App\Support\ReviewChecklist;
use Illuminate\Support\Facades\DB;

/**
 * Validator meminta guru mengganti/merevisi satu nomor; catatan wajib (R-03).
 */
final class RequestItemChange
{
    use LoadsReviewContext;

    public function __construct(private readonly RecalculatePackageProgress $recalculate)
    {
    }

    /**
     * @param  array<string, mixed>  $checklist
     */
    public function handle(QuestionItem $item, User $validator, ?string $note, array $checklist = []): QuestionItem
    {
        $note = $this->requireNote($note, 'Catatan wajib diisi (minimal 10 karakter) saat meminta penggantian soal.');

        return DB::transaction(function () use ($item, $validator, $note, $checklist): QuestionItem {
            [$package, $item] = $this->context($item, $validator);

            if (! in_array($item->status, [ItemStatus::BelumDitelaah, ItemStatus::Direvisi], true)) {
                throw new WorkflowException("Nomor {$item->number} berstatus '{$item->status->label()}' dan tidak dapat diminta ganti. Gunakan \"Buka kembali\" untuk nomor yang sudah disetujui.");
            }

            $now = now();

            ItemReview::query()->create([
                'item_id' => $item->id,
                'revision_id' => $item->current_revision_id,
                'validator_id' => $validator->id,
                'decision' => ReviewDecision::Ganti,
                'checklist' => ReviewChecklist::normalize($checklist),
                'note' => $note,
                'reviewed_at' => $now,
            ]);

            $item->update(['status' => ItemStatus::PerluGanti, 'reviewed_at' => $now]);

            $this->startReview($package);
            $this->recalculate->handle($package);

            ActivityLogger::log('item.change_requested', $package, ['number' => $item->number], $validator);
            Notify::to($package->teacher_id, 'Nomor '.$item->number.' diminta diganti', 'Validator '.$validator->name.' meminta penggantian pada paket "'.$package->title.'". Buka catatannya lalu unggah ulang.', '/teacher/packages/'.$package->id, 'minta_ganti');

            return $item;
        });
    }
}
