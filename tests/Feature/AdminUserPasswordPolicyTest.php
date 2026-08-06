<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserPasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_super_admin_cannot_set_password_on_create()
    {
        // seed roles and permissions then create roles and a non-super-admin user
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $creator = User::factory()->create(['email_verified_at' => now()]);
        $creator->assignRole('profile_verifier');
        // grant the manage permission so the creator can reach the admin route in tests
        $creator->givePermissionTo('user.manage');

        $this->actingAs($creator);

        $response = $this->post(route('admin.users.store'), [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => 'Secret123!',
            'roles' => ['applicant'],
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    public function test_super_admin_can_create_with_password()
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('super_admin');
        $admin->givePermissionTo('user.manage');

        $this->actingAs($admin);

        $response = $this->post(route('admin.users.store'), [
            'name' => 'Test User 2',
            'email' => 'testuser2@example.com',
            'password' => 'Secret123!',
            'roles' => ['applicant'],
        ]);

        $response->assertRedirect(route('admin.users'));
        $this->assertDatabaseHas('users', ['email' => 'testuser2@example.com']);
    }
}
