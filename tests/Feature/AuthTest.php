<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    private function makeUser(RoleName $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'password' => 'Rahasia12345',
            'must_change_password' => false,
            'is_active' => true,
        ], $attributes));

        $user->assignRole($role->value);

        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
    }

    public function test_user_can_login_with_email(): void
    {
        $user = $this->makeUser(RoleName::Admin);

        $this->post('/login', ['identifier' => $user->email, 'password' => 'Rahasia12345'])
            ->assertRedirect('/admin/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_with_nip(): void
    {
        $user = $this->makeUser(RoleName::Teacher, ['nip' => '198001012005011001']);

        $this->post('/login', ['identifier' => '198001012005011001', 'password' => 'Rahasia12345'])
            ->assertRedirect('/teacher/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = $this->makeUser(RoleName::Teacher);

        $this->post('/login', ['identifier' => $user->email, 'password' => 'salah'])
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_inactive_account_cannot_login(): void
    {
        $user = $this->makeUser(RoleName::Teacher, ['is_active' => false]);

        $this->post('/login', ['identifier' => $user->email, 'password' => 'Rahasia12345'])
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $user = $this->makeUser(RoleName::Teacher);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['identifier' => $user->email, 'password' => 'salah']);
        }

        // Percobaan ke-6 ditolak walau kata sandinya benar.
        $this->post('/login', ['identifier' => $user->email, 'password' => 'Rahasia12345'])
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_user_with_initial_password_is_forced_to_change_it(): void
    {
        $user = $this->makeUser(RoleName::Teacher, ['must_change_password' => true]);

        $this->post('/login', ['identifier' => $user->email, 'password' => 'Rahasia12345'])
            ->assertRedirect('/ganti-password');

        $this->get('/teacher/dashboard')->assertRedirect('/ganti-password');

        $this->put('/ganti-password', [
            'current_password' => 'Rahasia12345',
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])->assertRedirect('/teacher/dashboard');

        $this->assertFalse($user->fresh()->must_change_password);
        $this->get('/teacher/dashboard')->assertOk();
    }

    public function test_weak_new_password_is_rejected(): void
    {
        $user = $this->makeUser(RoleName::Teacher, ['must_change_password' => true]);

        $this->actingAs($user)->put('/ganti-password', [
            'current_password' => 'Rahasia12345',
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }
}
