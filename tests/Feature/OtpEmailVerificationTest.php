<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OtpEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function unverifiedUserWithOtp(string $otp = '123456', int $expiresInMinutes = 10): User
    {
        $user = User::factory()->unverified()->create()->assignRole('applicant');

        $user->forceFill([
            'email_otp_code' => Hash::make($otp),
            'email_otp_expires_at' => now()->addMinutes($expiresInMinutes),
        ])->save();

        return $user;
    }

    public function test_correct_otp_verifies_the_email(): void
    {
        $user = $this->unverifiedUserWithOtp('654321');

        $this->actingAs($user)
            ->post('/verify-email/otp', ['code' => '654321'])
            ->assertRedirect(route('dashboard').'?verified=1');

        $user->refresh();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNull($user->email_otp_code);
        $this->assertNull($user->email_otp_expires_at);
    }

    public function test_wrong_otp_is_rejected(): void
    {
        $user = $this->unverifiedUserWithOtp('654321');

        $this->actingAs($user)
            ->post('/verify-email/otp', ['code' => '111111'])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_expired_otp_is_rejected(): void
    {
        $user = $this->unverifiedUserWithOtp('654321', expiresInMinutes: -1);

        $this->actingAs($user)
            ->post('/verify-email/otp', ['code' => '654321'])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resending_issues_a_fresh_otp(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create()->assignRole('applicant');
        $this->assertNull($user->email_otp_code);

        $this->actingAs($user)->post('/email/verification-notification');

        $user->refresh();
        $this->assertNotNull($user->email_otp_code);
        $this->assertTrue($user->email_otp_expires_at->isFuture());
        Notification::assertSentTo($user, \App\Notifications\VerifyEmailWithOtp::class);
    }

    public function test_otp_fields_are_never_serialized(): void
    {
        $user = $this->unverifiedUserWithOtp();

        $this->assertArrayNotHasKey('email_otp_code', $user->toArray());
        $this->assertArrayNotHasKey('email_otp_expires_at', $user->toArray());
    }

    public function test_signed_link_verification_still_works(): void
    {
        $user = User::factory()->unverified()->create()->assignRole('applicant');

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)->get($url);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
