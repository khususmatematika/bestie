<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Package\LockPackage;
use App\Actions\Package\SubmitPackage;
use App\Actions\Package\UploadItemRevision;
use App\Actions\Review\ApproveItem;
use App\Actions\Review\ReopenItem;
use App\Actions\Review\RequestItemChange;
use App\Enums\ItemStatus;
use App\Enums\PackageStatus;
use App\Enums\PeriodStatus;
use App\Enums\PeriodType;
use App\Enums\QuestionType;
use App\Enums\RoleName;
use App\Enums\SemesterStatus;
use App\Enums\SemesterTerm;
use App\Exceptions\WorkflowException;
use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\GradeLevel;
use App\Models\ItemRevision;
use App\Models\QuestionItem;
use App\Models\QuestionPackage;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use App\Actions\Export\RequestFinalExport;
use App\Enums\ExportStatus;
use App\Models\PackageExport;
use Tests\TestCase;

/**
 * Alur paket soal M1: kirim (R-02), telaah per nomor (R-03, R-10), revisi (R-04, R-05),
 * FIX otomatis (R-06), buka kembali (R-07), kunci (R-08), periode ditutup (R-12).
 */
class PackageWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $validator;

    private User $other;

    private User $admin;

    private AssessmentPeriod $period;

    private Subject $subject;

    private GradeLevel $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        config(['queue.default' => 'sync']);
        $this->seed([RoleSeeder::class, ReferenceSeeder::class]);

        $this->teacher = $this->makeUser(RoleName::Teacher);
        $this->validator = $this->makeUser(RoleName::Validator);
        $this->other = $this->makeUser(RoleName::Validator);
        $this->admin = $this->makeUser(RoleName::Admin);

        $year = AcademicYear::query()->create(['name' => '2026/2027', 'starts_on' => '2026-07-13', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $semester = Semester::query()->create([
            'academic_year_id' => $year->id, 'term' => SemesterTerm::Ganjil, 'starts_on' => '2026-07-13', 'ends_on' => '2026-12-31', 'status' => SemesterStatus::Aktif,
        ]);
        $this->period = AssessmentPeriod::query()->create([
            'semester_id' => $semester->id, 'name' => 'STS Ganjil', 'type' => PeriodType::Sts, 'status' => PeriodStatus::Buka,
        ]);
        $this->subject = Subject::query()->firstOrFail();
        $this->grade = GradeLevel::query()->firstOrFail();
    }

    private function makeUser(RoleName $role): User
    {
        $user = User::factory()->create(['must_change_password' => false, 'is_active' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    private function item(QuestionPackage $package, int $number, bool $complete = true): QuestionItem
    {
        $item = QuestionItem::query()->create([
            'package_id' => $package->id,
            'number' => $number,
            'status' => ItemStatus::BelumDitelaah,
            'type' => QuestionType::PilihanGanda,
            'stem' => $complete ? "Soal nomor {$number}" : '',
            'options' => ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'],
            'answer_key' => $complete ? 'A' : '',
            'blueprint' => ['indikator' => 'Indikator', 'bobot' => 1],
        ]);

        $revision = ItemRevision::query()->create([
            'item_id' => $item->id, 'version' => 1, 'snapshot' => $item->content(), 'source' => 'ekstraksi',
            'uploaded_by' => $this->teacher->id, 'uploaded_at' => now(),
        ]);
        $item->update(['current_revision_id' => $revision->id]);

        return $item->refresh();
    }

    private function package(int $items = 2, bool $complete = true): QuestionPackage
    {
        $package = QuestionPackage::query()->create([
            'assessment_period_id' => $this->period->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'grade_level_id' => $this->grade->id,
            'title' => 'Paket uji',
            'status' => PackageStatus::Draft,
        ]);

        for ($n = 1; $n <= $items; $n++) {
            $this->item($package, $n, $complete);
        }

        return $package->refresh();
    }

    private function submitted(int $items = 2): QuestionPackage
    {
        return app(SubmitPackage::class)->handle($this->package($items), $this->teacher, $this->validator->id);
    }

    public function test_incomplete_package_cannot_be_submitted(): void
    {
        $this->expectException(WorkflowException::class);

        app(SubmitPackage::class)->handle($this->package(2, false), $this->teacher, $this->validator->id);
    }

    public function test_submit_moves_package_to_inbox_of_chosen_validator(): void
    {
        $package = $this->submitted();

        $this->assertSame(PackageStatus::Terkirim, $package->status);
        $this->assertSame($this->validator->id, $package->validator_id);
        $this->assertNotNull($package->submitted_at);

        $this->actingAs($this->validator)->get('/validator/inbox')->assertOk();
    }

    public function test_request_change_requires_note_of_ten_characters(): void
    {
        $package = $this->submitted();
        $item = $package->items()->firstOrFail();

        $this->expectException(WorkflowException::class);

        app(RequestItemChange::class)->handle($item, $this->validator, 'pendek');
    }

    public function test_only_chosen_validator_can_review(): void
    {
        $package = $this->submitted();
        $item = $package->items()->firstOrFail();

        $this->expectException(AuthorizationException::class);

        app(ApproveItem::class)->handle($item, $this->other);
    }

    public function test_full_cycle_reaches_fix_with_versions_kept_and_can_be_reopened_then_locked(): void
    {
        $package = $this->submitted();
        [$one, $two] = $package->items()->get()->all();

        app(ApproveItem::class)->handle($one, $this->validator);
        app(RequestItemChange::class)->handle($two, $this->validator, 'Opsi pengecoh kurang homogen, mohon diperbaiki.');

        $package->refresh();
        $this->assertSame(PackageStatus::PerluRevisi, $package->status);

        // R-04: nomor yang sudah disetujui tidak bisa diunggah ulang.
        try {
            app(UploadItemRevision::class)->handle($one->refresh(), $this->teacher, ['stem' => 'x']);
            $this->fail('Nomor disetujui tidak boleh diunggah ulang.');
        } catch (WorkflowException) {
            $this->addToAssertionCount(1);
        }

        app(UploadItemRevision::class)->handle($two->refresh(), $this->teacher, [
            'type' => 'pg', 'stem' => 'Soal nomor 2 (revisi)', 'options' => ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'],
            'answer_key' => 'B', 'blueprint' => ['indikator' => 'Indikator', 'bobot' => 1],
        ]);

        $this->assertSame(2, ItemRevision::query()->where('item_id', $two->id)->count()); // R-05
        $this->assertSame(ItemStatus::Direvisi, $two->refresh()->status);

        app(ApproveItem::class)->handle($two->refresh(), $this->validator);

        $package->refresh();
        $this->assertSame(PackageStatus::Fix, $package->status); // R-06
        $this->assertSame(100, (int) $package->progress_percent);

        app(ReopenItem::class)->handle($one->refresh(), $this->validator, 'Ditemukan kesalahan konsep pada kunci.');
        $this->assertSame(PackageStatus::PerluRevisi, $package->refresh()->status); // R-07

        app(UploadItemRevision::class)->handle($one->refresh(), $this->teacher, [
            'type' => 'pg', 'stem' => 'Soal nomor 1 (revisi)', 'options' => ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'],
            'answer_key' => 'C', 'blueprint' => ['indikator' => 'Indikator', 'bobot' => 1],
        ]);
        app(ApproveItem::class)->handle($one->refresh(), $this->validator);
        $this->assertSame(PackageStatus::Fix, $package->refresh()->status);

        app(LockPackage::class)->handle($package, $this->admin);
        $package->refresh();
        $this->assertSame(PackageStatus::Terkunci, $package->status);

        // R-08: paket terkunci tidak bisa diubah lagi.
        $this->expectException(WorkflowException::class);
        app(ReopenItem::class)->handle($one->refresh(), $this->validator, 'Coba buka kembali paket terkunci.');
    }

    public function test_closed_period_is_read_only(): void
    {
        $package = $this->submitted();
        $this->period->update(['status' => PeriodStatus::Ditutup]);

        $this->expectException(WorkflowException::class);

        app(ApproveItem::class)->handle($package->items()->firstOrFail(), $this->validator);
    }

    public function test_teacher_can_pick_self_as_validator_only_when_allowed(): void
    {
        $this->teacher->assignRole(RoleName::Validator->value);

        $package = $this->package();
        $package = app(SubmitPackage::class)->handle($package, $this->teacher, $this->teacher->id);
        $this->assertTrue($package->is_self_validated);
    }

    public function test_role_access_for_package_pages(): void
    {
        $this->actingAs($this->teacher)->get('/teacher/packages')->assertOk();
        $this->actingAs($this->teacher)->get('/validator/inbox')->assertForbidden();
        $this->actingAs($this->validator)->get('/validator/inbox')->assertOk();
        $this->actingAs($this->validator)->get('/teacher/packages')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/packages')->assertOk();
        $this->actingAs($this->teacher)->get('/admin/packages')->assertForbidden();
    }

    public function test_package_file_download_is_authorized(): void
    {
        $package = $this->submitted();

        $this->actingAs($this->other)->get("/packages/{$package->id}/files/999999")->assertStatus(404);
    }

    private function fixedPackage(): QuestionPackage
    {
        $package = $this->submitted();

        foreach ($package->items()->get() as $item) {
            app(ApproveItem::class)->handle($item, $this->validator);
        }

        return $package->refresh();
    }

    public function test_final_export_is_created_automatically_at_fix_and_downloadable(): void
    {
        $package = $this->fixedPackage();

        $this->assertSame(PackageStatus::Fix, $package->status);

        $export = PackageExport::query()->where('package_id', $package->id)->firstOrFail();
        $this->assertSame(ExportStatus::Ready, $export->status);
        $this->assertNotNull($export->sha256);
        Storage::disk('local')->assertExists($export->path);

        $this->actingAs($this->teacher)->get("/packages/{$package->id}/downloads/export/{$export->id}/docx")->assertOk();
        $this->actingAs($this->other)->get("/packages/{$package->id}/downloads/export/{$export->id}/docx")->assertForbidden();

        // Permintaan ulang memakai ekspor yang sama.
        $this->assertSame($export->id, app(RequestFinalExport::class)->handle($package, $this->teacher)->id);
    }

    public function test_final_export_requires_fix(): void
    {
        $this->expectException(WorkflowException::class);

        app(RequestFinalExport::class)->handle($this->submitted(), $this->teacher);
    }

    public function test_recap_and_validated_downloads_follow_access_rules(): void
    {
        $package = $this->submitted();
        app(ApproveItem::class)->handle($package->items()->firstOrFail(), $this->validator);

        $this->actingAs($this->teacher)->get("/packages/{$package->id}/downloads/recap")->assertOk();
        $this->actingAs($this->validator)->get("/packages/{$package->id}/downloads/validated")->assertOk();
        $this->actingAs($this->admin)->get("/packages/{$package->id}/downloads/validated?numbers=1")->assertOk();
        $this->actingAs($this->other)->get("/packages/{$package->id}/downloads/recap")->assertForbidden();
    }

    public function test_teacher_can_paste_image_into_draft_item_and_it_is_linked(): void
    {
        Storage::fake('local');
        $package = $this->package(1);
        $item = $package->items()->first();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

        $response = $this->actingAs($this->teacher)->post("/teacher/items/{$item->id}/images", [
            'image' => \Illuminate\Http\UploadedFile::fake()->createWithContent('tempel.png', (string) $png),
        ]);

        $response->assertOk()->assertJsonStructure(['id', 'url']);
        $this->assertSame([$response->json('id')], $item->refresh()->images);

        $this->actingAs($this->other)->post("/teacher/items/{$item->id}/images", [
            'image' => \Illuminate\Http\UploadedFile::fake()->createWithContent('x.png', (string) $png),
        ])->assertForbidden();

        $this->actingAs($this->teacher)->withHeaders(['Accept' => 'application/json'])->post("/teacher/items/{$item->id}/images", [
            'image' => \Illuminate\Http\UploadedFile::fake()->createWithContent('x.png', 'bukan gambar'),
        ])->assertStatus(422);
    }

    public function test_images_not_referenced_in_text_are_released_on_save(): void
    {
        Storage::fake('local');
        $package = $this->package(1);
        $item = $package->items()->first();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

        $id = $this->actingAs($this->teacher)->post("/teacher/items/{$item->id}/images", [
            'image' => \Illuminate\Http\UploadedFile::fake()->createWithContent('a.png', (string) $png),
        ])->json('id');

        $content = $item->refresh()->content();
        $content['stem'] = 'Soal dengan gambar ⟦img:'.$id.'⟧';
        app(\App\Actions\Package\SaveDraftItem::class)->update($item, $this->teacher, $content);
        $this->assertSame([$id], $item->refresh()->images);

        $content['stem'] = 'Soal tanpa gambar';
        app(\App\Actions\Package\SaveDraftItem::class)->update($item, $this->teacher, $content);
        $this->assertEmpty($item->refresh()->images);
    }

    public function test_workflow_events_create_in_app_notifications_and_users_only_see_their_own(): void
    {
        $package = $this->submitted(2);

        // Kirim paket -> validator mendapat notifikasi.
        $this->assertSame(1, $this->validator->unreadNotifications()->count());
        $this->assertSame('paket_baru', $this->validator->notifications()->first()->data['kind']);

        $two = $package->items()->where('number', 2)->first();
        app(RequestItemChange::class)->handle($two, $this->validator, 'Opsi pengecoh kurang homogen, mohon diperbaiki.');

        // Minta ganti -> guru mendapat notifikasi.
        $note = $this->teacher->unreadNotifications()->first();
        $this->assertNotNull($note);
        $this->assertSame('minta_ganti', $note->data['kind']);

        // Endpoint: daftar, tandai dibaca; pengguna lain tidak dapat membaca milik orang lain.
        $this->actingAs($this->teacher)->getJson('/notifications')->assertOk()->assertJsonPath('unread', 1);
        $this->actingAs($this->other)->postJson("/notifications/{$note->id}/read")->assertOk();
        $this->assertNull($note->refresh()->read_at);
        $this->actingAs($this->teacher)->postJson("/notifications/{$note->id}/read")->assertOk()->assertJsonPath('unread', 0);
    }

    public function test_deadline_reminders_are_sent_once_per_day(): void
    {
        $this->period->update(['upload_deadline' => today()->addDay()]);
        $this->package(1); // draft milik guru

        $this->artisan('notifications:deadlines')->assertSuccessful();
        $this->artisan('notifications:deadlines')->assertSuccessful();

        $this->assertSame(1, $this->teacher->notifications()->where('data', 'like', '%"kind":"tenggat"%')->count());
    }

    public function test_recap_lists_validator_checklist_and_similarity(): void
    {
        $package = $this->submitted(2);
        $one = $package->items()->where('number', 1)->first();

        app(ApproveItem::class)->handle($one, $this->validator, ['indikator' => true, 'materi' => true, 'bahasa' => false, 'kunci' => true]);

        $data = \App\Services\Export\RecapData::build($package->refresh());

        $this->assertCount(10, $data['rows'][0]['checks']);
        $this->assertTrue($data['rows'][0]['checks']['indikator']);
        $this->assertFalse($data['rows'][0]['checks']['bahasa']);
        $this->assertNull($data['rows'][1]['checks']['indikator']);
        $this->assertTrue($data['rows'][0]['similarity'] === null || is_int($data['rows'][0]['similarity']));
        $this->assertNotEmpty($data['identity']);
    }

    public function test_final_export_is_regenerated_when_document_format_changes(): void
    {
        $package = $this->submitted(1);
        $one = $package->items()->first();
        app(ApproveItem::class)->handle($one, $this->validator);
        $package->refresh();

        $first = app(RequestFinalExport::class)->handle($package);
        $first->refresh()->update(['status' => ExportStatus::Ready, 'format_hash' => \App\Services\Export\PackageDocument::fingerprint()]);
        $this->assertSame($first->id, app(RequestFinalExport::class)->handle($package)->id);

        // Ubah format dokumen (mis. kop) -> permintaan berikutnya membuat naskah baru.
        \App\Support\Settings::set('school_name', 'SMA Negeri 1 Turen (uji)');
        $second = app(RequestFinalExport::class)->handle($package);
        $this->assertNotSame($first->id, $second->id);
    }
}
