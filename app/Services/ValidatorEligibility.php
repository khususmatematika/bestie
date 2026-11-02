<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleName;
use App\Models\User;
use App\Models\ValidatorScope;
use App\Support\Settings;
use Illuminate\Support\Collection;

/**
 * Daftar validator yang boleh dipilih untuk sebuah paket: akun validator aktif, sesuai cakupan mapel
 * semester tersebut (tanpa cakupan = semua mapel), dan tunduk pada aturan self-validation (FR-VAL-02, FR-VAL-03).
 */
final class ValidatorEligibility
{
    /**
     * @return Collection<int, User>
     */
    public function for(int $semesterId, int $subjectId, int $teacherId): Collection
    {
        $validators = User::query()
            ->role(RoleName::Validator->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'nip']);

        $scopes = ValidatorScope::query()
            ->where('semester_id', $semesterId)
            ->get(['validator_id', 'subject_id'])
            ->groupBy('validator_id');

        return $validators
            ->filter(function (User $validator) use ($scopes, $subjectId, $teacherId): bool {
                if ($validator->id === $teacherId && ! self::selfValidationAllowed()) {
                    return false;
                }

                $rows = $scopes->get($validator->id);

                if ($rows === null || $rows->isEmpty()) {
                    return true;
                }

                return $rows->contains(static fn (ValidatorScope $s): bool => $s->subject_id === null || $s->subject_id === $subjectId);
            })
            ->values();
    }

    public function allows(int $validatorId, int $semesterId, int $subjectId, int $teacherId): bool
    {
        return $this->for($semesterId, $subjectId, $teacherId)->contains('id', $validatorId);
    }

    /**
     * Validator memvalidasi paket sendiri hanya bila diizinkan dan validator silang tidak diwajibkan.
     */
    public static function selfValidationAllowed(): bool
    {
        return (bool) Settings::get('allow_self_validation', true)
            && ! (bool) Settings::get('require_cross_validator_for_self', false);
    }
}
