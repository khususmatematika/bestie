<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Exceptions\WorkflowException;
use App\Models\QuestionPackage;

/**
 * Pemeriksaan umum "paket masih boleh diubah" (R-08 terkunci, R-12 periode ditutup / semester arsip).
 */
final class PackageGuard
{
    public static function ensureWritable(QuestionPackage $package): void
    {
        $package->loadMissing('assessmentPeriod.semester');

        if ($package->isLocked()) {
            throw new WorkflowException('Paket sudah terkunci dan tidak dapat diubah.');
        }

        if (! $package->assessmentPeriod->isOpen()) {
            throw new WorkflowException('Periode asesmen sudah ditutup; paket bersifat hanya-baca.');
        }

        if ($package->assessmentPeriod->semester->isArchived()) {
            throw new WorkflowException('Semester ini sudah diarsipkan; paket bersifat hanya-baca.');
        }
    }
}
