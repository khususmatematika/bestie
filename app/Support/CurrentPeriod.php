<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PeriodStatus;
use App\Enums\SemesterStatus;
use App\Models\AssessmentPeriod;
use Illuminate\Http\Request;

/**
 * Konteks periode asesmen (semester) yang sedang dilihat pengguna.
 * Disimpan di sesi; pemilihnya ada di topbar (FR-ACD-06).
 */
final class CurrentPeriod
{
    public const SESSION_KEY = 'current_period_id';

    public static function resolve(Request $request): ?AssessmentPeriod
    {
        $id = $request->session()->get(self::SESSION_KEY);

        if (is_int($id) || (is_string($id) && ctype_digit($id))) {
            $period = AssessmentPeriod::query()->with('semester.year')->find((int) $id);

            if ($period !== null) {
                return $period;
            }
        }

        $period = self::defaultPeriod();

        if ($period !== null) {
            $request->session()->put(self::SESSION_KEY, $period->id);
        }

        return $period;
    }

    public static function set(Request $request, int $periodId): bool
    {
        if (! AssessmentPeriod::query()->whereKey($periodId)->exists()) {
            return false;
        }

        $request->session()->put(self::SESSION_KEY, $periodId);

        return true;
    }

    private static function defaultPeriod(): ?AssessmentPeriod
    {
        $inActiveSemester = AssessmentPeriod::query()
            ->with('semester.year')
            ->whereHas('semester', static fn ($q) => $q->where('status', SemesterStatus::Aktif->value))
            ->orderByRaw('case when status = ? then 0 else 1 end', [PeriodStatus::Buka->value])
            ->orderBy('id')
            ->first();

        return $inActiveSemester ?? AssessmentPeriod::query()->with('semester.year')->latest('id')->first();
    }
}
