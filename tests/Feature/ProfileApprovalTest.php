<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\ApplicantManagement\Models\Applicant;
use Modules\ApplicantManagement\Notifications\ProfileApprovedNotification;
use Modules\ApplicantManagement\Notifications\ProfileRejectedNotification;
use Tests\TestCase;

class ProfileApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function applicantUser(): User
    {
        return User::factory()->create()->assignRole('applicant');
    }

    protected function completeProfileFor(User $user): Applicant
    {
        return Applicant::create([
            'user_id' => $user->id,
            'full_name_nepali' => 'परीक्षण',
            'full_name_english' => 'Test Applicant '.$user->id,
            'date_of_birth' => '1990-01-01',
            'age' => 36,
            'father_name' => 'Father',
            'grandfather_name' => 'Grandfather',
            'education' => 'Bachelors',
            'marital_status' => 'single',
            'permanent_district' => 'Kathmandu',
            'permanent_municipality' => 'KMC',
            'permanent_ward' => '1',
            'mobile_number' => '98000000'.$user->id,
            'photo_path' => 'docs/photo.jpg',
            'citizenship_doc_path' => 'docs/citizenship.jpg',
            'national_id_doc_path' => 'docs/nid.jpg',
            'pan_doc_path' => 'docs/pan.jpg',
            'boid' => '130100000000'.$user->id,
            'crn_number' => 'CRN'.$user->id,
            'bank_name' => 'Test Bank',
            'bank_branch' => 'Kathmandu',
            'bank_account_number' => '0123456789'.$user->id,
            'account_holder_name' => 'Test Applicant '.$user->id,
            'asba_consent' => true,
        ]);
    }

    public function test_complete_profile_can_be_submitted_for_review(): void
    {
        $user = $this->applicantUser();
        $this->completeProfileFor($user);

        $this->actingAs($user)->post('/profile/submit')->assertSessionHasNoErrors();

        $this->assertSame(Applicant::PROFILE_SUBMITTED, Applicant::where('user_id', $user->id)->value('profile_status'));
    }

    public function test_incomplete_profile_cannot_be_submitted(): void
    {
        $user = $this->applicantUser();
        $applicant = $this->completeProfileFor($user);
        $applicant->forceFill(['boid' => null])->save();

        $this->actingAs($user)->post('/profile/submit')->assertSessionHasErrors('profile');

        $this->assertSame(Applicant::PROFILE_DRAFT, $applicant->fresh()->profile_status);
    }

    public function test_reviewer_can_approve_a_submitted_profile_and_applicant_is_notified(): void
    {
        Notification::fake();

        $user = $this->applicantUser();
        $applicant = $this->completeProfileFor($user);
        $this->actingAs($user)->post('/profile/submit');

        $reviewer = User::factory()->create()->assignRole('approver');

        $this->actingAs($reviewer)
            ->post("/applicants/{$applicant->id}/profile/approve")
            ->assertSessionHasNoErrors();

        $applicant->refresh();
        $this->assertSame(Applicant::PROFILE_APPROVED, $applicant->profile_status);
        $this->assertSame($reviewer->id, $applicant->profile_reviewed_by);
        Notification::assertSentTo($user, ProfileApprovedNotification::class);
    }

    public function test_reviewer_can_reject_with_reason_and_applicant_can_resubmit(): void
    {
        Notification::fake();

        $user = $this->applicantUser();
        $applicant = $this->completeProfileFor($user);
        $this->actingAs($user)->post('/profile/submit');

        $reviewer = User::factory()->create()->assignRole('approver');

        $this->actingAs($reviewer)
            ->post("/applicants/{$applicant->id}/profile/reject", ['rejection_reason' => 'Citizenship scan unreadable'])
            ->assertSessionHasNoErrors();

        $applicant->refresh();
        $this->assertSame(Applicant::PROFILE_REJECTED, $applicant->profile_status);
        $this->assertSame('Citizenship scan unreadable', $applicant->profile_rejection_reason);
        Notification::assertSentTo($user, ProfileRejectedNotification::class);

        // rejected profiles may be resubmitted
        $this->actingAs($user)->post('/profile/submit')->assertSessionHasNoErrors();
        $this->assertSame(Applicant::PROFILE_SUBMITTED, $applicant->fresh()->profile_status);
    }

    public function test_applicant_cannot_access_review_queue_or_review_actions(): void
    {
        $user = $this->applicantUser();
        $applicant = $this->completeProfileFor($user);
        $this->actingAs($user)->post('/profile/submit');

        $this->actingAs($user)->get('/applicants/review')->assertForbidden();
        $this->actingAs($user)->post("/applicants/{$applicant->id}/profile/approve")->assertForbidden();
    }

    public function test_wizard_draft_is_blocked_until_profile_approved(): void
    {
        $user = $this->applicantUser();
        $applicant = $this->completeProfileFor($user);

        $payload = ['payload' => [
            'issue_code' => 'PHL-IPO-1',
            'shares_applied' => 10,
            'amount_per_share' => '100.00',
            'total_amount_declared' => '1000.00',
        ]];

        $this->actingAs($user)->post('/applications/draft', $payload)->assertSessionHasErrors('profile');

        $applicant->forceFill(['profile_status' => Applicant::PROFILE_APPROVED])->save();

        $this->actingAs($user)->post('/applications/draft', $payload)->assertSessionHasNoErrors();
    }
}
