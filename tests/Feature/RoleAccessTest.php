<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Matriks akses peran (rules.md §11) untuk rute M0.
 */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    private function userWith(RoleName ...$roles): User
    {
        $user = User::factory()->create(['must_change_password' => false, 'is_active' => true]);
        $user->assignRole(array_map(static fn (RoleName $r): string => $r->value, $roles));

        return $user;
    }

    /**
     * @return list<string>
     */
    private function adminUrls(): array
    {
        return [
            '/admin/dashboard', '/admin/academic-years', '/admin/semesters', '/admin/periods',
            '/admin/subjects', '/admin/subjects/import', '/admin/grade-levels', '/admin/teachers', '/admin/teachers/import',
            '/admin/validators', '/admin/assignments', '/admin/settings', '/admin/activity-logs',
        ];
    }

    public function test_admin_can_open_every_admin_page(): void
    {
        $admin = $this->userWith(RoleName::Admin);

        foreach ($this->adminUrls() as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_teacher_and_validator_cannot_open_admin_pages(): void
    {
        foreach ([RoleName::Teacher, RoleName::Validator] as $role) {
            $user = $this->userWith($role);

            foreach ($this->adminUrls() as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
        }
    }

    public function test_teacher_cannot_manage_accounts(): void
    {
        $teacher = $this->userWith(RoleName::Teacher);
        $target = $this->userWith(RoleName::Teacher);

        $this->actingAs($teacher)->post("/admin/validators/{$target->id}/toggle")->assertForbidden();
        $this->actingAs($teacher)->post("/admin/teachers/{$target->id}/reset-password")->assertForbidden();
        $this->actingAs($teacher)->delete("/admin/teachers/{$target->id}")->assertForbidden();

        $this->assertFalse($target->fresh()->hasRole(RoleName::Validator->value));
    }

    public function test_each_role_lands_on_its_own_dashboard(): void
    {
        $this->actingAs($this->userWith(RoleName::Teacher))->get('/teacher/dashboard')->assertOk();
        $this->actingAs($this->userWith(RoleName::Validator))->get('/validator/dashboard')->assertOk();
        $this->actingAs($this->userWith(RoleName::Admin))->get('/admin/dashboard')->assertOk();
    }

    public function test_dual_role_user_can_switch_role(): void
    {
        $user = $this->userWith(RoleName::Teacher, RoleName::Validator);

        // Peran bawaan = prioritas tertinggi (validator).
        $this->actingAs($user)->get('/validator/dashboard')->assertOk();
        $this->actingAs($user)->get('/teacher/dashboard')->assertForbidden();

        $this->actingAs($user)->post('/role/switch', ['role' => 'teacher'])->assertRedirect('/teacher/dashboard');

        $this->get('/teacher/dashboard')->assertOk();
        $this->get('/validator/dashboard')->assertForbidden();
    }

    public function test_user_cannot_switch_to_a_role_they_do_not_have(): void
    {
        $teacher = $this->userWith(RoleName::Teacher);

        $this->actingAs($teacher)->post('/role/switch', ['role' => 'admin'])->assertForbidden();
        $this->get('/admin/dashboard')->assertForbidden();
    }
}
