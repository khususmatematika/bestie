<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Enums\ExtractionStatus;
use App\Enums\FileKind;
use App\Exceptions\WorkflowException;
use App\Imports\BlueprintRowsImport;
use App\Models\ItemRevision;
use App\Models\PackageFile;
use App\Models\QuestionItem;
use App\Models\QuestionPackage;
use App\Services\Documents\BlueprintRowsParser;
use App\Support\ItemContent;
use App\Services\Documents\DocumentRows;
use App\Services\Documents\DocxTextReader;
use App\Services\Documents\NaskahReader;
use App\Services\Documents\PdfTextReader;
use App\Services\Documents\ItemAssembler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Mengekstrak butir per nomor dari berkas kisi-kisi (XLSX/CSV) dan naskah (DOCX) terbaru pada paket draft
 * (architecture.md §7.1). Hasil menggantikan seluruh butir draft sebagai revisi v1.
 * Berjalan di antrian (rules.md §6.2.5).
 */
final class ExtractPackageItems
{
    public function __construct(
        private readonly BlueprintRowsParser $blueprintParser,
        private readonly ItemAssembler $assembler,
        private readonly DocxTextReader $docxReader,
        private readonly PdfTextReader $pdfReader,
        private readonly NaskahReader $naskahReader,
        private readonly StoreItemImages $images,
        private readonly RecalculatePackageProgress $recalculate,
    ) {
    }

    /**
     * @return array{items: int, warnings: list<string>}
     */
    public function handle(QuestionPackage $package): array
    {
        if (! $package->isDraft()) {
            throw new WorkflowException('Ekstraksi hanya dapat dilakukan pada paket yang masih draft.');
        }

        /** @var PackageFile|null $kisi */
        $kisi = $package->files()->where('kind', FileKind::KisiKisi->value)->reorder('id', 'desc')->first();
        /** @var PackageFile|null $naskah */
        $naskah = $package->files()->where('kind', FileKind::Naskah->value)->reorder('id', 'desc')->first();

        $files = array_filter([$kisi, $naskah]);

        if ($files === []) {
            throw new WorkflowException('Belum ada berkas kisi-kisi atau naskah untuk diekstrak.');
        }

        foreach ($files as $file) {
            $file->update(['extraction_status' => ExtractionStatus::Processing, 'extraction_report' => null]);
        }

        $warnings = [];
        $reports = [];
        $naskahItems = [];
        $blueprints = [];

        try {
            if ($kisi !== null) {
                [$blueprints, $reports['kisi']] = $this->readBlueprint($kisi);
                $warnings = [...$warnings, ...$reports['kisi']['warnings']];
            }

            if ($naskah !== null) {
                [$naskahItems, $reports['naskah']] = $this->readNaskah($naskah);
                $warnings = [...$warnings, ...$reports['naskah']['warnings']];
            }
        } catch (InvalidArgumentException $e) {
            $this->markFailed($files, $e->getMessage());

            throw new WorkflowException($e->getMessage());
        }

        $assembled = $this->assembler->assemble($naskahItems, $blueprints);
        $warnings = [...$warnings, ...$assembled['warnings']];

        if ($assembled['items'] === []) {
            $message = 'Tidak ada nomor soal yang ditemukan. Gunakan template standar (penanda "No. 1" pada naskah, kolom "No" pada kisi-kisi) atau isi butir secara manual.';
            $this->markFailed($files, $message, $warnings);

            throw new WorkflowException($message);
        }

        // Gambar pada naskah DOCX disimpan sebagai lampiran dan dihubungkan ke nomornya.
        $imageIds = [];
        $imageMap = [];

        if ($naskah !== null && $naskah->extension() === 'docx') {
            $byNumber = [];

            foreach ($assembled['items'] as $row) {
                if ($row['images'] !== []) {
                    $byNumber[$row['number']] = $row['images'];
                }
            }

            $stored = $this->images->handle($package, Storage::disk('local')->path($naskah->path), $byNumber);
            $imageIds = $stored['ids'];
            $imageMap = $stored['map'];

            if ($stored['skipped'] > 0) {
                $warnings[] = "{$stored['skipped']} gambar tidak dapat disimpan (format tidak didukung atau terlalu besar).";
            }
        }

        DB::transaction(function () use ($package, $assembled, $kisi, $naskah, $imageIds, $imageMap): void {
            // Ekstraksi ulang menggantikan seluruh butir draft (revisi lama ikut terhapus bersama butirnya).
            QuestionItem::query()->where('package_id', $package->id)->delete();

            foreach ($assembled['items'] as $row) {
                $content = ItemContent::resolveImageTokens($row['content'], $imageMap[$row['number']] ?? []);

                $item = QuestionItem::query()->create([
                    'package_id' => $package->id,
                    'number' => $row['number'],
                    'blueprint' => $content['blueprint'],
                    'type' => $content['type'],
                    'stem' => $content['stem'],
                    'options' => $content['options'],
                    'answer_key' => $content['answer_key'],
                    'scoring_guide' => $content['scoring_guide'],
                    'images' => $imageIds[$row['number']] ?? null,
                ]);

                $revision = ItemRevision::query()->create([
                    'item_id' => $item->id,
                    'version' => 1,
                    'snapshot' => $item->content(),
                    'source' => 'ekstraksi',
                    'uploaded_by' => $package->teacher_id,
                    'uploaded_at' => now(),
                    'file_id' => $naskah?->id ?? $kisi?->id,
                ]);

                $item->update(['current_revision_id' => $revision->id]);
            }

            $this->recalculate->handle($package);
        });

        $count = count($assembled['items']);

        foreach (['kisi' => $kisi, 'naskah' => $naskah] as $name => $file) {
            if ($file === null) {
                continue;
            }

            $report = $reports[$name] ?? ['warnings' => []];
            $status = ($report['skipped'] ?? false) ? ExtractionStatus::Skipped : ExtractionStatus::Done;

            $file->update([
                'extraction_status' => $status,
                'extraction_report' => $report + ['items' => $count, 'package_warnings' => $assembled['warnings']],
            ]);
        }

        return ['items' => $count, 'warnings' => $warnings];
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array{warnings: list<string>, skipped?: bool}}
     */
    private function readBlueprint(PackageFile $file): array
    {
        $extension = $file->extension();
        $absolute = Storage::disk('local')->path($file->path);
        $extra = [];

        try {
            if (in_array($extension, ['xlsx', 'csv'], true)) {
                /** @var list<array<string, mixed>> $rows */
                $rows = Excel::toArray(new BlueprintRowsImport, $file->path, 'local')[0] ?? [];
            } elseif ($extension === 'docx') {
                $rows = DocumentRows::fromTables($this->docxReader->read($absolute)['tables']);
            } elseif ($extension === 'pdf') {
                $rows = $this->pdfReader->tableRows($absolute);
                $extra[] = 'Kisi-kisi dibaca dari PDF; periksa kolom tiap nomor karena tata letak tabel PDF dapat memengaruhi hasil.';
            } else {
                return [[], ['warnings' => ['Kisi-kisi berformat .'.$extension.' tidak didukung. Gunakan XLSX/CSV, DOCX, atau PDF.'], 'skipped' => true]];
            }
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw new InvalidArgumentException('Kisi-kisi tidak dapat dibaca. Pastikan memakai template (kolom No, Indikator Soal, …) dan tidak rusak.');
        }

        if (count($rows) > 300) {
            throw new InvalidArgumentException('Kisi-kisi melebihi 300 baris.');
        }

        $parsed = $this->blueprintParser->parse($rows);

        return [$parsed['rows'], ['warnings' => [...$extra, ...$parsed['warnings']], 'rows' => count($parsed['rows'])]];
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array{warnings: list<string>, skipped?: bool}}
     */
    private function readNaskah(PackageFile $file): array
    {
        $parsed = $this->naskahReader->read(Storage::disk('local')->path($file->path), $file->extension(), $file->path);

        return [$parsed['items'], ['warnings' => $parsed['warnings'], 'numbers' => count($parsed['items'])]];
    }

    /**
     * @param  array<int, PackageFile>  $files
     * @param  list<string>  $warnings
     */
    private function markFailed(array $files, string $message, array $warnings = []): void
    {
        foreach ($files as $file) {
            $file->update([
                'extraction_status' => ExtractionStatus::Failed,
                'extraction_report' => ['error' => $message, 'warnings' => $warnings],
            ]);
        }
    }
}
