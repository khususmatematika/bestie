<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_sent(): void
    {
        $this->withoutVite();

        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy');
        $response->assertHeader('Permissions-Policy');
    }

    public function test_create_admin_command_makes_admin_without_env(): void
    {
        $this->seed(RoleSeeder::class);

        $this->artisan('bestie:create-admin')
            ->expectsQuestion('Email admin', 'Kepala@Sekolah.test')
            ->expectsQuestion('Nama admin', 'Panitia')
            ->expectsQuestion('Kata sandi (minimal 10 karakter)', 'RahasiaKu123')
            ->expectsQuestion('Ulangi kata sandi', 'RahasiaKu123')
            ->assertSuccessful();

        $admin = User::query()->where('email', 'kepala@sekolah.test')->firstOrFail();
        $this->assertTrue($admin->hasRole(RoleName::Admin->value));
        $this->assertTrue(password_verify('RahasiaKu123', $admin->password));
    }

    public function test_create_admin_rejects_existing_email_without_reset(): void
    {
        $this->seed(RoleSeeder::class);
        User::factory()->create(['email' => 'a@sekolah.test']);

        $this->artisan('bestie:create-admin')
            ->expectsQuestion('Email admin', 'a@sekolah.test')
            ->assertFailed();
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $this->withoutVite();
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['must_change_password' => false, 'is_active' => true]);
        $user->assignRole(RoleName::Teacher->value);

        $this->actingAs($user)->get('/teacher/dashboard')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/teacher/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }
}
