<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_applicant_sees_share_application_and_profile()
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $user = User::factory()->create();
        $user->assignRole('applicant');

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Share Application');
        $response->assertSee('Profile');
    }

    public function test_non_applicant_does_not_see_share_application_in_panel_without_permission()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertDontSee('Share Application');
    }
}
