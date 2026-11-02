<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Exceptions\WorkflowException;
use App\Models\QuestionPackage;
use App\Models\User;
use App\Services\ValidatorEligibility;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Pergantian validator hanya oleh Admin dan tercatat (R-09).
 */
final class ChangePackageValidator
{
    public function __construct(private readonly ValidatorEligibility $eligibility)
    {
    }

    public function handle(QuestionPackage $package, User $admin, int $validatorId): QuestionPackage
    {
        return DB::transaction(function () use ($package, $admin, $validatorId): QuestionPackage {
            $package = QuestionPackage::query()->with('assessmentPeriod.semester')->lockForUpdate()->findOrFail($package->id);

            Gate::forUser($admin)->authorize('administer', $package);
            PackageGuard::ensureWritable($package);

            if ($package->isDraft() || $package->validator_id === null) {
                throw new WorkflowException('Paket belum dikirim; guru memilih validatornya saat mengirim.');
            }

            if ($package->validator_id === $validatorId) {
                throw new WorkflowException('Validator yang dipilih sama dengan validator saat ini.');
            }

            if (! $this->eligibility->allows($validatorId, $package->assessmentPeriod->semester_id, $package->subject_id, $package->teacher_id)) {
                throw new WorkflowException('Validator yang dipilih tidak valid untuk mapel ini.');
            }

            $from = $package->validator_id;

            $package->update([
                'validator_id' => $validatorId,
                'is_self_validated' => $validatorId === $package->teacher_id,
            ]);

            ActivityLogger::log('package.validator_changed', $package, ['from' => $from, 'to' => $validatorId], $admin);

            return $package;
        });
    }
}
