<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_login_is_logged_with_ip_and_browser(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $log = Activity::where('log_name', 'auth')->where('event', 'login')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->causer_id);
        $this->assertNotNull($log->properties['ip']);
        $this->assertArrayHasKey('user_agent', $log->properties->toArray());
    }

    public function test_logout_is_logged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout');

        $log = Activity::where('log_name', 'auth')->where('event', 'logout')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->causer_id);
    }

    public function test_failed_login_is_logged_without_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

        $log = Activity::where('log_name', 'auth')->where('event', 'login_failed')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($user->email, $log->properties['email']);
        $this->assertStringNotContainsString('wrong-password', json_encode($log->properties));
    }

    public function test_admin_logs_page_shows_activity_and_filters_by_log_name(): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        activity('auth')->event('login')->causedBy($admin)->log('User logged in');
        activity('settings')->causedBy($admin)->log('Site settings updated');

        $this->actingAs($admin)->get('/admin/logs?log_name=settings')
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Logs', false)
                ->where('logs.data.0.log_name', 'settings')
            );
    }
}
