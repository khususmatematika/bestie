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
 * Admin menghapus satu paket (soft delete) dari monitoring paket.
 * Paket yang sudah Fix atau Terkunci tidak boleh dihapus.
 */
final class DeletePackageAsAdmin
{
    public function handle(QuestionPackage $package, User $admin): void
    {
        DB::transaction(function () use ($package, $admin): void {
            $package = QuestionPackage::query()->lockForUpdate()->findOrFail($package->id);

            Gate::forUser($admin)->authorize('administer', $package);

            if (in_array($package->status, [PackageStatus::Fix, PackageStatus::Terkunci], true)) {
                throw new WorkflowException('Paket yang sudah Fix atau terkunci tidak dapat dihapus.');
            }

            ActivityLogger::log('package.deleted_by_admin', $package, ['title' => $package->title, 'status' => $package->status->value], $admin);

            $package->delete();
        });
    }
}
