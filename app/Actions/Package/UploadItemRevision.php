<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Enums\FileKind;
use App\Enums\ItemStatus;
use App\Exceptions\WorkflowException;
use App\Models\ItemRevision;
use App\Models\PackageFile;
use App\Models\QuestionItem;
use App\Models\QuestionPackage;
use App\Models\User;
use App\Services\Documents\NaskahReader;
use App\Support\ActivityLogger;
use App\Support\Notify;
use App\Support\ItemContent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Guru mengunggah ulang satu nomor yang berstatus PERLU_GANTI (R-04). Setiap unggah ulang membuat versi
 * baru; versi lama tidak dihapus (R-05) dan waktunya dicatat (R-13). Dapat juga dari satu berkas naskah DOCX.
 */
final class UploadItemRevision
{
    public function __construct(
        private readonly RecalculatePackageProgress $recalculate,
        private readonly AttachPackageFile $attach,
        private readonly NaskahReader $naskahReader,
        private readonly StoreItemImages $images,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input  isi baru butir (type, stem, options, answer_key, scoring_guide, blueprint)
     */
    public function handle(QuestionItem $item, User $teacher, array $input, ?UploadedFile $attachment = null, ?PackageFile $file = null): QuestionItem
    {
        return DB::transaction(function () use ($item, $teacher, $input, $attachment, $file): QuestionItem {
            $package = QuestionPackage::query()->with('assessmentPeriod.semester')->lockForUpdate()->findOrFail($item->package_id);

            Gate::forUser($teacher)->authorize('manage', $package);
            PackageGuard::ensureWritable($package);

            $item = QuestionItem::query()->lockForUpdate()->findOrFail($item->id);

            if ($item->status !== ItemStatus::PerluGanti) {
                throw new WorkflowException("Nomor {$item->number} tidak sedang diminta diganti; unggah ulang hanya untuk nomor berstatus 'Perlu ganti'.");
            }

            $new = ItemContent::normalize($input);

            if ($attachment === null && $file === null && $new == ItemContent::normalize($item->content())) {
                throw new WorkflowException("Belum ada perubahan pada nomor {$item->number}. Perbaiki soal sesuai catatan validator sebelum mengunggah ulang.");
            }

            $item->fill([
                'blueprint' => $new['blueprint'],
                'type' => $new['type'],
                'stem' => $new['stem'],
                'options' => $new['options'],
                'answer_key' => $new['answer_key'],
                'scoring_guide' => $new['scoring_guide'],
                // Gambar lama yang tidak lagi dirujuk teks dilepas dari butir (berkas tetap untuk riwayat versi).
                'images' => array_values(array_intersect((array) ($item->images ?? []), ItemContent::referencedImageIds($new))) ?: null,
            ]);

            $missing = $item->missingFields();

            if ($missing !== []) {
                throw new WorkflowException("Nomor {$item->number} belum lengkap: ".implode(', ', $missing).'.');
            }

            if ($attachment !== null) {
                $file = $this->attach->handle($package, $attachment, FileKind::Lampiran, $teacher);
            }

            $now = now();
            $version = (int) ItemRevision::query()->where('item_id', $item->id)->max('version') + 1;

            $revision = ItemRevision::query()->create([
                'item_id' => $item->id,
                'version' => $version,
                'snapshot' => $item->content(),
                'source' => 'unggah_ulang',
                'uploaded_by' => $teacher->id,
                'uploaded_at' => $now,
                'file_id' => $file?->id,
            ]);

            $item->fill([
                'status' => ItemStatus::Direvisi,
                'current_revision_id' => $revision->id,
                'resubmitted_at' => $now,
            ])->save();

            $this->recalculate->handle($package);

            ActivityLogger::log('item.revised', $package, ['number' => $item->number, 'version' => $version], $teacher);

            if ($package->validator_id !== null && $package->validator_id !== $teacher->id) {
                Notify::to($package->validator_id, 'Nomor '.$item->number.' diunggah ulang', $teacher->name.' mengunggah ulang nomor pada paket "'.$package->title.'" untuk dicek ulang.', '/validator/packages/'.$package->id.'/review/'.$item->number, 'unggah_ulang');
            }

            return $item;
        });
    }

    /**
     * Unggah ulang dari satu naskah DOCX: hanya nomor berstatus PERLU_GANTI yang diproses.
     *
     * @return array{revised: list<int>, unchanged: list<int>, ignored: list<int>}
     */
    public function fromDocument(QuestionPackage $package, User $teacher, UploadedFile $document): array
    {
        Gate::forUser($teacher)->authorize('manage', $package);

        if (! in_array(mb_strtolower($document->getClientOriginalExtension()), ['docx', 'pdf'], true)) {
            throw new WorkflowException('Unggah ulang seluruh naskah mendukung berkas .docx atau .pdf.');
        }

        try {
            $parsed = $this->naskahReader->read((string) $document->getRealPath(), $document->getClientOriginalExtension())['items'];
        } catch (InvalidArgumentException $e) {
            throw new WorkflowException($e->getMessage());
        }

        if ($parsed === []) {
            throw new WorkflowException('Tidak ada nomor soal yang ditemukan pada naskah. Gunakan template naskah standar.');
        }

        $package->loadMissing('assessmentPeriod.semester');
        PackageGuard::ensureWritable($package);

        $items = QuestionItem::query()->where('package_id', $package->id)->get()->keyBy('number');

        $targets = $items->filter(static fn (QuestionItem $i): bool => $i->status === ItemStatus::PerluGanti);

        if ($targets->isEmpty()) {
            throw new WorkflowException('Tidak ada nomor yang sedang diminta diganti pada paket ini.');
        }

        // Semua nomor diproses sekaligus: bila satu nomor gagal, tidak ada perubahan yang tersimpan.
        return DB::transaction(function () use ($package, $teacher, $document, $parsed, $targets): array {
            $file = $this->attach->handle($package, $document, FileKind::Lampiran, $teacher);

            $result = ['revised' => [], 'unchanged' => [], 'ignored' => []];

            foreach ($parsed as $number => $row) {
                $item = $targets->get($number);

                if ($item === null) {
                    $result['ignored'][] = (int) $number;

                    continue;
                }

                $current = $item->content();
                $input = [
                    'type' => $row['type'] ?? $current['type'],
                    'stem' => $row['stem'] !== '' ? $row['stem'] : $current['stem'],
                    'options' => $row['options'] !== [] ? $row['options'] : $current['options'],
                    'answer_key' => $row['answer_key'] !== '' ? $row['answer_key'] : $current['answer_key'],
                    'scoring_guide' => $row['scoring_guide'] !== '' ? $row['scoring_guide'] : $current['scoring_guide'],
                    'blueprint' => array_merge($current['blueprint'], isset($row['bobot']) && $row['bobot'] > 0 ? ['bobot' => $row['bobot']] : []),
                ];

                if (ItemContent::withoutImageIds(ItemContent::normalize($input)) == ItemContent::withoutImageIds(ItemContent::normalize($current))) {
                    $result['unchanged'][] = (int) $number;

                    continue;
                }

                // Gambar pada naskah baru disimpan dan penandanya diarahkan ke lampiran.
                $paths = ItemContent::pendingImagePaths($input);

                if ($paths !== [] && mb_strtolower($document->getClientOriginalExtension()) === 'docx') {
                    $stored = $this->images->forItem($package, (string) $document->getRealPath(), (int) $number, $paths);
                    $input = ItemContent::resolveImageTokens($input, $stored['map']);
                    $item->update(['images' => [...(array) ($item->images ?? []), ...array_values($stored['map'])]]);
                } else {
                    $input = ItemContent::resolveImageTokens($input, []);
                }

                $this->handle($item, $teacher, $input, null, $file);
                $result['revised'][] = (int) $number;
            }

            if ($result['revised'] === []) {
                throw new WorkflowException('Naskah tidak berisi perubahan pada nomor yang diminta diganti.');
            }

            return $result;
        });
    }
}
