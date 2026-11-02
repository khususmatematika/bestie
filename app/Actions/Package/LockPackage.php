<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Enums\PackageStatus;
use App\Exceptions\WorkflowException;
use App\Models\QuestionPackage;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Admin mengunci paket yang sudah FIX; setelah itu paket immutable (R-08).
 */
final class LockPackage
{
    public function handle(QuestionPackage $package, User $admin): QuestionPackage
    {
        return DB::transaction(function () use ($package, $admin): QuestionPackage {
            $package = QuestionPackage::query()->lockForUpdate()->findOrFail($package->id);

            Gate::forUser($admin)->authorize('administer', $package);

            if ($package->status !== PackageStatus::Fix) {
                throw new WorkflowException('Hanya paket berstatus Fix (100% disetujui) yang dapat dikunci.');
            }

            $package->update([
                'status' => PackageStatus::Terkunci,
                'locked_at' => now(),
                'locked_by' => $admin->id,
            ]);

            ActivityLogger::log('package.locked', $package, [], $admin);

            return $package;
        });
    }
}
