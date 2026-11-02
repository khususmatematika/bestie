<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Enums\ExtractionStatus;
use App\Enums\FileKind;
use App\Models\PackageFile;
use App\Models\QuestionPackage;
use App\Services\Documents\DocxStructureReader;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Menyimpan gambar yang tertanam pada naskah DOCX sebagai berkas lampiran paket (disk privat, nama UUID) dan
 * menghubungkannya ke nomor soalnya. Hanya PNG/JPEG/GIF asli (diperiksa dari isi berkas) yang disimpan.
 */
final class StoreItemImages
{
    private const MAX_PER_ITEM = 8;

    private const MAX_TOTAL = 60;

    public function __construct(private readonly DocxStructureReader $reader)
    {
    }

    /**
     * @param  array<int, list<string>>  $byNumber  nomor soal => path media di dalam DOCX
     * @return array{ids: array<int, list<int>>, map: array<int, array<string, int>>, skipped: int}
     */
    public function handle(QuestionPackage $package, string $docxPath, array $byNumber): array
    {
        $this->removePrevious($package);

        $ids = [];
        $map = [];
        $skipped = 0;
        $total = 0;

        foreach ($byNumber as $number => $paths) {
            $stored = $this->forItem($package, $docxPath, (int) $number, $paths, $total);
            $ids[(int) $number] = array_values($stored['map']);
            $map[(int) $number] = $stored['map'];
            $skipped += $stored['skipped'];
            $total += count($stored['map']);
        }

        return ['ids' => $ids, 'map' => $map, 'skipped' => $skipped];
    }

    /**
     * Menyimpan gambar milik satu nomor (tanpa menghapus gambar lain).
     *
     * @param  list<string>  $paths  path media di dalam DOCX
     * @return array{map: array<string, int>, skipped: int}
     */
    public function forItem(QuestionPackage $package, string $docxPath, int $number, array $paths, int $alreadyStored = 0): array
    {
        $map = [];
        $skipped = 0;

        foreach (array_slice($paths, 0, self::MAX_PER_ITEM) as $index => $zipPath) {
            if ($alreadyStored + count($map) >= self::MAX_TOTAL) {
                $skipped++;

                continue;
            }

            $bytes = $this->reader->media($docxPath, $zipPath);
            $extension = $bytes === null ? null : $this->extension($bytes);

            if ($bytes === null || $extension === null) {
                $skipped++;

                continue;
            }

            $path = 'packages/'.$package->id.'/'.Str::uuid()->toString().'.'.$extension;
            Storage::disk('local')->put($path, $bytes);

            $file = PackageFile::query()->create([
                'package_id' => $package->id,
                'kind' => FileKind::Lampiran,
                'original_name' => 'gambar-no-'.$number.'-'.($index + 1).'.'.$extension,
                'path' => $path,
                'mime' => $extension === 'jpg' ? 'image/jpeg' : 'image/'.$extension,
                'size' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
                'extraction_status' => ExtractionStatus::Skipped,
                'extraction_report' => ['source' => 'extracted_image'],
                'uploaded_by' => $package->teacher_id,
            ]);

            $map[$zipPath] = $file->id;
        }

        return ['map' => $map, 'skipped' => $skipped];
    }

    private function removePrevious(QuestionPackage $package): void
    {
        $old = PackageFile::query()
            ->where('package_id', $package->id)
            ->where('kind', FileKind::Lampiran->value)
            ->where('original_name', 'like', 'gambar-no-%')
            ->get();

        foreach ($old as $file) {
            Storage::disk('local')->delete($file->path);
            $file->delete();
        }
    }

    private function extension(string $bytes): ?string
    {
        return match (true) {
            str_starts_with($bytes, "\x89PNG") => 'png',
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'jpg',
            str_starts_with($bytes, 'GIF8') => 'gif',
            default => null,
        };
    }
}
