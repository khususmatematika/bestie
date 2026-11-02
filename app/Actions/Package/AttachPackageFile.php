<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Enums\ExtractionStatus;
use App\Enums\FileKind;
use App\Exceptions\WorkflowException;
use App\Models\PackageFile;
use App\Models\QuestionPackage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Menyimpan berkas paket di disk privat dengan nama acak (rules.md §6.2).
 * Kisi-kisi/naskah baru menggantikan yang lama dan hanya boleh saat paket draft.
 */
final class AttachPackageFile
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'kisi_kisi' => ['xlsx', 'docx', 'pdf'],
        'naskah' => ['docx', 'pdf'],
        'lampiran' => ['docx', 'xlsx', 'pdf', 'png', 'jpg', 'jpeg'],
    ];

    public function handle(QuestionPackage $package, UploadedFile $file, FileKind $kind, User $uploader): PackageFile
    {
        PackageGuard::ensureWritable($package);

        if ($kind !== FileKind::Lampiran && ! $package->isDraft()) {
            throw new WorkflowException('Kisi-kisi dan naskah hanya dapat diganti saat paket masih draft. Untuk nomor yang diminta diganti, gunakan unggah ulang per nomor.');
        }

        $extension = mb_strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED[$kind->value], true)) {
            throw new WorkflowException('Format berkas '.$kind->label().' tidak didukung (.'.$extension.').');
        }

        $path = Storage::disk('local')->putFileAs(
            'packages/'.$package->id,
            $file,
            Str::uuid()->toString().'.'.$extension,
        );

        if ($path === false) {
            throw new WorkflowException('Berkas gagal disimpan. Coba lagi.');
        }

        $previous = $kind === FileKind::Lampiran
            ? collect()
            : $package->files()->where('kind', $kind->value)->get();

        $record = PackageFile::query()->create([
            'package_id' => $package->id,
            'kind' => $kind,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 200, ''),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => (int) $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
            'extraction_status' => in_array($kind, [FileKind::KisiKisi, FileKind::Naskah], true)
                ? ExtractionStatus::Pending
                : ExtractionStatus::Skipped,
            'uploaded_by' => $uploader->id,
        ]);

        foreach ($previous as $old) {
            Storage::disk('local')->delete($old->path);
            $old->delete();
        }

        return $record;
    }
}
