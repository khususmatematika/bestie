<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PackageStatus;
use App\Enums\RoleName;
use App\Models\QuestionPackage;
use App\Models\User;

/**
 * Otorisasi kepemilikan paket (rules.md §6.1, anti-IDOR).
 * Aturan status/periode dijaga oleh Action, bukan di sini.
 */
class QuestionPackagePolicy
{
    /** Admin, guru pemilik, atau validator yang ditunjuk pada paket. */
    public function view(User $user, QuestionPackage $package): bool
    {
        if ($user->hasRole(RoleName::Admin->value)) {
            return true;
        }

        if ($package->teacher_id === $user->id) {
            return true;
        }

        return $package->validator_id === $user->id
            && $user->hasRole(RoleName::Validator->value)
            && $package->status !== PackageStatus::Draft;
    }

    /** Guru pemilik mengubah isi paket (draft, revisi nomor, kirim). */
    public function manage(User $user, QuestionPackage $package): bool
    {
        return $package->teacher_id === $user->id;
    }

    /** Validator yang ditunjuk menelaah paket (R-10). */
    public function review(User $user, QuestionPackage $package): bool
    {
        return $package->validator_id === $user->id
            && $user->hasRole(RoleName::Validator->value)
            && $package->status !== PackageStatus::Draft;
    }

    /** Hanya admin yang mengunci / mengganti validator (R-09). */
    public function administer(User $user, QuestionPackage $package): bool
    {
        return $user->hasRole(RoleName::Admin->value);
    }
}
