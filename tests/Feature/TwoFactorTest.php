<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function login(User $user)
    {
        return $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    }

    public function test_staff_login_is_challenged_for_a_second_factor(): void
    {
        $admin = User::factory()->create()->assignRole('super_admin');

        $this->login($admin)->assertRedirect(route('two-factor.challenge'));

        // pending 2FA blocks everything but the challenge and logout
        $this->get('/admin/dashboard')->assertRedirect(route('two-factor.challenge'));
        $this->get('/two-factor')->assertOk();
    }

    public function test_correct_code_completes_the_login(): void
    {
        $admin = User::factory()->create()->assignRole('super_admin');
        $this->login($admin);

        $this->post('/two-factor', ['code' => TwoFactorService::DUMMY_CODE])
            ->assertRedirect(route('dashboard'));

        $this->get('/admin/dashboard')->assertOk();
    }

    public function test_wrong_code_is_rejected(): void
    {
        $admin = User::factory()->create()->assignRole('super_admin');
        $this->login($admin);

        $this->post('/two-factor', ['code' => '9999'])->assertSessionHasErrors('code');

        $this->get('/admin/dashboard')->assertRedirect(route('two-factor.challenge'));
    }

    public function test_applicants_are_not_challenged(): void
    {
        $applicant = User::factory()->create()->assignRole('applicant');

        $this->login($applicant)->assertRedirect(route('dashboard', absolute: false));

        $this->get('/applications/wizard')->assertOk();
    }

    public function test_challenge_page_redirects_away_when_nothing_is_pending(): void
    {
        $applicant = User::factory()->create()->assignRole('applicant');

        $this->actingAs($applicant)->get('/two-factor')->assertRedirect();
    }
}
