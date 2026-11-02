<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Enums\FileKind;
use App\Enums\PackageStatus;
use App\Exceptions\WorkflowException;
use App\Jobs\ExtractPackageItemsJob;
use App\Models\AssessmentPeriod;
use App\Models\QuestionPackage;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Guru membuat paket soal per mapel + tingkat + periode (FR-PKG-01) dan, bila ada berkas,
 * memicu ekstraksi di antrian (langkah 3 prosedur).
 */
final class CreatePackage
{
    public function __construct(private readonly AttachPackageFile $attach)
    {
    }

    public function handle(
        User $teacher,
        AssessmentPeriod $period,
        TeachingAssignment $assignment,
        ?string $title = null,
        ?UploadedFile $kisiKisi = null,
        ?UploadedFile $naskah = null,
    ): QuestionPackage {
        $period->loadMissing('semester');

        if (! $period->isOpen()) {
            throw new WorkflowException('Periode asesmen ini sudah ditutup; paket baru tidak dapat dibuat.');
        }

        if ($period->semester->isArchived()) {
            throw new WorkflowException('Semester ini sudah diarsipkan.');
        }

        if ($assignment->teacher_id !== $teacher->id || $assignment->semester_id !== $period->semester_id) {
            throw new WorkflowException('Penugasan mengajar tidak sesuai dengan periode yang dipilih.');
        }

        $duplicate = QuestionPackage::query()
            ->where('assessment_period_id', $period->id)
            ->where('teacher_id', $teacher->id)
            ->where('subject_id', $assignment->subject_id)
            ->where('grade_level_id', $assignment->grade_level_id)
            ->exists();

        if ($duplicate) {
            throw new WorkflowException('Paket untuk mapel dan tingkat ini sudah ada pada periode ini. Buka paket tersebut untuk melanjutkan.');
        }

        $assignment->loadMissing(['subject:id,name', 'gradeLevel:id,name']);

        $package = DB::transaction(function () use ($teacher, $period, $assignment, $title, $kisiKisi, $naskah): QuestionPackage {
            $package = QuestionPackage::query()->create([
                'assessment_period_id' => $period->id,
                'teacher_id' => $teacher->id,
                'subject_id' => $assignment->subject_id,
                'grade_level_id' => $assignment->grade_level_id,
                'title' => trim((string) $title) !== ''
                    ? trim((string) $title)
                    : "{$assignment->subject->name} Kelas {$assignment->gradeLevel->name} — {$period->name}",
                'status' => PackageStatus::Draft,
            ]);

            $package->setRelation('assessmentPeriod', $period);

            if ($kisiKisi !== null) {
                $this->attach->handle($package, $kisiKisi, FileKind::KisiKisi, $teacher);
            }

            if ($naskah !== null) {
                $this->attach->handle($package, $naskah, FileKind::Naskah, $teacher);
            }

            return $package;
        });

        if ($kisiKisi !== null || $naskah !== null) {
            \App\Support\RunJob::dispatch(new ExtractPackageItemsJob($package->id));
        }

        ActivityLogger::log('package.created', $package, ['title' => $package->title], $teacher);

        return $package;
    }
}
