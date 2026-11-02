<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Enums\PeriodStatus;
use App\Models\AssessmentPeriod;
use App\Support\ActivityLogger;

final class SetPeriodStatus
{
    /**
     * Menutup / membuka kembali periode. Periode ditutup bersifat hanya-baca (R-12).
     */
    public function handle(AssessmentPeriod $period, PeriodStatus $status): AssessmentPeriod
    {
        $period->update(['status' => $status]);

        ActivityLogger::log("period.{$status->value}", $period);

        return $period->refresh();
    }
}
