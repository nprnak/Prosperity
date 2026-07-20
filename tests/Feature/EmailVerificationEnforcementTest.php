<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailWithOtp;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_unverified_user_is_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create()->assignRole('applicant');

        foreach (['/applications/wizard', '/profile', '/dashboard'] as $uri) {
            $this->actingAs($user)->get($uri)->assertRedirect(route('verification.notice'));
        }
    }

    public function test_verified_user_passes_through(): void
    {
        $user = User::factory()->create()->assignRole('applicant');

        $this->actingAs($user)->get('/applications/wizard')->assertOk();
    }

    public function test_registration_sends_verification_email(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'new-user@example.com')->firstOrFail();
        Notification::assertSentTo(
            $user,
            VerifyEmailWithOtp::class,
        );
    }
}
