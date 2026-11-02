<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Enums\FileKind;
use App\Exceptions\WorkflowException;
use App\Jobs\ExtractPackageItemsJob;
use App\Models\QuestionPackage;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

/**
 * Guru mengunggah/mengganti kisi-kisi dan/atau naskah pada paket draft lalu memicu ekstraksi ulang.
 * Ekstraksi ulang menggantikan seluruh butir draft.
 */
final class ReplacePackageFiles
{
    public function __construct(private readonly AttachPackageFile $attach)
    {
    }

    public function handle(QuestionPackage $package, User $teacher, ?UploadedFile $kisiKisi, ?UploadedFile $naskah): void
    {
        Gate::forUser($teacher)->authorize('manage', $package);

        if ($kisiKisi === null && $naskah === null) {
            throw new WorkflowException('Pilih berkas kisi-kisi atau naskah terlebih dahulu.');
        }

        if ($kisiKisi !== null) {
            $this->attach->handle($package, $kisiKisi, FileKind::KisiKisi, $teacher);
        }

        if ($naskah !== null) {
            $this->attach->handle($package, $naskah, FileKind::Naskah, $teacher);
        }

        \App\Support\RunJob::dispatch(new ExtractPackageItemsJob($package->id));

        ActivityLogger::log('package.files_replaced', $package, [], $teacher);
    }
}
