<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PeriodStatus;
use App\Enums\PeriodType;
use App\Enums\RoleName;
use App\Enums\SemesterStatus;
use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\Semester;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['must_change_password' => false]);
        $this->admin->assignRole(RoleName::Admin->value);
    }

    private function year(string $name = '2026/2027'): AcademicYear
    {
        return AcademicYear::query()->create(['name' => $name, 'starts_on' => '2026-07-13', 'ends_on' => '2027-06-26']);
    }

    private function semester(AcademicYear $year, string $term, SemesterStatus $status): Semester
    {
        return Semester::query()->create([
            'academic_year_id' => $year->id,
            'term' => $term,
            'starts_on' => '2026-07-13',
            'ends_on' => '2026-12-19',
            'status' => $status,
        ]);
    }

    public function test_year_name_must_use_the_expected_format(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/academic-years', ['name' => '2026-2027', 'starts_on' => '2026-07-13', 'ends_on' => '2027-06-26'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin)
            ->post('/admin/academic-years', ['name' => '2026/2027', 'starts_on' => '2026-07-13', 'ends_on' => '2027-06-26'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('academic_years', ['name' => '2026/2027']);
    }

    public function test_only_one_semester_can_be_active_at_a_time(): void
    {
        $year = $this->year();
        $ganjil = $this->semester($year, 'ganjil', SemesterStatus::Aktif);
        $genap = $this->semester($year, 'genap', SemesterStatus::Rencana);

        $this->actingAs($this->admin)->post("/admin/semesters/{$genap->id}/activate")->assertRedirect();

        $this->assertSame(SemesterStatus::Aktif, $genap->fresh()->status);
        $this->assertSame(SemesterStatus::Arsip, $ganjil->fresh()->status);
        $this->assertSame(1, Semester::query()->where('status', 'aktif')->count());
    }

    public function test_archived_semester_cannot_be_edited(): void
    {
        $semester = $this->semester($this->year(), 'ganjil', SemesterStatus::Arsip);

        $this->actingAs($this->admin)->put("/admin/semesters/{$semester->id}", [
            'academic_year_id' => $semester->academic_year_id,
            'term' => 'ganjil',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-20',
        ])->assertSessionHas('error');

        $this->assertSame('2026-07-13', $semester->fresh()->starts_on->toDateString());
    }

    public function test_year_with_semesters_cannot_be_deleted(): void
    {
        $year = $this->year();
        $this->semester($year, 'ganjil', SemesterStatus::Rencana);

        $this->actingAs($this->admin)->delete("/admin/academic-years/{$year->id}")->assertSessionHas('error');

        $this->assertDatabaseHas('academic_years', ['id' => $year->id]);
    }

    public function test_period_type_must_be_unique_per_semester(): void
    {
        $semester = $this->semester($this->year(), 'ganjil', SemesterStatus::Aktif);
        $payload = ['semester_id' => $semester->id, 'name' => 'STS Ganjil', 'type' => 'sts'];

        $this->actingAs($this->admin)->post('/admin/periods', $payload)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/admin/periods', $payload)->assertSessionHasErrors('type');

        $this->assertSame(1, AssessmentPeriod::query()->count());
    }

    public function test_deadlines_must_be_in_chronological_order(): void
    {
        $semester = $this->semester($this->year(), 'ganjil', SemesterStatus::Aktif);

        $this->actingAs($this->admin)->post('/admin/periods', [
            'semester_id' => $semester->id,
            'name' => 'SAS Ganjil',
            'type' => 'sas',
            'upload_deadline' => '2026-11-23',
            'validation_deadline' => '2026-11-01',
        ])->assertSessionHasErrors('validation_deadline');
    }

    public function test_closed_period_is_read_only_until_reopened(): void
    {
        $semester = $this->semester($this->year(), 'ganjil', SemesterStatus::Aktif);
        $period = AssessmentPeriod::query()->create([
            'semester_id' => $semester->id,
            'name' => 'STS Ganjil',
            'type' => PeriodType::Sts,
            'status' => PeriodStatus::Buka,
        ]);

        $this->actingAs($this->admin)->post("/admin/periods/{$period->id}/toggle");
        $this->assertSame(PeriodStatus::Ditutup, $period->fresh()->status);

        $this->actingAs($this->admin)->put("/admin/periods/{$period->id}", [
            'semester_id' => $semester->id,
            'name' => 'Nama baru',
            'type' => 'sts',
        ])->assertSessionHas('error');
        $this->assertSame('STS Ganjil', $period->fresh()->name);

        $this->actingAs($this->admin)->post("/admin/periods/{$period->id}/toggle");
        $this->assertSame(PeriodStatus::Buka, $period->fresh()->status);
    }

    public function test_period_switch_is_stored_in_session(): void
    {
        $semester = $this->semester($this->year(), 'ganjil', SemesterStatus::Aktif);
        $period = AssessmentPeriod::query()->create(['semester_id' => $semester->id, 'name' => 'SAS', 'type' => PeriodType::Sas]);

        $this->actingAs($this->admin)->post('/semester/switch', ['period_id' => $period->id])->assertRedirect();
        $this->assertSame($period->id, session('current_period_id'));

        $this->actingAs($this->admin)->post('/semester/switch', ['period_id' => 9999])->assertNotFound();
    }
}
