<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicantManagement\Models\Profile;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

/**
 * An approved KYC profile was previously immutable to everyone: the applicant
 * may only edit while incomplete or returned, the workflow engine refuses to
 * act on an approved record, and no staff route existed at all. That froze the
 * bank account and BOID — the two fields ASBA blocking and refunds run on.
 */
class ProfileAmendmentTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_an_approver_can_amend_an_approved_profile(): void
    {
        $profile = $this->approvedKycProfile();
        $approver = User::factory()->create()->assignRole('profile_approver');

        $this->actingAs($approver)
            ->patch("/applicants/{$profile->id}/profile", [
                'bank_account_number' => '9876543210',
                'mobile' => '9800000001',
                'remarks' => 'Bank account corrected from the updated cheque copy.',
            ])
            ->assertSessionHasNoErrors();

        $profile->refresh();

        $this->assertSame('9876543210', $profile->bank_account_number);
        $this->assertSame('9800000001', $profile->mobile);
        // The amendment does not disturb the approval already given.
        $this->assertSame(ProfileStatus::Approved, $profile->profile_status);
    }

    public function test_the_amendment_is_recorded_against_the_staff_member(): void
    {
        $profile = $this->approvedKycProfile();
        $approver = User::factory()->create()->assignRole('profile_approver');

        $this->actingAs($approver)
            ->patch("/applicants/{$profile->id}/profile", [
                'bank_account_number' => '9876543210',
                'remarks' => 'Corrected from the updated cheque copy.',
            ])
            ->assertSessionHasNoErrors();

        // Two entries: the attribute diff written by LogsActivity, and the
        // note carrying the stated reason.
        $diff = Activity::where('log_name', 'profile')->where('event', 'updated')->latest('id')->first();

        $this->assertNotNull($diff);
        $this->assertSame($approver->id, $diff->causer_id);
        $this->assertSame('9876543210', $diff->properties['attributes']['bank_account_number']);

        $note = Activity::where('log_name', 'profile')->whereNull('event')->latest('id')->first();

        $this->assertNotNull($note);
        $this->assertSame($approver->id, $note->causer_id);
        $this->assertStringContainsString('Corrected from the updated cheque copy.', $note->description);
    }

    /**
     * Identity is what the three stages actually signed off on, so it is not
     * quietly correctable after the fact.
     */
    public function test_identity_fields_are_not_amendable(): void
    {
        $profile = $this->approvedKycProfile();
        $approver = User::factory()->create()->assignRole('profile_approver');

        $this->actingAs($approver)
            ->patch("/applicants/{$profile->id}/profile", [
                'full_name_en' => 'Someone Else',
                'citizenship_number' => '00000000',
                'bank_account_number' => '9876543210',
            ])
            ->assertSessionHasNoErrors();

        $profile->refresh();

        $this->assertNotSame('Someone Else', $profile->full_name_en);
        $this->assertNotSame('00000000', $profile->citizenship_number);
    }

    public function test_a_reviewer_without_approve_permission_cannot_amend(): void
    {
        $profile = $this->approvedKycProfile();

        $this->actingAs(User::factory()->create()->assignRole('profile_reviewer'))
            ->patch("/applicants/{$profile->id}/profile", ['bank_account_number' => '1111111111'])
            ->assertForbidden();
    }

    public function test_an_applicant_cannot_amend_anyone_elses_profile(): void
    {
        $profile = $this->approvedKycProfile();

        $this->actingAs(User::factory()->create()->assignRole('applicant'))
            ->patch("/applicants/{$profile->id}/profile", ['bank_account_number' => '1111111111'])
            ->assertForbidden();
    }

    private function approvedKycProfile(): Profile
    {
        $user = User::factory()->create()->assignRole('applicant');
        $profile = $this->completeProfile($user);
        $profile->forceFill(['profile_status' => ProfileStatus::Approved])->save();

        return $profile;
    }
}
