<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ApplicantManagement\Models\Applicant;
use Modules\ApplicationManagement\Models\ShareApplication;
use Tests\TestCase;

class InAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_stage_rejection_creates_an_in_app_notification_for_the_applicant(): void
    {
        [$user, $application] = $this->applicationAt(ShareApplication::STATUS_PAYMENT_VERIFIED);
        $reviewer = User::factory()->create()->assignRole('reviewer');

        $this->actingAs($reviewer)
            ->post("/reviewer/applications/{$application->id}/reject", ['rejection_reason' => 'Blurry documents.']);

        $notification = $user->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('Application Rejected', $notification->data['title']);
        $this->assertStringContainsString('Blurry documents.', $notification->data['message']);
    }

    public function test_profile_approval_creates_an_in_app_notification(): void
    {
        [$user, $application] = $this->applicationAt(ShareApplication::STATUS_DRAFT, profileStatus: Applicant::PROFILE_SUBMITTED);
        $approver = User::factory()->create()->assignRole('approver');

        $this->actingAs($approver)->post("/applicants/{$application->applicant_id}/profile/approve");

        $this->assertSame('Profile Approved', $user->notifications()->first()?->data['title']);
    }

    public function test_unread_count_is_shared_with_every_page_and_mark_read_clears_it(): void
    {
        [$user] = $this->applicationAt(ShareApplication::STATUS_DRAFT);

        $applicant = Applicant::where('user_id', $user->id)->firstOrFail();
        $user->notify(new \Modules\ApplicantManagement\Notifications\ProfileApprovedNotification($applicant));

        $this->actingAs($user)->get('/applications/wizard')
            ->assertInertia(fn ($page) => $page->where('notifications.unread_count', 1));

        $this->actingAs($user)->post('/notifications/mark-read');

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    /**
     * @return array{0: User, 1: ShareApplication}
     */
    protected function applicationAt(string $status, string $profileStatus = Applicant::PROFILE_APPROVED): array
    {
        $user = User::factory()->create()->assignRole('applicant');

        $applicant = Applicant::create([
            'user_id' => $user->id,
            'full_name_nepali' => 'परीक्षण',
            'full_name_english' => 'Applicant '.$user->id,
            'date_of_birth' => '1990-01-01',
            'age' => 36,
            'father_name' => 'F',
            'grandfather_name' => 'GF',
            'marital_status' => 'single',
            'permanent_district' => 'KTM',
            'permanent_municipality' => 'KMC',
            'permanent_ward' => '1',
            'mobile_number' => '98000000'.$user->id,
        ]);
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
