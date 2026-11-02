<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Enums\ExtractionStatus;
use App\Enums\FileKind;
use App\Enums\ItemStatus;
use App\Exceptions\WorkflowException;
use App\Models\PackageFile;
use App\Models\QuestionItem;
use App\Models\QuestionPackage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Gambar yang ditempel (paste) langsung ke kolom soal: disimpan sebagai lampiran paket dan dikaitkan ke nomornya.
 * Hanya PNG/JPEG/GIF asli (diperiksa dari isi berkas), maksimal 3 MB, 30 gambar per nomor.
 */
final class StorePastedImage
{
    private const MAX_BYTES = 3_145_728;

    private const MAX_PER_ITEM = 30;

    public function handle(QuestionItem $item, User $teacher, UploadedFile $image): PackageFile
    {
        return DB::transaction(function () use ($item, $teacher, $image): PackageFile {
            $package = QuestionPackage::query()->with('assessmentPeriod.semester')->lockForUpdate()->findOrFail($item->package_id);

            Gate::forUser($teacher)->authorize('manage', $package);
            PackageGuard::ensureWritable($package);

            $item = QuestionItem::query()->lockForUpdate()->findOrFail($item->id);

            if (! $package->isDraft() && $item->status !== ItemStatus::PerluGanti) {
                throw new WorkflowException('Gambar hanya dapat ditambahkan saat paket draft atau pada nomor yang diminta diganti.');
            }

            $bytes = (string) file_get_contents((string) $image->getRealPath());

            if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) {
                throw new WorkflowException('Ukuran gambar maksimal 3 MB.');
            }

            $extension = match (true) {
                str_starts_with($bytes, "\x89PNG") => 'png',
                str_starts_with($bytes, "\xFF\xD8\xFF") => 'jpg',
                str_starts_with($bytes, 'GIF8') => 'gif',
                default => null,
            };

            if ($extension === null || @getimagesizefromstring($bytes) === false) {
                throw new WorkflowException('Hanya gambar PNG, JPEG, atau GIF yang dapat ditempel.');
            }

            $ids = (array) ($item->images ?? []);

            if (count($ids) >= self::MAX_PER_ITEM) {
                throw new WorkflowException('Batas gambar per nomor tercapai ('.self::MAX_PER_ITEM.').');
            }

            $path = 'packages/'.$package->id.'/'.Str::uuid()->toString().'.'.$extension;
            Storage::disk('local')->put($path, $bytes);

            $file = PackageFile::query()->create([
                'package_id' => $package->id,
                'kind' => FileKind::Lampiran,
                'original_name' => 'tempel-no-'.$item->number.'-'.(count($ids) + 1).'.'.$extension,
                'path' => $path,
                'mime' => $extension === 'jpg' ? 'image/jpeg' : 'image/'.$extension,
                'size' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
                'extraction_status' => ExtractionStatus::Skipped,
                'extraction_report' => ['source' => 'pasted_image'],
                'uploaded_by' => $teacher->id,
            ]);

            $item->update(['images' => [...$ids, $file->id]]);

            return $file;
        });
    }
}
