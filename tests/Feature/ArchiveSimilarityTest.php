<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ItemStatus;
use App\Enums\PackageStatus;
use App\Enums\PeriodStatus;
use App\Enums\PeriodType;
use App\Enums\QuestionType;
use App\Enums\RoleName;
use App\Enums\SemesterStatus;
use App\Enums\SemesterTerm;
use App\Models\AcademicYear;
use App\Models\ArchiveImport;
use App\Models\AssessmentPeriod;
use App\Models\ArchiveItem;
use App\Models\QuestionItem;
use App\Models\QuestionPackage;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\GradeLevel;
use App\Models\User;
use App\Services\Similarity\ItemSimilarity;
use Database\Seeders\ReferenceSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Arsip soal tahun lalu + deteksi kemiripan (M3).
 */
class ArchiveSimilarityTest extends TestCase
{
    use RefreshDatabase;

    public function test_similarity_math_prefers_near_duplicates(): void
    {
        $a = 'Hitung luas lingkaran dengan jari-jari 7 cm. A. 154 B. 144 C. 124';
        $same = ItemSimilarity::score(ItemSimilarity::shingles(ItemSimilarity::normalize($a)), ItemSimilarity::shingles(ItemSimilarity::normalize('Hitung luas lingkaran dengan jari-jari 7 cm.')), 'Hitung luas lingkaran dengan jari-jari 7 cm.', $a);
        $other = ItemSimilarity::score(ItemSimilarity::shingles(ItemSimilarity::normalize($a)), ItemSimilarity::shingles(ItemSimilarity::normalize('Hitung luas lingkaran dengan jari-jari 7 cm.')), 'Tentukan modus dari data berikut', 'Tentukan modus dari data berikut A. 3 B. 4');

        $this->assertGreaterThan(0.7, $same);
        $this->assertLessThan(0.2, $other);
        $this->assertSame('a b c', ItemSimilarity::normalize('⟦img:3⟧ A, \\(b\\) C!'));
    }

    public function test_admin_imports_archive_and_validator_sees_similar_items(): void
    {
        $this->withoutVite();
        Storage::fake('local');
        config(['queue.default' => 'sync']);
        $this->seed([RoleSeeder::class, ReferenceSeeder::class]);

        $admin = $this->user(RoleName::Admin);
        $teacher = $this->user(RoleName::Teacher);
        $validator = $this->user(RoleName::Validator);
        $subject = Subject::query()->firstOrFail();
        $grade = GradeLevel::query()->firstOrFail();

        $year = AcademicYear::query()->create(['name' => '2026/2027', 'starts_on' => '2026-07-13', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $semester = Semester::query()->create(['academic_year_id' => $year->id, 'term' => SemesterTerm::Ganjil, 'starts_on' => '2026-07-13', 'ends_on' => '2026-12-31', 'status' => SemesterStatus::Aktif]);
        $period = AssessmentPeriod::query()->create(['semester_id' => $semester->id, 'name' => 'STS Ganjil', 'type' => PeriodType::Sts, 'status' => PeriodStatus::Buka]);

        $fixture = __DIR__.'/../fixtures/naskah-asat-matematika-xi.docx';

        $this->actingAs($admin)->post('/admin/archive', [
            'subject_id' => $subject->id,
            'grade_level_id' => $grade->id,
            'year_label' => 'Genap 2024/2025',
            'file' => new UploadedFile($fixture, 'naskah-lama.docx', null, null, true),
        ])->assertSessionHasNoErrors();

        $import = ArchiveImport::query()->firstOrFail();
        $this->assertSame(21, $import->items_count);
        $archived = ArchiveItem::query()->where('archive_import_id', $import->id)->where('number', 1)->firstOrFail();

        $package = QuestionPackage::query()->create([
            'assessment_period_id' => $period->id, 'teacher_id' => $teacher->id, 'validator_id' => $validator->id,
            'subject_id' => $subject->id, 'grade_level_id' => $grade->id, 'title' => 'Paket uji', 'status' => PackageStatus::Terkirim,
        ]);

        // Butir yang menyalin soal arsip nomor 1 (teks sama persis).
        QuestionItem::query()->create([
            'package_id' => $package->id, 'number' => 1, 'status' => ItemStatus::BelumDitelaah, 'type' => QuestionType::PilihanGanda,
            'stem' => $archived->stem, 'options' => $archived->options, 'answer_key' => 'A', 'blueprint' => ['indikator' => 'x'],
        ]);

        $this->actingAs($validator)->get("/validator/packages/{$package->id}/review/1")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('similar.0.score', 100)->where('similar.0.source', 'arsip'));

        // Pengguna non-admin tidak boleh mengimpor arsip.
        $this->actingAs($teacher)->post('/admin/archive', ['subject_id' => $subject->id, 'year_label' => 'x'])->assertForbidden();

        $this->actingAs($admin)->delete("/admin/archive/{$import->id}")->assertRedirect();
        $this->assertSame(0, ArchiveItem::query()->count());
    }

    private function user(RoleName $role): User
    {
        $user = User::factory()->create(['must_change_password' => false, 'is_active' => true]);
        $user->assignRole($role->value);

        return $user;
    }
}
