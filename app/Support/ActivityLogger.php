<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Pencatat jejak audit (FR-AUD-01). Tidak pernah menyimpan kata sandi.
 */
final class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(string $event, ?Model $subject = null, array $properties = [], ?User $causer = null): ActivityLog
    {
        $causer ??= Auth::user();

        if (! array_key_exists('impersonated_by', $properties) && ($by = Impersonation::impersonatorId()) !== null) {
            $properties['impersonated_by'] = $by;
        }

        return ActivityLog::query()->create([
            'causer_id' => $causer?->getKey(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'event' => $event,
            'properties' => self::sanitize($properties),
            'ip' => request()->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private static function sanitize(array $properties): array
    {
        $clean = [];

        foreach ($properties as $key => $value) {
            if (str_contains(strtolower((string) $key), 'password')) {
                continue;
            }

            $clean[$key] = is_array($value) ? self::sanitize($value) : $value;
        }

        return $clean;
    }
}
