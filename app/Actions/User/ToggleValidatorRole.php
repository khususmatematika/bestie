<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\ActivityLogger;

final class ToggleValidatorRole
{
    /**
     * Menambah / mencabut peran validator (langkah 2 prosedur, FR-VAL-01).
     *
     * @return bool true bila akun kini menjadi validator
     */
    public function handle(User $user): bool
    {
        $role = RoleName::Validator->value;

        if ($user->hasRole($role)) {
            $user->removeRole($role);
            ActivityLogger::log('validator.revoked', $user);

            return false;
        }

        $user->assignRole($role);
        ActivityLogger::log('validator.assigned', $user);

        return true;
    }
}
