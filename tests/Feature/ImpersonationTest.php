<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ganti akun (admin masuk sebagai guru/validator).
 */
class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    private function userWith(RoleName $role, array $attributes = []): User
    {
        $user = User::factory()->create(['must_change_password' => false, 'is_active' => true, ...$attributes]);
        $user->assignRole($role->value);

        return $user;
    }

    public function test_admin_can_enter_a_teacher_account_and_return(): void
    {
        $admin = $this->userWith(RoleName::Admin);
        $teacher = $this->userWith(RoleName::Teacher);

        $this->actingAs($admin)
            ->post("/admin/switch-account/{$teacher->id}", ['role' => 'teacher'])
            ->assertRedirect('/teacher/dashboard');

        $this->assertAuthenticatedAs($teacher);
        $this->get('/teacher/dashboard')->assertOk();
        $this->get('/admin/dashboard')->assertForbidden();

        $this->post('/switch-account/leave')->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin/dashboard')->assertOk();

        $this->assertDatabaseHas('activity_logs', ['event' => 'auth.impersonate.start', 'causer_id' => $admin->id, 'subject_id' => $teacher->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'auth.impersonate.stop', 'causer_id' => $admin->id]);
    }

    public function test_actions_while_impersonating_are_marked_and_password_changes_are_blocked(): void
    {
        $admin = $this->userWith(RoleName::Admin);
        $validator = $this->userWith(RoleName::Validator);

        $this->actingAs($admin)->post("/admin/switch-account/{$validator->id}", ['role' => 'validator'])->assertRedirect('/validator/dashboard');

        $this->put('/profil/password', ['current_password' => 'x', 'password' => 'Baru12345!', 'password_confirmation' => 'Baru12345!'])->assertForbidden();
        $this->put('/profil', ['name' => 'Diubah'])->assertForbidden();

        $this->post('/logout');
        $log = ActivityLog::query()->where('event', 'auth.logout')->latest('id')->first();
        $this->assertSame($admin->id, $log->properties['impersonated_by'] ?? null);
    }

    public function test_only_admins_can_switch_and_only_to_active_non_admin_accounts(): void
    {
        $teacher = $this->userWith(RoleName::Teacher);
        $other = $this->userWith(RoleName::Teacher);
        $admin = $this->userWith(RoleName::Admin);
        $otherAdmin = $this->userWith(RoleName::Admin);
        $inactive = $this->userWith(RoleName::Teacher, ['is_active' => false]);

        $this->actingAs($teacher)->post("/admin/switch-account/{$other->id}", ['role' => 'teacher'])->assertForbidden();
        $this->actingAs($teacher)->get('/admin/switch-account/users')->assertForbidden();

        $this->actingAs($admin)->post("/admin/switch-account/{$otherAdmin->id}", ['role' => 'teacher'])->assertForbidden();
        $this->actingAs($admin)->post("/admin/switch-account/{$inactive->id}", ['role' => 'teacher'])->assertForbidden();
        $this->actingAs($admin)->post("/admin/switch-account/{$other->id}", ['role' => 'validator'])->assertForbidden();
        $this->actingAs($admin)->post("/admin/switch-account/{$other->id}", ['role' => 'admin'])->assertSessionHasErrors('role');
    }

    public function test_search_lists_only_active_teachers_and_validators(): void
    {
        $admin = $this->userWith(RoleName::Admin);
        $this->userWith(RoleName::Teacher, ['name' => 'Budi Guru']);
        $this->userWith(RoleName::Validator, ['name' => 'Budi Validator']);
        $this->userWith(RoleName::Teacher, ['name' => 'Budi Nonaktif', 'is_active' => false]);
        $this->userWith(RoleName::Admin, ['name' => 'Budi Admin']);

        $names = collect($this->actingAs($admin)->getJson('/admin/switch-account/users?q=Budi')->assertOk()->json('data'))->pluck('name')->all();

        $this->assertEqualsCanonicalizing(['Budi Guru', 'Budi Validator'], $names);
    }

    public function test_leave_without_impersonating_is_harmless(): void
    {
        $teacher = $this->userWith(RoleName::Teacher);

        $this->actingAs($teacher)->post('/switch-account/leave')->assertRedirect('/login');
    }

    public function test_review_decisions_and_package_submission_are_blocked_while_impersonating(): void
    {
        foreach (['validator.items.approve', 'validator.items.request-change', 'validator.items.reopen', 'teacher.packages.submit'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route, $name);
            $this->assertContains('not.impersonating', $route->gatherMiddleware(), $name);
        }
    }

    public function test_impersonation_expires_and_returns_to_admin(): void
    {
        $admin = $this->userWith(RoleName::Admin);
        $teacher = $this->userWith(RoleName::Teacher);

        $this->actingAs($admin)->post("/admin/switch-account/{$teacher->id}", ['role' => 'teacher'])->assertRedirect('/teacher/dashboard');

        $this->travel(config('banksoal.impersonation.ttl_minutes') - 1)->minutes();
        $this->get('/teacher/dashboard')->assertOk();

        $this->travel(2)->minutes();
        $this->get('/teacher/dashboard')->assertRedirect('/admin/dashboard');

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(session()->has('impersonator_id'));
        $stop = ActivityLog::query()->where('event', 'auth.impersonate.stop')->latest('id')->first();
        $this->assertSame('expired', $stop->properties['reason'] ?? null);
    }
}
