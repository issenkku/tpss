<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user with two roles
        $this->user = User::create([
            'username' => 'multirole',
            'name' => 'Multi Role User',
            'email' => 'multi@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        // Role 1: Staff (Primary)
        UserRole::create([
            'user_id' => $this->user->id,
            'role' => 'staff',
            'is_primary' => true,
        ]);

        // Role 2: Instructor
        UserRole::create([
            'user_id' => $this->user->id,
            'role' => 'instructor',
            'is_primary' => false,
        ]);
    }

    public function test_user_can_switch_role(): void
    {
        $csrfToken = 'valid-test-csrf-token';

        $this
            ->withSession([
                'active_role' => 'staff',
                '_token' => $csrfToken,
            ])
            ->actingAs($this->user);

        $response = $this->post('/switch-role', [
            '_token' => $csrfToken,
            'role' => 'instructor',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertEquals('instructor', session('active_role'));

        $this->get('/dashboard')->assertRedirect(route('lecturer.dashboard'));
    }

    public function test_user_cannot_switch_to_unauthorized_role(): void
    {
        $csrfToken = 'valid-test-csrf-token';

        $this
            ->withSession([
                'active_role' => 'staff',
                '_token' => $csrfToken,
            ])
            ->actingAs($this->user);

        // Try switching to admin (which user doesn't have)
        $response = $this->post('/switch-role', [
            '_token' => $csrfToken,
            'role' => 'admin',
        ]);

        $response->assertRedirect('/dashboard');
        // Role should still be staff
        $this->assertEquals('staff', session('active_role'));

        $this->get('/dashboard')->assertRedirect(route('staff.settings'));
    }

    public function test_dashboard_hub_avoids_unreleased_role_dashboards(): void
    {
        $this
            ->withSession(['active_role' => 'staff'])
            ->actingAs($this->user);

        $this->get('/dashboard')->assertRedirect(route('staff.settings'));

        $this->withSession(['active_role' => 'instructor']);
        $this->get('/dashboard')->assertRedirect(route('lecturer.dashboard'));

        UserRole::create([
            'user_id' => $this->user->id,
            'role' => 'course_head',
            'is_primary' => false,
        ]);

        $this->withSession(['active_role' => 'course_head']);
        $this->get('/dashboard')->assertRedirect(route('maker.schedules.index'));

        UserRole::create([
            'user_id' => $this->user->id,
            'role' => 'executive',
            'is_primary' => false,
        ]);

        $this->withSession(['active_role' => 'executive']);
        $this->get('/dashboard')->assertRedirect(route('approver.dashboard'));
    }

    public function test_deep_link_does_not_auto_switch_to_allowed_route_role(): void
    {
        UserRole::create([
            'user_id' => $this->user->id,
            'role' => 'course_head',
            'is_primary' => false,
        ]);

        $this
            ->withSession(['active_role' => 'staff'])
            ->actingAs($this->user);

        $response = $this->get(route('maker.course_offerings.index'));

        $response->assertRedirect(route('dashboard'));
        $this->assertEquals('staff', session('active_role'));
    }
}
