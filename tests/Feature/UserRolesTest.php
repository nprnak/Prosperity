<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The seeder states plainly that a user may hold several roles, and
 * WorkflowService::assertMayAct exists to stop one person taking two stages of
 * the same record. Both were dead letters while the admin form could only ever
 * assign one role, and silently stripped the rest on every edit.
 */
class UserRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_a_user_can_be_created_holding_several_roles(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/users', [
                'name' => 'Bimala Shrestha',
                'email' => 'bimala@example.com',
                'password' => 'a-long-enough-secret',
                'roles' => ['finance_staff', 'application_verifier'],
            ])
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'bimala@example.com')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            ['finance_staff', 'application_verifier'],
            $user->getRoleNames()->all(),
        );
    }

    public function test_editing_a_user_keeps_the_roles_submitted(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['finance_staff', 'application_verifier']);

        $this->actingAs($this->admin())
            ->patch("/admin/users/{$user->id}", [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => ['finance_staff', 'application_verifier', 'application_reviewer'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['finance_staff', 'application_verifier', 'application_reviewer'],
            $user->fresh()->getRoleNames()->all(),
        );
    }

    public function test_at_least_one_real_role_is_required(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/users', [
                'name' => 'No Role',
                'email' => 'norole@example.com',
                'password' => 'a-long-enough-secret',
                'roles' => [],
            ])
            ->assertSessionHasErrors('roles');

        $this->actingAs($this->admin())
            ->post('/admin/users', [
                'name' => 'Bad Role',
                'email' => 'badrole@example.com',
                'password' => 'a-long-enough-secret',
                'roles' => ['not_a_role'],
            ])
            ->assertSessionHasErrors('roles.0');
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole('super_admin');
    }
}
