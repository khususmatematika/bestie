<?php

declare(strict_types=1);

namespace App\Actions\Master;

use App\Enums\SubjectCategory;
use App\Imports\SubjectRowsImport;
use App\Models\GradeLevel;
use App\Models\Subject;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Impor mapel beserta kategori dan tingkat kelasnya.
 *
 * - Kunci unik = kode mapel (tanpa membedakan huruf besar/kecil).
 * - Setiap baris diproses dalam transaksi sendiri; baris salah tidak menggagalkan baris benar.
 * - Diproses langsung (tanpa antrian) karena daftar mapel kecil.
 */
final class ImportSubjects
{
    /**
     * @return array{total: int, created: int, updated: int, skipped: int, failed: int, rows: list<array{row: int, code: string, status: string, message: string}>}
     */
    public function handle(UploadedFile|string $file, bool $updateExisting, ?User $actor = null): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = Excel::toArray(new SubjectRowsImport, $file)[0] ?? [];

        $filled = array_filter($rows, fn (array $row): bool => $this->hasContent($row));
        $maxRows = (int) config('banksoal.import.max_subject_rows', 500);

        if (count($filled) > $maxRows) {
            throw new InvalidArgumentException("Berkas melebihi batas {$maxRows} baris.");
        }

        /** @var Collection<string, GradeLevel> $grades */
        $grades = GradeLevel::query()->get()->keyBy(static fn (GradeLevel $g): string => mb_strtoupper($g->name));

        $report = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'rows' => []];

        foreach ($rows as $index => $row) {
            if (! $this->hasContent($row)) {
                continue;
            }

            try {
                $outcome = DB::transaction(fn (): array => $this->processRow($row, $updateExisting, $grades));
            } catch (Throwable $e) {
                report($e);
                $outcome = ['status' => 'failed', 'message' => 'Terjadi kesalahan saat menyimpan baris ini.'];
            }

            $report['total']++;
            $report[$outcome['status']]++;
            $report['rows'][] = [
                'row' => $index + 2, // baris 1 = judul kolom
                'code' => mb_strtoupper(trim($this->text($row['kode_mapel'] ?? $row['kode'] ?? null))),
                'status' => $outcome['status'],
                'message' => $outcome['message'],
            ];
        }

        ActivityLogger::log('import.subjects.finished', null, [
            'created' => $report['created'],
            'updated' => $report['updated'],
            'skipped' => $report['skipped'],
            'failed' => $report['failed'],
        ], $actor);

        return $report;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  Collection<string, GradeLevel>  $grades
     * @return array{status: string, message: string}
     */
    private function processRow(array $row, bool $updateExisting, Collection $grades): array
    {
        $data = [
            'code' => mb_strtoupper(trim($this->text($row['kode_mapel'] ?? $row['kode'] ?? null))),
            'name' => trim($this->text($row['nama_mapel'] ?? $row['nama'] ?? $row['mapel'] ?? null)),
            'category' => trim($this->text($row['kategori'] ?? null)),
            'grades' => $this->list($row['tingkat'] ?? $row['kelas'] ?? null),
        ];

        $validator = Validator::make($data, [
            'code' => ['required', 'alpha_dash', 'max:20'],
            'name' => ['required', 'string', 'max:120'],
        ], [], ['code' => 'kode mapel', 'name' => 'nama mapel']);

        if ($validator->fails()) {
            return $this->failed(implode(' ', $validator->errors()->all()));
        }

        // Kategori kosong dianggap mapel umum; isian lain yang tidak dikenali ditolak.
        $category = $data['category'] === '' ? SubjectCategory::Umum : SubjectCategory::fromInput($data['category']);

        if ($category === null) {
            return $this->failed("Kategori '{$data['category']}' tidak valid (gunakan umum atau pilihan).");
        }

        if ($data['grades'] === []) {
            return $this->failed('Kolom tingkat wajib diisi (X, XI, dan/atau XII).');
        }

        $gradeIds = [];

        foreach ($data['grades'] as $name) {
            $grade = $grades->get($name);

            if ($grade === null) {
                return $this->failed("Tingkat '{$name}' tidak valid. Tingkat yang tersedia: ".$grades->pluck('name')->implode(', ').'.');
            }

            $gradeIds[] = $grade->id;
        }

        $subject = Subject::query()->where('code', $data['code'])->first();

        if ($subject !== null) {
            if (! $updateExisting) {
                return ['status' => 'skipped', 'message' => 'Kode mapel sudah ada, dilewati.'];
            }

            $subject->update(['name' => $data['name'], 'category' => $category]);
            $subject->gradeLevels()->sync($gradeIds);

            return ['status' => 'updated', 'message' => 'Mapel diperbarui. '.$this->summary($category, $gradeIds, $grades)];
        }

        $subject = Subject::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'category' => $category,
            'is_active' => true,
        ]);
        $subject->gradeLevels()->sync($gradeIds);

        return ['status' => 'created', 'message' => 'Mapel dibuat. '.$this->summary($category, $gradeIds, $grades)];
    }

    /**
     * Ringkasan "Kelas X (Fase E), Kelas XI (Fase F+)" untuk laporan per baris.
     *
     * @param  list<int>  $gradeIds
     * @param  Collection<string, GradeLevel>  $grades
     */
    private function summary(SubjectCategory $category, array $gradeIds, Collection $grades): string
    {
        return $grades
            ->filter(static fn (GradeLevel $g): bool => in_array($g->id, $gradeIds, true))
            ->sortBy('sort_order')
            ->map(static fn (GradeLevel $g): string => 'Kelas '.$g->name.($g->phaseFor($category) !== null ? ' ('.$g->phaseFor($category).')' : ''))
            ->implode(', ');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hasContent(array $row): bool
    {
        foreach ($row as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function text(mixed $value): string
    {
        if (is_float($value) || is_int($value)) {
            return (string) $value;
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return list<string>
     */
    private function list(mixed $value): array
    {
        $parts = preg_split('/[,;\/\s]+/', mb_strtoupper($this->text($value))) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $parts), static fn (string $p): bool => $p !== '')));
    }

    /**
     * @return array{status: string, message: string}
     */
    private function failed(string $message): array
    {
        return ['status' => 'failed', 'message' => $message];
    }
}
