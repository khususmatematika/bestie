<?php

declare(strict_types=1);

namespace App\Actions\Archive;

use App\Models\ArchiveImport;
use App\Models\User;
use App\Services\Documents\NaskahReader;
use App\Support\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mengimpor naskah soal tahun lalu (.docx/.pdf) ke arsip: dibaca per nomor, disimpan sebagai teks saja
 * (gambar tidak diarsipkan) untuk pembanding kemiripan.
 */
final class ImportArchive
{
    public function __construct(private readonly NaskahReader $reader)
    {
    }

    /**
     * @return array{import: ArchiveImport, warnings: list<string>}
     */
    public function handle(UploadedFile $file, int $subjectId, ?int $gradeLevelId, string $yearLabel, User $admin): array
    {
        $extension = mb_strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['docx', 'pdf'], true)) {
            throw new InvalidArgumentException('Arsip hanya menerima naskah .docx atau .pdf.');
        }

        $read = $this->reader->read((string) $file->getRealPath(), $extension);
        $rows = [];

        foreach ($read['items'] as $number => $row) {
            $stem = $this->clean((string) ($row['stem'] ?? ''));

            if ($stem === '') {
                continue;
            }

            $rows[] = [
                'number' => (int) $number,
                'type' => isset($row['type']) ? (is_object($row['type']) ? $row['type']->value : (string) $row['type']) : null,
                'stem' => $stem,
                'options' => array_map(fn ($o): string => $this->clean((string) $o), array_filter((array) ($row['options'] ?? []), 'is_string')),
                'answer_key' => mb_substr((string) ($row['answer_key'] ?? ''), 0, 500),
            ];
        }

        if ($rows === []) {
            throw new InvalidArgumentException('Tidak ada nomor soal yang dapat dibaca dari naskah. Gunakan naskah dengan format yang sama seperti naskah ujian.');
        }

        $import = DB::transaction(function () use ($rows, $file, $subjectId, $gradeLevelId, $yearLabel, $admin): ArchiveImport {
            $import = ArchiveImport::query()->create([
                'subject_id' => $subjectId,
                'grade_level_id' => $gradeLevelId,
                'year_label' => $yearLabel,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'items_count' => count($rows),
                'imported_by' => $admin->id,
            ]);

            foreach (array_chunk($rows, 100) as $chunk) {
                $import->items()->createMany($chunk);
            }

            ActivityLogger::log('archive.imported', $import, ['items' => count($rows), 'year' => $yearLabel], $admin);

            return $import;
        });

        return ['import' => $import, 'warnings' => $read['warnings']];
    }

    private function clean(string $text): string
    {
        $text = preg_replace('/⟦rb:([^|⟧]*)\|[^⟧]*⟧/u', '$1', $text) ?? $text;

        return trim(preg_replace('/⟦[^⟧]*⟧/u', '', $text) ?? $text);
    }
}
