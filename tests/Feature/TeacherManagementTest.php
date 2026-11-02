<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\User\ImportTeachers;
use App\Enums\ImportStatus;
use App\Enums\RoleName;
use App\Enums\SemesterStatus;
use App\Models\AcademicYear;
use App\Models\ImportBatch;
use App\Models\Semester;
use App\Models\TeachingAssignment;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherManagementTest extends TestCase
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

    private function activeSemester(): Semester
    {
        $year = AcademicYear::query()->create(['name' => '2026/2027', 'starts_on' => '2026-07-13', 'ends_on' => '2027-06-26']);

        return Semester::query()->create([
            'academic_year_id' => $year->id,
            'term' => 'ganjil',
            'starts_on' => '2026-07-13',
            'ends_on' => '2026-12-19',
            'status' => SemesterStatus::Aktif,
        ]);
    }

    private function batchFromCsv(string $csv, bool $updateExisting = false): ImportBatch
    {
        $path = 'imports/uploads/test-'.uniqid().'.csv';
        Storage::disk('local')->put($path, $csv);

        return ImportBatch::query()->create([
            'type' => 'teachers',
            'original_name' => 'guru.csv',
            'file_path' => $path,
            'status' => ImportStatus::Pending,
            'options' => ['update_existing' => $updateExisting],
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_admin_creates_teacher_with_one_time_password(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/teachers', ['name' => 'Budi, S.Pd.', 'email' => 'Budi@Sekolah.test', 'nip' => '198503122010011001'])
            ->assertSessionHas('credential');

        $teacher = User::query()->where('email', 'budi@sekolah.test')->firstOrFail();

        $this->assertTrue($teacher->hasRole(RoleName::Teacher->value));
        $this->assertTrue($teacher->must_change_password);
        $this->assertNotEmpty(session('credential.password'));
    }

    public function test_duplicate_email_or_nip_is_rejected(): void
    {
        User::factory()->create(['email' => 'ada@sekolah.test', 'nip' => '198503122010011001']);

        $this->actingAs($this->admin)
            ->post('/admin/teachers', ['name' => 'Dobel', 'email' => 'ada@sekolah.test', 'nip' => '198503122010011001'])
            ->assertSessionHasErrors(['email', 'nip']);
    }

    public function test_validator_role_can_be_assigned_and_revoked(): void
    {
        $teacher = User::factory()->create(['is_active' => true]);
        $teacher->assignRole(RoleName::Teacher->value);

        $this->actingAs($this->admin)->post("/admin/validators/{$teacher->id}/toggle");
        $this->assertTrue($teacher->fresh()->hasRole(RoleName::Validator->value));
        $this->assertTrue($teacher->fresh()->hasRole(RoleName::Teacher->value), 'peran guru tetap ada');

        $this->actingAs($this->admin)->post("/admin/validators/{$teacher->id}/toggle");
        $this->assertFalse($teacher->fresh()->hasRole(RoleName::Validator->value));
    }

    public function test_admin_account_cannot_be_managed_through_teacher_module(): void
    {
        $otherAdmin = User::factory()->create();
        $otherAdmin->assignRole(RoleName::Admin->value);

        $this->actingAs($this->admin)->post("/admin/teachers/{$otherAdmin->id}/reset-password")->assertForbidden();
        $this->actingAs($this->admin)->delete("/admin/teachers/{$otherAdmin->id}")->assertForbidden();
    }

    public function test_password_reset_forces_change_and_hides_old_password(): void
    {
        $teacher = User::factory()->create(['password' => 'LamaSekali123', 'must_change_password' => false]);
        $teacher->assignRole(RoleName::Teacher->value);

        $this->actingAs($this->admin)->post("/admin/teachers/{$teacher->id}/reset-password")->assertSessionHas('credential');

        $fresh = $teacher->fresh();
        $this->assertTrue($fresh->must_change_password);
        $this->assertFalse(\Hash::check('LamaSekali123', $fresh->password));
    }

    public function test_deactivated_teacher_cannot_login(): void
    {
        $teacher = User::factory()->create(['password' => 'Rahasia12345', 'must_change_password' => false]);
        $teacher->assignRole(RoleName::Teacher->value);

        $this->actingAs($this->admin)->post("/admin/teachers/{$teacher->id}/toggle-active");
        $this->assertFalse($teacher->fresh()->is_active);

        auth()->logout();
        $this->post('/login', ['identifier' => $teacher->email, 'password' => 'Rahasia12345'])->assertSessionHasErrors('identifier');
    }

    public function test_import_creates_accounts_assignments_and_reports_bad_rows(): void
    {
        $semester = $this->activeSemester();

        $csv = <<<'CSV'
nama,email,nip,kode_mapel,tingkat,telepon
"Guru Satu, S.Pd.",satu@sekolah.test,198001012005011001,"MAT,FIS","X,XI",08123
Guru Dua,dua@sekolah.test,,BIN,XII,
Guru Salah,bukan-email,,MAT,X,
Guru Mapel Palsu,tiga@sekolah.test,,XXX,X,
Guru Tanpa Tingkat,empat@sekolah.test,,MAT,,
CSV;

        $batch = $this->batchFromCsv($csv);
        app(ImportTeachers::class)->handle($batch);
        $batch->refresh();

        $this->assertSame(ImportStatus::Done, $batch->status);
        $this->assertSame(5, $batch->total);
        $this->assertSame(2, $batch->success);
        $this->assertSame(3, $batch->failed);

        $satu = User::query()->where('email', 'satu@sekolah.test')->firstOrFail();
        $this->assertTrue($satu->must_change_password);
        $this->assertSame('198001012005011001', $satu->nip);
        // MAT (X, XI, XII) + FIS (pilihan: hanya XI, XII) dipilih untuk X dan XI => MAT-X, MAT-XI, FIS-XI.
        // FIS-X dilewati karena mapel pilihan tidak diajarkan di Kelas X.
        $this->assertSame(3, TeachingAssignment::query()->where('teacher_id', $satu->id)->where('semester_id', $semester->id)->count());

        $this->assertDatabaseMissing('users', ['email' => 'tiga@sekolah.test']);
        $this->assertDatabaseMissing('users', ['email' => 'empat@sekolah.test']);

        // Kartu akun dibuat dan hanya bisa diunduh satu kali.
        $this->assertNotNull($batch->report_path);
        $this->actingAs($this->admin)->get("/admin/teachers/import/{$batch->id}/accounts")->assertOk();
        $this->assertNull($batch->fresh()->report_path);
        $this->actingAs($this->admin)->get("/admin/teachers/import/{$batch->id}/accounts")->assertSessionHas('error');
    }

    public function test_import_uses_password_column_and_allows_nip_only_accounts(): void
    {
        $this->activeSemester();

        $csv = <<<'CSV'
nama,email,nip,password,kode_mapel,tingkat,telepon
Guru Sandi,sandi@sekolah.test,,RahasiaBaru123,MAT,X,
Guru NIP Saja,,198505052010012002,,BIN,XII,
Guru Sandi Pendek,pendek@sekolah.test,,abc,,,
Guru Tanpa Identitas,,,,,,
CSV;

        $batch = $this->batchFromCsv($csv);
        $batch->update(['options' => ['update_existing' => false, 'must_change_password' => false]]);
        app(ImportTeachers::class)->handle($batch);

        $this->assertSame(2, $batch->fresh()->success);
        $this->assertSame(2, $batch->fresh()->failed);

        $sandi = User::query()->where('email', 'sandi@sekolah.test')->firstOrFail();
        $this->assertFalse($sandi->must_change_password);

        $nipOnly = User::query()->where('nip', '198505052010012002')->firstOrFail();
        $this->assertNull($nipOnly->email);

        auth()->logout();
        $this->post('/login', ['identifier' => 'sandi@sekolah.test', 'password' => 'RahasiaBaru123'])->assertSessionHasNoErrors();
    }

    public function test_admin_can_create_teacher_with_nip_only_given_password_and_assignments(): void
    {
        $semester = $this->activeSemester();
        $mat = \App\Models\Subject::query()->where('code', 'MAT')->firstOrFail();
        $x = \App\Models\GradeLevel::query()->where('name', 'X')->firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/teachers', [
                'name' => 'Guru Baru',
                'nip' => '198001012005011009',
                'password' => 'SandiAwal123',
                'must_change_password' => false,
                'assignments' => [['subject_id' => $mat->id, 'grade_level_ids' => [$x->id]]],
            ])
            ->assertSessionHasNoErrors();

        $teacher = User::query()->where('nip', '198001012005011009')->firstOrFail();
        $this->assertNull($teacher->email);
        $this->assertFalse($teacher->must_change_password);
        $this->assertSame(1, TeachingAssignment::query()->where('teacher_id', $teacher->id)->where('semester_id', $semester->id)->count());

        // Ubah: kosongkan penugasan -> terhapus.
        $this->actingAs($this->admin)
            ->put("/admin/teachers/{$teacher->id}", ['name' => 'Guru Baru', 'nip' => '198001012005011009', 'assignments' => []])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, TeachingAssignment::query()->where('teacher_id', $teacher->id)->count());

        // Email atau NIP wajib salah satu.
        $this->actingAs($this->admin)->post('/admin/teachers', ['name' => 'Tanpa Identitas'])->assertSessionHasErrors(['email', 'nip']);

        auth()->logout();
        $this->post('/login', ['identifier' => '198001012005011009', 'password' => 'SandiAwal123'])->assertSessionHasNoErrors();
    }

    public function test_import_is_idempotent_and_skips_existing_by_default(): void
    {
        $this->activeSemester();
        $csv = "nama,email,nip,kode_mapel,tingkat,telepon\nGuru Satu,satu@sekolah.test,,MAT,X,\n";

        app(ImportTeachers::class)->handle($this->batchFromCsv($csv));
        $second = $this->batchFromCsv($csv);
        app(ImportTeachers::class)->handle($second);

        $this->assertSame(1, User::query()->where('email', 'satu@sekolah.test')->count());
        $this->assertSame(0, $second->fresh()->success);
        $this->assertSame('skipped', $second->fresh()->results[0]['status']);
    }

    public function test_import_can_update_existing_teacher_when_allowed(): void
    {
        $this->activeSemester();
        User::factory()->create(['name' => 'Nama Lama', 'email' => 'satu@sekolah.test']);

        $csv = "nama,email,nip,kode_mapel,tingkat,telepon\nNama Baru,satu@sekolah.test,,MAT,X,\n";
        app(ImportTeachers::class)->handle($this->batchFromCsv($csv, updateExisting: true));

        $user = User::query()->where('email', 'satu@sekolah.test')->firstOrFail();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertTrue($user->hasRole(RoleName::Teacher->value));
        $this->assertSame(1, TeachingAssignment::query()->where('teacher_id', $user->id)->count());
    }

    public function test_import_never_modifies_admin_accounts(): void
    {
        $csv = "nama,email,nip,kode_mapel,tingkat,telepon\nDiubah,{$this->admin->email},,,,\n";
        $batch = $this->batchFromCsv($csv, updateExisting: true);

        app(ImportTeachers::class)->handle($batch);

        $this->assertSame('failed', $batch->fresh()->results[0]['status']);
        $this->assertNotSame('Diubah', $this->admin->fresh()->name);
    }

    public function test_import_upload_validates_file_type(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $this->actingAs($this->admin)
            ->post('/admin/teachers/import', ['file' => \Illuminate\Http\UploadedFile::fake()->create('virus.exe', 10)])
            ->assertSessionHasErrors('file');

        \Illuminate\Support\Facades\Queue::assertNothingPushed();
    }
}
