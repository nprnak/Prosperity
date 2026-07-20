<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicantManagement\Notifications\ProfileApprovedNotification;
use Modules\ApplicantManagement\Notifications\ProfileReturnedNotification;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\CompanyManagement\Models\Company;
use Modules\CompanyManagement\Models\ShareOffering;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

class ProfileApprovalTest extends TestCase
{
    use CreatesProfiles;
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

    /** Walk a submitted profile through the stages preceding $upTo. */
    protected function advanceTo(Profile $profile, string $upTo): void
    {
        $stages = [
            'profile_verifier' => 'Documents legible.',
            'profile_reviewer' => 'Details match documents.',
        ];

        foreach ($stages as $role => $remarks) {
            if ($role === $upTo) {
                return;
            }

            $this->actingAs(User::factory()->create()->assignRole($role))
                ->post("/applicants/{$profile->id}/profile/act",
                    ['action' => 'approve', 'remarks' => $remarks]);
        }
    }

    public function test_complete_profile_can_be_submitted_for_review(): void
    {
        $user = $this->applicantUser();
        $this->completeProfile($user);

        $this->actingAs($user)->post('/profile/submit')->assertSessionHasNoErrors();

        $this->assertSame(ProfileStatus::Submitted, Profile::where('user_id', $user->id)->value('profile_status'));
    }

    public function test_incomplete_profile_cannot_be_submitted(): void
    {
        $user = $this->applicantUser();
        $applicant = $this->completeProfile($user);
        $applicant->forceFill(['boid' => null])->save();

        $this->actingAs($user)->post('/profile/submit')->assertSessionHasErrors('profile');

        $this->assertSame(ProfileStatus::Incomplete, $applicant->fresh()->profile_status);
    }

    public function test_kyc_requires_all_three_stages_and_three_people(): void
    {
        Notification::fake();

        $user = $this->applicantUser();
        $applicant = $this->completeProfile($user);
        $this->actingAs($user)->post('/profile/submit');

        $verifier = User::factory()->create()->assignRole('profile_verifier');
        $reviewer = User::factory()->create()->assignRole('profile_reviewer');
        $approver = User::factory()->create()->assignRole('profile_approver');

        $this->actingAs($verifier)
            ->post("/applicants/{$applicant->id}/profile/act", ['action' => 'approve', 'remarks' => 'Documents legible.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(ProfileStatus::Verified, $applicant->fresh()->profile_status);

        $this->actingAs($reviewer)
            ->post("/applicants/{$applicant->id}/profile/act", ['action' => 'approve', 'remarks' => 'Details match.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(ProfileStatus::Reviewed, $applicant->fresh()->profile_status);

        $this->actingAs($approver)
            ->post("/applicants/{$applicant->id}/profile/act", ['action' => 'approve', 'remarks' => 'Approved.'])
            ->assertSessionHasNoErrors();

        $applicant->refresh();
        $this->assertSame(ProfileStatus::Approved, $applicant->profile_status);
        Notification::assertSentTo($user, ProfileApprovedNotification::class);

        $this->assertSame(3, $applicant->workflowEvents()->distinct()->count('actor_id'));
    }

    public function test_approver_cannot_approve_a_profile_that_skipped_earlier_stages(): void
    {
        $user = $this->applicantUser();
        $applicant = $this->completeProfile($user);
        $this->actingAs($user)->post('/profile/submit');

        $this->actingAs(User::factory()->create()->assignRole('profile_approver'))
            ->post("/applicants/{$applicant->id}/profile/act", ['action' => 'approve', 'remarks' => 'Straight through.'])
            ->assertSessionHasErrors('workflow');

        $this->assertSame(ProfileStatus::Submitted, $applicant->fresh()->profile_status);
    }

    public function test_returned_profile_goes_back_to_the_applicant_and_restarts_the_chain(): void
    {
        Notification::fake();

        $user = $this->applicantUser();
        $applicant = $this->completeProfile($user);
        $this->actingAs($user)->post('/profile/submit');

        $verifier = User::factory()->create()->assignRole('profile_verifier');

        $this->actingAs($verifier)
            ->post("/applicants/{$applicant->id}/profile/act",
                ['action' => 'return_to_applicant', 'remarks' => 'Citizenship scan unreadable.'])
            ->assertSessionHasNoErrors();

        $applicant->refresh();
        $this->assertSame(ProfileStatus::Returned, $applicant->profile_status);
        Notification::assertSentTo($user, ProfileReturnedNotification::class);

        // resubmitting starts a fresh cycle, so three signatures are needed again
        $this->actingAs($user)->post('/profile/submit')->assertSessionHasNoErrors();
        $applicant->refresh();
        $this->assertSame(ProfileStatus::Submitted, $applicant->profile_status);
        $this->assertSame(2, $applicant->workflow_cycle);
        $this->assertSame([], $applicant->stagesActedByUser($verifier->id));
    }

    public function test_applicant_cannot_edit_a_profile_that_is_with_the_review_team(): void
    {
        $user = $this->applicantUser();
        $applicant = $this->completeProfile($user);
        $this->actingAs($user)->post('/profile/submit');

        $this->actingAs($user)
            ->patch('/profile/applicant', ['full_name_np' => 'बदलिएको नाम'])
            ->assertSessionHasErrors();

        $this->assertSame(ProfileStatus::Submitted, $applicant->fresh()->profile_status);
    }

    public function test_wizard_reports_an_application_that_is_under_review(): void
    {
        $user = $this->applicantUser();
        $profile = $this->approvedProfile($user);

        $application = ShareApplication::create([
            'applicant_id' => $profile->id,
            'application_number' => 'APP-REVIEW-'.$user->id,
            'status' => ApplicationStatus::PaymentVerified,
            'shares_applied' => 10,
            'amount_per_share' => '100.00',
            'total_amount_declared' => '1000.00',
        ]);

        $this->actingAs($user)->get('/applications/wizard')
            ->assertInertia(fn ($page) => $page
                ->component('Applications/Wizard', false)
                ->where('activeApplication.id', $application->id)
                ->where('activeApplication.status_label', 'Awaiting Verification')
            );
    }

    public function test_applicant_cannot_access_review_queue_or_review_actions(): void
    {
        $user = $this->applicantUser();
        $applicant = $this->completeProfile($user);
        $this->actingAs($user)->post('/profile/submit');

        $this->actingAs($user)->get('/applicants/review')->assertForbidden();
        $this->actingAs($user)
            ->post("/applicants/{$applicant->id}/profile/act", ['action' => 'approve', 'remarks' => 'Mine.'])
            ->assertForbidden();
    }

    public function test_wizard_draft_is_blocked_until_profile_approved(): void
    {
        $user = $this->applicantUser();
        $applicant = $this->completeProfile($user);

        $company = Company::create([
            'name' => 'Prosperity Holdings', 'code' => 'PHL', 'status' => 'active',
        ]);
        $offering = $company->offerings()->create([
            'title' => 'IPO', 'fiscal_year' => '2082/83', 'total_shares' => 100000,
            'share_rate' => '100.00', 'min_shares' => 10, 'max_shares' => 1000,
            'status' => ShareOffering::STATUS_OPEN,
        ]);

        $payload = ['payload' => [
            'share_offering_id' => $offering->id,
            'shares_applied' => 10,
        ]];

        $this->actingAs($user)->post('/applications/draft', $payload)->assertSessionHasErrors('profile');

        $applicant->forceFill(['profile_status' => ProfileStatus::Approved])->save();

        $this->actingAs($user)->post('/applications/draft', $payload)->assertSessionHasNoErrors();
    }
}
