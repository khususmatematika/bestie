<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Enums\PackageStatus;
use App\Exceptions\WorkflowException;
use App\Models\QuestionItem;
use App\Models\QuestionPackage;
use App\Models\User;
use App\Services\ValidatorEligibility;
use App\Support\ActivityLogger;
use App\Support\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Guru mengirim paket ke validator (langkah 3-4 prosedur; FR-PKG-05/06, R-02, R-09, R-11).
 */
final class SubmitPackage
{
    public function __construct(
        private readonly ValidatorEligibility $eligibility,
        private readonly RecalculatePackageProgress $recalculate,
    ) {
    }

    public function handle(QuestionPackage $package, User $teacher, int $validatorId): QuestionPackage
    {
        return DB::transaction(function () use ($package, $teacher, $validatorId): QuestionPackage {
            $package = QuestionPackage::query()->with('assessmentPeriod.semester')->lockForUpdate()->findOrFail($package->id);

            Gate::forUser($teacher)->authorize('manage', $package);
            PackageGuard::ensureWritable($package);

            if (! $package->isDraft()) {
                throw new WorkflowException('Paket ini sudah dikirim.');
            }

            $items = QuestionItem::query()->where('package_id', $package->id)->orderBy('number')->get();

            if ($items->isEmpty()) {
                throw new WorkflowException('Paket belum memiliki nomor soal. Unggah naskah atau tambahkan nomor secara manual.');
            }

            $problems = [];

            foreach ($items as $item) {
                $missing = $item->missingFields();

                if ($missing !== []) {
                    $problems[] = "No. {$item->number}: ".implode(', ', $missing);
                }
            }

            if ($problems !== []) {
                $shown = array_slice($problems, 0, 5);
                $more = count($problems) > 5 ? ' (+'.(count($problems) - 5).' nomor lain)' : '';

                throw new WorkflowException('Paket belum lengkap. '.implode('; ', $shown).$more.'.');
            }

            if (! $this->eligibility->allows($validatorId, $package->assessmentPeriod->semester_id, $package->subject_id, $teacher->id)) {
                throw new WorkflowException('Validator yang dipilih tidak valid untuk mapel ini.');
            }

            $package->update([
                'status' => PackageStatus::Terkirim,
                'validator_id' => $validatorId,
                'is_self_validated' => $validatorId === $teacher->id,
                'submitted_at' => now(),
            ]);

            $this->recalculate->handle($package);

            ActivityLogger::log('package.submitted', $package, [
                'validator_id' => $validatorId,
                'self_validated' => $validatorId === $teacher->id,
                'items' => $items->count(),
            ], $teacher);

            if ($validatorId !== $teacher->id) {
                Notify::to($validatorId, 'Paket baru untuk ditelaah', $teacher->name.' mengirim paket "'.$package->title.'".', '/validator/packages/'.$package->id.'/review', 'paket_baru');
            }

            return $package;
        });
    }
}
