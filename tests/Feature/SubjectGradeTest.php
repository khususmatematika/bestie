<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Master\ImportSubjects;
use App\Enums\RoleName;
use App\Enums\SemesterStatus;
use App\Enums\SubjectCategory;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Mapel x tingkat kelas x fase (Kelas X = Fase E; XI/XII = Fase F umum, Fase F+ pilihan).
 */
class SubjectGradeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RoleSeeder::class, ReferenceSeeder::class]);

        $this->admin = User::factory()->create(['must_change_password' => false]);
        $this->admin->assignRole(RoleName::Admin->value);
    }

    private function gradeId(string $name): int
    {
        return GradeLevel::query()->where('name', $name)->firstOrFail()->id;
    }

    private function csv(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'mapel').'.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'mapel.csv', 'text/csv', null, true);
    }

    public function test_phases_follow_grade_and_category(): void
    {
        $x = GradeLevel::query()->where('name', 'X')->firstOrFail();
        $xi = GradeLevel::query()->where('name', 'XI')->firstOrFail();
        $xii = GradeLevel::query()->where('name', 'XII')->firstOrFail();

        $this->assertSame('Fase E', $x->phaseFor(SubjectCategory::Umum));
        $this->assertSame('Fase E', $x->phaseFor(SubjectCategory::Pilihan));
        $this->assertSame('Fase F', $xi->phaseFor(SubjectCategory::Umum));
        $this->assertSame('Fase F+', $xi->phaseFor(SubjectCategory::Pilihan));
        $this->assertSame('Fase F', $xii->phaseFor(SubjectCategory::Umum));
        $this->assertSame('Fase F+', $xii->phaseFor(SubjectCategory::Pilihan));
    }

    public function test_seeded_subjects_are_linked_to_the_right_grades(): void
    {
        $mat = Subject::query()->where('code', 'MAT')->firstOrFail();
        $fis = Subject::query()->where('code', 'FIS')->firstOrFail();

        $this->assertEqualsCanonicalizing(['X', 'XI', 'XII'], $mat->gradeLevels->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['XI', 'XII'], $fis->gradeLevels->pluck('name')->all());
        $this->assertSame(SubjectCategory::Pilihan, $fis->category);
    }

    public function test_admin_creates_subject_with_category_and_grades(): void
    {
        $this->actingAs($this->admin)->post('/admin/subjects', [
            'code' => 'sen',
            'name' => 'Seni Budaya',
            'category' => 'umum',
            'grade_level_ids' => [$this->gradeId('X')],
        ])->assertSessionHasNoErrors();

        $subject = Subject::query()->where('code', 'SEN')->firstOrFail();
        $this->assertSame(['X'], $subject->gradeLevels->pluck('name')->all());
    }

    public function test_subject_requires_valid_category_and_at_least_one_grade(): void
    {
        $this->actingAs($this->admin)->post('/admin/subjects', [
            'code' => 'ZZZ', 'name' => 'Uji', 'category' => 'lainnya', 'grade_level_ids' => [],
        ])->assertSessionHasErrors(['category', 'grade_level_ids']);

        $this->assertDatabaseMissing('subjects', ['code' => 'ZZZ']);
    }

    public function test_updating_subject_replaces_its_grades(): void
    {
        $subject = Subject::query()->where('code', 'MAT')->firstOrFail();

        $this->actingAs($this->admin)->put("/admin/subjects/{$subject->id}", [
            'code' => 'MAT',
            'name' => 'Matematika',
            'category' => 'umum',
            'grade_level_ids' => [$this->gradeId('XII')],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['XII'], $subject->fresh()->gradeLevels->pluck('name')->all());
    }

    public function test_assignment_rejects_grade_not_taught_for_the_subject(): void
    {
        $year = AcademicYear::query()->create(['name' => '2026/2027', 'starts_on' => '2026-07-13', 'ends_on' => '2027-06-26']);
        $semester = Semester::query()->create([
            'academic_year_id' => $year->id, 'term' => 'ganjil',
            'starts_on' => '2026-07-13', 'ends_on' => '2026-12-19', 'status' => SemesterStatus::Aktif,
        ]);
        $teacher = User::factory()->create(['is_active' => true]);
        $teacher->assignRole(RoleName::Teacher->value);
        $fis = Subject::query()->where('code', 'FIS')->firstOrFail(); // pilihan: hanya XI & XII

        $payload = ['semester_id' => $semester->id, 'teacher_id' => $teacher->id, 'subject_id' => $fis->id];

        $this->actingAs($this->admin)
            ->post('/admin/assignments', $payload + ['grade_level_ids' => [$this->gradeId('X')]])
            ->assertSessionHasErrors('grade_level_ids');
        $this->assertSame(0, TeachingAssignment::query()->count());

        $this->actingAs($this->admin)
            ->post('/admin/assignments', $payload + ['grade_level_ids' => [$this->gradeId('XI'), $this->gradeId('XII')]])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, TeachingAssignment::query()->count());
    }

    public function test_subject_import_creates_updates_skips_and_reports_bad_rows(): void
    {
        $csv = <<<'CSV'
kode_mapel,nama_mapel,kategori,tingkat
SENI,Seni Musik,umum,X
ASTRO,Astronomi,pilihan,"XI,XII"
BIN,Bahasa Indonesia (baru),umum,"X,XI"
TANPA,Tanpa Tingkat,umum,
SALAH,Kategori Salah,wajibpilih,X
KLS,Kelas Salah,umum,XIII
,Tanpa Kode,umum,X
CSV;

        $report = app(ImportSubjects::class)->handle($this->csv($csv), updateExisting: true, actor: $this->admin);

        $this->assertSame(7, $report['total']);
        $this->assertSame(2, $report['created']);
        $this->assertSame(1, $report['updated']);
        $this->assertSame(4, $report['failed']);

        $seni = Subject::query()->where('code', 'SENI')->firstOrFail();
        $this->assertSame(['X'], $seni->gradeLevels->pluck('name')->all());
        $this->assertSame(SubjectCategory::Umum, $seni->category);

        $astro = Subject::query()->where('code', 'ASTRO')->firstOrFail();
        $this->assertSame(SubjectCategory::Pilihan, $astro->category);
        $this->assertEqualsCanonicalizing(['XI', 'XII'], $astro->gradeLevels->pluck('name')->all());

        $bin = Subject::query()->where('code', 'BIN')->firstOrFail();
        $this->assertSame('Bahasa Indonesia (baru)', $bin->name);
        $this->assertEqualsCanonicalizing(['X', 'XI'], $bin->gradeLevels->pluck('name')->all());

        $this->assertDatabaseMissing('subjects', ['code' => 'TANPA']);
        $this->assertDatabaseMissing('subjects', ['code' => 'SALAH']);
        $this->assertDatabaseMissing('subjects', ['code' => 'KLS']);
    }

    public function test_subject_import_skips_existing_codes_when_update_is_off(): void
    {
        $csv = "kode_mapel,nama_mapel,kategori,tingkat\nMAT,Nama Lain,umum,X\n";

        $report = app(ImportSubjects::class)->handle($this->csv($csv), updateExisting: false, actor: $this->admin);

        $this->assertSame(1, $report['skipped']);
        $this->assertSame('Matematika', Subject::query()->where('code', 'MAT')->firstOrFail()->name);
        $this->assertCount(3, Subject::query()->where('code', 'MAT')->firstOrFail()->gradeLevels);
    }

    public function test_only_admin_can_use_subject_import(): void
    {
        $teacher = User::factory()->create(['must_change_password' => false]);
        $teacher->assignRole(RoleName::Teacher->value);

        $this->actingAs($this->admin)->get('/admin/subjects/import')->assertOk();
        $this->actingAs($teacher)->get('/admin/subjects/import')->assertForbidden();
        $this->actingAs($teacher)->post('/admin/subjects/import', [])->assertForbidden();
    }

    public function test_subject_import_upload_validates_file_type(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/subjects/import', ['file' => UploadedFile::fake()->create('virus.exe', 10)])
            ->assertSessionHasErrors('file');
    }
}
