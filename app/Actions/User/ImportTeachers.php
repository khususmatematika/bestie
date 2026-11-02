<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Enums\ImportStatus;
use App\Enums\RoleName;
use App\Exports\AccountCardsExport;
use App\Imports\TeacherRowsImport;
use App\Models\GradeLevel;
use App\Models\ImportBatch;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Memproses berkas impor akun guru per baris (FR-USR-03, rules.md §9.5).
 *
 * - Idempoten: NIP / email yang sama = pembaruan (jika diizinkan), bukan duplikat.
 * - Setiap baris dalam transaksi sendiri; baris salah tidak menggagalkan baris benar.
 * - Kata sandi awal hanya ditulis ke berkas "kartu akun" sementara, tidak ke log.
 */
final class ImportTeachers
{
    private bool $mustChange = true;

    public function __construct(private readonly CreateTeacher $createTeacher)
    {
    }

    public function handle(ImportBatch $batch): void
    {
        $batch->update([
            'status' => ImportStatus::Processing,
            'started_at' => now(),
            'error_message' => null,
        ]);

        /** @var list<array<string, mixed>> $rows */
        $rows = Excel::toArray(new TeacherRowsImport, $batch->file_path, 'local')[0] ?? [];

        $maxRows = (int) config('banksoal.import.max_rows', 1000);
        $filled = array_filter($rows, fn (array $row): bool => $this->hasContent($row));

        if (count($filled) > $maxRows) {
            $batch->update([
                'status' => ImportStatus::Failed,
                'error_message' => "Berkas melebihi batas {$maxRows} baris. Pecah menjadi beberapa berkas.",
                'finished_at' => now(),
            ]);

            return;
        }

        $updateExisting = (bool) ($batch->options['update_existing'] ?? false);
        $this->mustChange = (bool) ($batch->options['must_change_password'] ?? true);
        $semester = Semester::current();

        /** @var Collection<string, Subject> $subjects */
        $subjects = Subject::query()->with('gradeLevels:id')->get()->keyBy(static fn (Subject $s): string => mb_strtoupper($s->code));
        /** @var Collection<string, GradeLevel> $grades */
        $grades = GradeLevel::query()->get()->keyBy(static fn (GradeLevel $g): string => mb_strtoupper($g->name));

        $results = [];
        $credentials = [];
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($rows as $index => $row) {
            if (! $this->hasContent($row)) {
                continue;
            }

            $line = $index + 2; // baris 1 = judul kolom

            try {
                $outcome = DB::transaction(
                    fn (): array => $this->processRow($row, $updateExisting, $semester, $subjects, $grades),
                );
            } catch (Throwable $e) {
                report($e);
                $outcome = ['status' => 'failed', 'message' => 'Terjadi kesalahan saat menyimpan baris ini.'];
            }

            $counts[$outcome['status']]++;

            if (isset($outcome['credential'])) {
                $credentials[] = $outcome['credential'];
            }

            $results[] = [
                'row' => $line,
                'name' => (string) ($row['nama'] ?? ''),
                'status' => $outcome['status'],
                'message' => $outcome['message'],
            ];
        }

        $reportPath = null;

        if ($credentials !== []) {
            $reportPath = 'imports/accounts/'.Str::uuid().'.xlsx';
            Excel::store(new AccountCardsExport($credentials), $reportPath, 'local');
        }

        $batch->update([
            'status' => ImportStatus::Done,
            'total' => count($results),
            'success' => $counts['created'] + $counts['updated'],
            'failed' => $counts['failed'],
            'results' => $results,
            'report_path' => $reportPath,
            'finished_at' => now(),
        ]);

        ActivityLogger::log('import.teachers.finished', $batch, $counts, $batch->creator);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  Collection<string, Subject>  $subjects
     * @param  Collection<string, GradeLevel>  $grades
     * @return array{status: string, message: string, credential?: array{name: string, email: string|null, nip: string|null, password: string}}
     */
    private function processRow(array $row, bool $updateExisting, ?Semester $semester, Collection $subjects, Collection $grades): array
    {
        $data = $this->normalize($row);

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'required_without:nip', 'email', 'max:150'],
            'nip' => ['nullable', 'required_without:email', 'digits_between:8,25'],
            'password' => ['nullable', 'string', 'min:8', 'max:64'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        if ($validator->fails()) {
            return $this->failed(implode(' ', $validator->errors()->all()));
        }

        $picked = [];
        foreach ($data['subjects'] as $code) {
            $subject = $subjects->get($code);

            if ($subject === null) {
                return $this->failed("Kode mapel '{$code}' tidak ditemukan.");
            }

            $picked[] = $subject;
        }

        $pickedGrades = [];
        foreach ($data['grades'] as $name) {
            $grade = $grades->get($name);

            if ($grade === null) {
                return $this->failed("Tingkat '{$name}' tidak valid (gunakan X, XI, atau XII).");
            }

            $pickedGrades[] = $grade;
        }

        if ($picked !== [] && $pickedGrades === []) {
            return $this->failed('Kolom tingkat wajib diisi jika kode_mapel diisi.');
        }

        // Hanya kombinasi mapel x tingkat yang memang diajarkan (menu Mapel) yang dibuat penugasannya.
        $pairs = [];
        $invalid = [];
        foreach ($picked as $subject) {
            foreach ($pickedGrades as $grade) {
                if ($subject->gradeLevels->contains('id', $grade->id)) {
                    $pairs[] = [$subject->id, $grade->id];
                } else {
                    $invalid[] = "{$subject->code}-{$grade->name}";
                }
            }
        }

        if ($picked !== [] && $pairs === []) {
            return $this->failed('Mapel tidak diajarkan pada tingkat tersebut: '.implode(', ', $invalid).'.');
        }

        $skipNote = $invalid === [] ? '' : 'Dilewati (mapel tidak diajarkan di tingkat itu): '.implode(', ', $invalid).'.';

        $byEmail = $data['email'] !== null ? User::withTrashed()->where('email', $data['email'])->first() : null;
        $byNip = $data['nip'] !== null ? User::withTrashed()->where('nip', $data['nip'])->first() : null;

        if ($byEmail !== null && $byNip !== null && $byEmail->isNot($byNip)) {
            return $this->failed('NIP dan email terdaftar pada dua akun yang berbeda.');
        }

        $existing = $byEmail ?? $byNip;

        if ($existing !== null) {
            if ($existing->trashed()) {
                return $this->failed('Akun ini pernah dihapus dan tidak dapat diimpor ulang.');
            }

            if ($existing->hasRole(RoleName::Admin->value)) {
                return $this->failed('Akun admin tidak dapat diubah melalui impor.');
            }

            if (! $updateExisting) {
                return ['status' => 'skipped', 'message' => 'Sudah terdaftar, dilewati.'];
            }

            $existing->update(array_filter([
                'name' => $data['name'],
                'email' => $data['email'],
                'nip' => $data['nip'],
            ], static fn (mixed $value): bool => $value !== null));

            if ($data['password'] !== null) {
                $existing->forceFill(['password' => $data['password'], 'must_change_password' => $this->mustChange])->save();
            }

            if ($data['phone'] !== null) {
                $existing->profile()->updateOrCreate([], ['phone' => $data['phone']]);
            }

            if (! $existing->hasAnyRole([RoleName::Teacher->value, RoleName::Validator->value])) {
                $existing->assignRole(RoleName::Teacher->value);
            }

            $note = $this->assign($existing, $semester, $pairs, $skipNote);

            return [
                'status' => 'updated',
                'message' => trim('Data diperbarui. '.($data['password'] !== null ? 'Kata sandi diperbarui. ' : '').$note),
                ...($data['password'] !== null ? ['credential' => [
                    'name' => $existing->name,
                    'email' => $existing->email,
                    'nip' => $existing->nip,
                    'password' => $data['password'],
                ]] : []),
            ];
        }

        $created = $this->createTeacher->handle([
            'name' => $data['name'],
            'email' => $data['email'],
            'nip' => $data['nip'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'must_change_password' => $this->mustChange,
        ]);

        $note = $this->assign($created['user'], $semester, $pairs, $skipNote);

        return [
            'status' => 'created',
            'message' => trim('Akun dibuat. '.$note),
            'credential' => [
                'name' => $created['user']->name,
                'email' => $created['user']->email,
                'nip' => $created['user']->nip,
                'password' => $created['password'],
            ],
        ];
    }

    /**
     * Membuat penugasan mapel x tingkat pada semester aktif.
     *
     * @param  list<array{0: int, 1: int}>  $pairs  pasangan [subject_id, grade_level_id] yang valid
     */
    private function assign(User $user, ?Semester $semester, array $pairs, string $skipNote): string
    {
        if ($pairs === []) {
            return $skipNote;
        }

        if ($semester === null) {
            return trim('Penugasan mapel dilewati karena belum ada semester aktif. '.$skipNote);
        }

        $count = 0;

        foreach ($pairs as [$subjectId, $gradeId]) {
            $assignment = TeachingAssignment::query()->firstOrCreate([
                'semester_id' => $semester->id,
                'teacher_id' => $user->id,
                'subject_id' => $subjectId,
                'grade_level_id' => $gradeId,
            ]);

            if ($assignment->wasRecentlyCreated) {
                $count++;
            }
        }

        return trim(($count > 0 ? "{$count} penugasan mapel ditambahkan. " : '').$skipNote);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{name: string, email: string|null, nip: string|null, phone: string|null, password: string|null, subjects: list<string>, grades: list<string>}
     */
    private function normalize(array $row): array
    {
        $phone = trim($this->text($row['telepon'] ?? null));

        return [
            'name' => trim($this->text($row['nama'] ?? null)),
            'email' => ($email = mb_strtolower(trim($this->text($row['email'] ?? null)))) === '' ? null : $email,
            'password' => ($password = trim(is_scalar($row['password'] ?? null) ? (string) $row['password'] : '')) === '' ? null : $password,
            'nip' => $this->digits($row['nip'] ?? null),
            'phone' => $phone === '' ? null : $phone,
            'subjects' => $this->list($row['kode_mapel'] ?? null),
            'grades' => $this->list($row['tingkat'] ?? null),
        ];
    }

    private function text(mixed $value): string
    {
        if (is_float($value) || is_int($value)) {
            return number_format((float) $value, 0, '', '');
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function digits(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $this->text($value)) ?? '';

        return $digits === '' ? null : $digits;
    }

    /**
     * @return list<string>
     */
    private function list(mixed $value): array
    {
        $parts = preg_split('/[,;]+/', $this->text($value)) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $part): string => mb_strtoupper(trim($part)),
            $parts,
        ), static fn (string $part): bool => $part !== ''));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hasContent(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) (is_scalar($value) ? $value : '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{status: string, message: string}
     */
    private function failed(string $message): array
    {
        return ['status' => 'failed', 'message' => $message];
    }
}
