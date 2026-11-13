<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ActivityLogUserFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    private function make(RoleName $role): User
    {
        $user = User::factory()->create(['must_change_password' => false, 'is_active' => true]);
        $user->assignRole($role->value);

        return $user;
    }

    public function test_log_can_be_filtered_per_user_and_lists_users(): void
    {
        $admin = $this->make(RoleName::Admin);
        $other = $this->make(RoleName::Teacher);

        ActivityLog::create(['event' => 'a.one', 'causer_id' => $admin->id]);
        ActivityLog::create(['event' => 'b.one', 'causer_id' => $other->id]);
        ActivityLog::create(['event' => 'b.two', 'causer_id' => $other->id]);

        $this->actingAs($admin)->get('/admin/activity-logs?user='.$other->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Logs')
                ->has('logs.data', 2)
                ->has('users', 2)
                ->where('filters.user', (string) $other->id));
    }

    public function test_index_without_user_only_returns_summary(): void
    {
        $admin = $this->make(RoleName::Admin);
        ActivityLog::create(['event' => 'a.one', 'causer_id' => $admin->id]);

        $this->actingAs($admin)->get('/admin/activity-logs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('logs', null)->has('users', 1)->where('users.0.last_event', 'a.one'));
    }
}
