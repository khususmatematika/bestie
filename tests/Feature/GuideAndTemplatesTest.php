<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Imports\BlueprintRowsImport;
use App\Models\User;
use App\Services\Documents\BlueprintRowsParser;
use App\Services\Documents\BlueprintTemplateWorkbook;
use App\Services\Documents\NaskahReader;
use App\Services\Documents\NaskahTemplateDocument;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class GuideAndTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    private function userWith(RoleName $role): User
    {
        $user = User::factory()->create(['must_change_password' => false, 'is_active' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    public function test_every_role_can_open_the_guide_and_download_templates(): void
    {
        foreach ([RoleName::Admin, RoleName::Validator, RoleName::Teacher] as $role) {
            $user = $this->userWith($role);

            $this->actingAs($user)->get('/panduan')->assertOk();
            $this->actingAs($user)->get('/templates/naskah')->assertOk();
            $this->actingAs($user)->get('/templates/kisi-kisi')->assertOk();
        }

        auth()->logout();
        $this->get('/panduan')->assertRedirect('/login');
    }

    public function test_naskah_template_is_read_back_without_warnings(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tn').'.docx';
        app(NaskahTemplateDocument::class)->build($path);

        $read = app(NaskahReader::class)->read($path, 'docx');

        $this->assertSame([], $read['warnings']);
        $this->assertSame(
            ['pg', 'pg', 'pg_kompleks', 'benar_salah', 'benar_salah', 'menjodohkan', 'isian', 'uraian'],
            array_values(array_map(static fn (array $i): string => $i['type'], $read['items'])),
        );
        $this->assertSame('C', $read['items'][1]['answer_key']);
        $this->assertSame('B,D', $read['items'][3]['answer_key']);
        $this->assertSame('B', $read['items'][4]['answer_key']);
        $this->assertSame("A=Jawa Timur; B=Jawa Barat; C=Jawa Tengah; D=Bali; E=Nusa Tenggara Barat\nPengecoh: Sulawesi Selatan; Kalimantan Timur", $read['items'][6]['answer_key']);
        $this->assertSame('S', $read['items'][5]['answer_key']);

        @unlink($path);
    }

    public function test_blueprint_template_matches_the_naskah_template(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tk').'.xlsx';
        app(BlueprintTemplateWorkbook::class)->build($path);

        $rows = Excel::toArray(new BlueprintRowsImport, $path)[0];
        $parsed = app(BlueprintRowsParser::class)->parse($rows);

        $this->assertSame([], $parsed['warnings']);
        $this->assertSame(range(1, 8), array_keys($parsed['rows']));
        $this->assertSame(['pg', 'pg', 'pg_kompleks', 'benar_salah', 'benar_salah', 'menjodohkan', 'isian', 'uraian'], array_values(array_map(static fn (array $r): ?string => $r['type'], $parsed['rows'])));
        // Sel digabung diwariskan ke baris di bawahnya.
        $this->assertSame($parsed['rows'][1]['blueprint']['cp'], $parsed['rows'][7]['blueprint']['cp']);
        $this->assertNotSame($parsed['rows'][1]['blueprint']['cp'], $parsed['rows'][8]['blueprint']['cp']);

        @unlink($path);
    }

    public function test_word_blueprint_template_is_read_back(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kw').'.docx';
        app(\App\Services\Documents\BlueprintTemplateDocument::class)->build($path);

        $rows = \App\Services\Documents\DocumentRows::fromTables((new \App\Services\Documents\DocxTextReader)->read($path)['tables']);
        $parsed = app(BlueprintRowsParser::class)->parse($rows);

        $this->assertSame([], $parsed['warnings']);
        $this->assertSame(range(1, 8), array_keys($parsed['rows']));
        $this->assertSame('menjodohkan', $parsed['rows'][6]['type']);

        $user = $this->userWith(RoleName::Teacher);
        $this->actingAs($user)->get('/templates/kisi-kisi-word')->assertOk();

        @unlink($path);
    }
}
