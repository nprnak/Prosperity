<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicantManagement\Notifications\ProfileApprovedNotification;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApprovalManagement\Notifications\ApplicationStageProgressedNotification;
use Modules\ApprovalManagement\Notifications\ApplicationReturnedNotification;
use Modules\PaymentManagement\Notifications\PaymentVerifiedNotification;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

class InAppNotificationTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_stage_rejection_creates_an_in_app_notification_for_the_applicant(): void
    {
        [$user, $application] = $this->applicationAt(ApplicationStatus::PaymentVerified);
        $verifier = User::factory()->create()->assignRole('application_verifier');

        $this->actingAs($verifier)
            ->post("/verifier/applications/{$application->id}/act",
                ['action' => 'return_to_applicant', 'remarks' => 'Blurry documents.']);

        $notification = $user->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('Application Returned', $notification->data['title']);
        $this->assertStringContainsString('Blurry documents.', $notification->data['message']);
    }

    public function test_verifier_and_reviewer_approvals_notify_the_applicant_of_stage_progress(): void
    {
        [$user, $application] = $this->applicationAt(ApplicationStatus::PaymentVerified);
        $verifier = User::factory()->create()->assignRole('application_verifier');
        $reviewer = User::factory()->create()->assignRole('application_reviewer');

        $this->actingAs($verifier)
            ->post("/verifier/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Verified.'])
            ->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame(ApplicationStatus::Verified, $application->status);

        $this->actingAs($reviewer)
            ->post("/reviewer/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Reviewed.'])
            ->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame(ApplicationStatus::Reviewed, $application->status);

        $this->assertSame(2, $user->notifications()->count());
        $this->assertCount(2, $user->notifications->where('type', ApplicationStageProgressedNotification::class));
    }

    public function test_profile_approval_creates_an_in_app_notification(): void
    {
        [$user, $application] = $this->applicationAt(ApplicationStatus::Draft, profileStatus: ProfileStatus::Submitted);
        // KYC approval is the third stage, so walk all three.
        foreach ([
            'profile_verifier' => 'Documents legible.',
            'profile_reviewer' => 'Details match.',
            'profile_approver' => 'Approved.',
        ] as $role => $remarks) {
            $this->actingAs(User::factory()->create()->assignRole($role))
                ->post("/applicants/{$application->applicant_id}/profile/act",
                    ['action' => 'approve', 'remarks' => $remarks]);
        }

        $this->assertSame('Profile Approved', $user->notifications()->first()?->data['title']);
    }

    public function test_unread_count_is_shared_with_every_page_and_mark_read_clears_it(): void
    {
        [$user] = $this->applicationAt(ApplicationStatus::Draft);

        $applicant = Profile::where('user_id', $user->id)->firstOrFail();
        $user->notify(new ProfileApprovedNotification($applicant));

        $this->actingAs($user)->get('/applications/wizard')
            ->assertInertia(fn ($page) => $page->where('notifications.unread_count', 1));

        $this->actingAs($user)->post('/notifications/mark-read');

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    /**
     * @return array{0: User, 1: ShareApplication}
     */
    /**
     * The status is an enum, so interpolating it straight into a mail line
     * throws and the queued job dies in failed_jobs — the applicant simply
     * never hears that their payment cleared.
     */
    public function test_payment_verified_mail_renders_the_status_label(): void
    {
        [$user, $application] = $this->applicationAt(ApplicationStatus::PaymentVerified);

        $mail = (new PaymentVerifiedNotification($application))->toMail($user);

        $this->assertContains('Status: Awaiting Verification', $mail->introLines);
    }

    /**
     * Returning an application is a request for correction, not a refusal —
     * there is no terminal rejection in the workflow at all.
     */
    public function test_returned_notification_does_not_call_the_application_rejected(): void
    {
        [$user, $application] = $this->applicationAt(ApplicationStatus::Returned);
        $application->forceFill(['rejection_reason' => 'Blurry documents.'])->save();

        $notification = new ApplicationReturnedNotification($application);

        $this->assertStringNotContainsStringIgnoringCase(
            'reject', $notification->toArray($user)['message']
        );
        $this->assertStringNotContainsStringIgnoringCase(
            'reject', implode(' ', $notification->toMail($user)->introLines)
        );
    }

    protected function applicationAt(ApplicationStatus $status, ProfileStatus $profileStatus = ProfileStatus::Approved): array
    {
        $user = User::factory()->create()->assignRole('applicant');

        $applicant = $this->minimalProfile($user);
        $applicant->forceFill(['profile_status' => $profileStatus])->save();

        $application = ShareApplication::create([
            'applicant_id' => $applicant->id,
            'application_number' => 'APP-TEST-'.$user->id,
            'status' => $status,
            'shares_applied' => 10,
            'amount_per_share' => '100.00',
            'total_amount_declared' => '1000.00',
        ]);

        return [$user, $application];
    }
}
