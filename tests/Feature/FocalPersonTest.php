<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\CompanyManagement\Models\Company;
use Modules\CompanyManagement\Models\ShareOffering;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

/**
 * Focal person attribution: who may be designated one, who may attribute
 * applicants to them, and how an application ends up credited.
 *
 * The rules under test exist because these numbers are reported on:
 *   - eligibility is designation AND an approved KYC profile, checked live;
 *   - the applicant's profile holds a default, each application keeps its own
 *     copy, so re-attributing later cannot rewrite past offerings;
 *   - applicants quote a code rather than picking from a list, so the names of
 *     KYC-approved users are never published to them.
 */
class FocalPersonTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function superAdmin(): User
    {
        return User::factory()->create()->assignRole('super_admin');
    }

    /** A designated, KYC-approved focal person. */
    protected function focalPerson(string $name = 'Focal Person'): User
    {
        $user = User::factory()->create(['name' => $name]);

        $this->approvedProfile($user);

        $this->actingAs($this->superAdmin())
            ->patch("/admin/focal-persons/{$user->id}", ['is_focal_person' => true])
            ->assertSessionHasNoErrors();

        return $user->fresh();
    }

    protected function openOffering(): ShareOffering
    {
        $company = Company::firstOrCreate(
            ['code' => 'TCO'],
            ['name' => 'Test Company', 'status' => 'active'],
        );

        return $company->offerings()->create([
            'title' => 'Test IPO',
            'fiscal_year' => '2082/83',
            'total_shares' => 100000,
            'share_rate' => '250.00',
            'min_shares' => 10,
            'max_shares' => 500,
            'status' => ShareOffering::STATUS_OPEN,
        ]);
    }

    protected function approvedApplicant(): Profile
    {
        return $this->approvedProfile(User::factory()->create()->assignRole('applicant'));
    }

    // ---------------------------------------------------------------- designation

    public function test_super_admin_designates_a_kyc_approved_user_and_a_code_is_issued(): void
    {
        $user = User::factory()->create();
        $this->approvedProfile($user);

        $this->actingAs($this->superAdmin())
            ->patch("/admin/focal-persons/{$user->id}", ['is_focal_person' => true])
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertTrue($user->is_focal_person);
        $this->assertMatchesRegularExpression('/^FP-\d{4}$/', $user->focal_person_code);
    }

    public function test_a_user_whose_kyc_is_not_approved_cannot_be_designated(): void
    {
        $user = User::factory()->create();
        // Complete, but only at the first of three sign-offs.
        $this->completeProfile($user)->forceFill(['profile_status' => ProfileStatus::Verified])->save();

        $this->actingAs($this->superAdmin())
            ->patch("/admin/focal-persons/{$user->id}", ['is_focal_person' => true])
            ->assertSessionHasErrors('is_focal_person');

        $this->assertFalse($user->fresh()->is_focal_person);
    }

    public function test_designation_does_not_touch_the_users_role(): void
    {
        $user = User::factory()->create()->assignRole('finance_staff');
        $this->approvedProfile($user);

        $this->actingAs($this->superAdmin())
            ->patch("/admin/focal-persons/{$user->id}", ['is_focal_person' => true])
            ->assertSessionHasNoErrors();

        // The whole reason this is a flag and not a Spatie role: the admin user
        // editor syncs exactly one role, so a role here would have replaced this.
        $this->assertTrue($user->fresh()->hasRole('finance_staff'));
    }

    public function test_staff_without_the_permission_cannot_reach_the_screen(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('finance_staff'))
            ->get('/admin/focal-persons')
            ->assertForbidden();
    }

    public function test_revoking_keeps_the_code_and_every_existing_attribution(): void
    {
        $focalPerson = $this->focalPerson();
        $applicant = $this->approvedApplicant();
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->patch("/applicants/{$applicant->id}/focal-person", ['focal_person_id' => $focalPerson->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->patch("/admin/focal-persons/{$focalPerson->id}", ['is_focal_person' => false])
            ->assertSessionHasNoErrors();

        $focalPerson->refresh();

        $this->assertFalse($focalPerson->is_focal_person);
        // History has to stay readable, and re-designating must not renumber them.
        $this->assertNotNull($focalPerson->focal_person_code);
        $this->assertSame($focalPerson->id, $applicant->fresh()->focal_person_id);
    }

    // -------------------------------------------------------------------- lookup

    public function test_an_applicant_resolves_a_code_to_a_name(): void
    {
        $focalPerson = $this->focalPerson('Ramesh Adhikari');

        $this->actingAs($this->approvedApplicant()->user)
            // Lower case on purpose: the code gets repeated over the phone.
            ->getJson('/focal-persons/lookup?code='.strtolower($focalPerson->focal_person_code))
            ->assertOk()
            ->assertJson(['found' => true, 'name' => 'Ramesh Adhikari']);
    }

    public function test_an_unknown_code_resolves_to_nothing(): void
    {
        $this->actingAs($this->approvedApplicant()->user)
            ->getJson('/focal-persons/lookup?code=FP-9999')
            ->assertNotFound()
            ->assertJson(['found' => false]);
    }

    public function test_an_undesignated_user_is_not_resolvable(): void
    {
        $focalPerson = $this->focalPerson();

        $this->actingAs($this->superAdmin())
            ->patch("/admin/focal-persons/{$focalPerson->id}", ['is_focal_person' => false]);

        // The code still exists, but eligibility is checked live.
        $this->actingAs($this->approvedApplicant()->user)
            ->getJson('/focal-persons/lookup?code='.$focalPerson->focal_person_code)
            ->assertNotFound();
    }

    public function test_a_focal_person_whose_kyc_lapses_stops_being_offered(): void
    {
        $focalPerson = $this->focalPerson();

        Profile::query()->where('user_id', $focalPerson->id)
            ->update(['profile_status' => ProfileStatus::Returned->value]);

        $this->actingAs($this->approvedApplicant()->user)
            ->getJson('/focal-persons/lookup?code='.$focalPerson->focal_person_code)
            ->assertNotFound();
    }

    // ----------------------------------------------------------- profile default

    public function test_super_admin_sets_an_applicants_default_without_disturbing_kyc(): void
    {
        $focalPerson = $this->focalPerson();
        $applicant = $this->approvedApplicant();

        $this->actingAs($this->superAdmin())
            ->patch("/applicants/{$applicant->id}/focal-person", ['focal_person_id' => $focalPerson->id])
            ->assertSessionHasNoErrors();

        $applicant->refresh();

        $this->assertSame($focalPerson->id, $applicant->focal_person_id);
        // Attribution is not a KYC decision: an approved profile must not be
        // dragged back into the review chain by being re-attributed.
        $this->assertSame(ProfileStatus::Approved, $applicant->profile_status);
        $this->assertSame(1, (int) $applicant->workflow_cycle);
    }

    public function test_a_kyc_reviewer_cannot_assign_a_focal_person(): void
    {
        $focalPerson = $this->focalPerson();
        $applicant = $this->approvedApplicant();

        $this->actingAs(User::factory()->create()->assignRole('profile_reviewer'))
            ->patch("/applicants/{$applicant->id}/focal-person", ['focal_person_id' => $focalPerson->id])
            ->assertForbidden();

        $this->assertNull($applicant->fresh()->focal_person_id);
    }

    public function test_an_undesignated_user_cannot_be_assigned_as_a_default(): void
    {
        $bystander = User::factory()->create();
        $this->approvedProfile($bystander);
        $applicant = $this->approvedApplicant();

        $this->actingAs($this->superAdmin())
            ->patch("/applicants/{$applicant->id}/focal-person", ['focal_person_id' => $bystander->id])
            ->assertSessionHasErrors('focal_person_id');

        $this->assertNull($applicant->fresh()->focal_person_id);
    }

    public function test_an_applicant_cannot_be_their_own_default(): void
    {
        $applicant = $this->approvedApplicant();

        // Designate the applicant themselves, then try to self-attribute.
        $this->actingAs($this->superAdmin())
            ->patch("/admin/focal-persons/{$applicant->user_id}", ['is_focal_person' => true])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->superAdmin())
            ->patch("/applicants/{$applicant->id}/focal-person", ['focal_person_id' => $applicant->user_id])
            ->assertSessionHasErrors('focal_person_id');

        $this->assertNull($applicant->fresh()->focal_person_id);
    }

    public function test_the_default_can_be_cleared(): void
    {
        $focalPerson = $this->focalPerson();
        $applicant = $this->approvedApplicant();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->patch("/applicants/{$applicant->id}/focal-person", [
            'focal_person_id' => $focalPerson->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->patch("/applicants/{$applicant->id}/focal-person", [
            'focal_person_id' => null,
        ])->assertSessionHasNoErrors();

        $this->assertNull($applicant->fresh()->focal_person_id);
    }

    // ------------------------------------------------------------- application

    public function test_a_new_draft_inherits_the_applicants_default(): void
    {
        $offering = $this->openOffering();
        $focalPerson = $this->focalPerson();
        $applicant = $this->approvedApplicant();

        $this->actingAs($this->superAdmin())->patch("/applicants/{$applicant->id}/focal-person", [
            'focal_person_id' => $focalPerson->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($applicant->user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $offering->id,
            'shares_applied' => 20,
        ]])->assertSessionHasNoErrors();

        $this->assertSame($focalPerson->id, ShareApplication::firstOrFail()->focal_person_id);
    }

    public function test_an_applicant_credits_a_different_focal_person_by_code(): void
    {
        $offering = $this->openOffering();
        $default = $this->focalPerson('Default Person');
        $other = $this->focalPerson('Other Person');
        $applicant = $this->approvedApplicant();

        $this->actingAs($this->superAdmin())->patch("/applicants/{$applicant->id}/focal-person", [
            'focal_person_id' => $default->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($applicant->user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $offering->id,
            'shares_applied' => 20,
            'focal_person_code' => $other->focal_person_code,
        ]])->assertSessionHasNoErrors();

        $this->assertSame($other->id, ShareApplication::firstOrFail()->focal_person_id);
        // The override is per application; the applicant's default is untouched.
        $this->assertSame($default->id, $applicant->fresh()->focal_person_id);
    }

    public function test_an_unknown_code_rejects_the_draft(): void
    {
        $offering = $this->openOffering();
        $applicant = $this->approvedApplicant();

        $this->actingAs($applicant->user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $offering->id,
            'shares_applied' => 20,
            'focal_person_code' => 'FP-9999',
        ]])->assertSessionHasErrors('payload.focal_person_code');

        // Nothing half-applied: the code is resolved before any write.
        $this->assertSame(0, ShareApplication::count());
    }

    public function test_an_applicant_cannot_name_themselves_on_their_application(): void
    {
        $offering = $this->openOffering();
        $applicant = $this->approvedApplicant();

        $this->actingAs($this->superAdmin())
            ->patch("/admin/focal-persons/{$applicant->user_id}", ['is_focal_person' => true])
            ->assertSessionHasNoErrors();

        $code = $applicant->user->fresh()->focal_person_code;

        $this->actingAs($applicant->user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $offering->id,
            'shares_applied' => 20,
            'focal_person_code' => $code,
        ]])->assertSessionHasErrors('payload.focal_person_code');
    }

    public function test_changing_the_default_leaves_an_existing_application_alone(): void
    {
        $offering = $this->openOffering();
        $first = $this->focalPerson('First Person');
        $second = $this->focalPerson('Second Person');
        $applicant = $this->approvedApplicant();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->patch("/applicants/{$applicant->id}/focal-person", [
            'focal_person_id' => $first->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($applicant->user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $offering->id,
            'shares_applied' => 20,
        ]])->assertSessionHasNoErrors();

        $this->actingAs($admin)->patch("/applicants/{$applicant->id}/focal-person", [
            'focal_person_id' => $second->id,
        ])->assertSessionHasNoErrors();

        // This is the point of the per-application column: the offering already
        // applied for stays credited where it was reported.
        $this->assertSame($first->id, ShareApplication::firstOrFail()->focal_person_id);
        $this->assertSame($second->id, $applicant->fresh()->focal_person_id);
    }

    public function test_an_applicant_clears_the_credit_on_their_own_application(): void
    {
        $offering = $this->openOffering();
        $focalPerson = $this->focalPerson();
        $applicant = $this->approvedApplicant();

        $this->actingAs($this->superAdmin())->patch("/applicants/{$applicant->id}/focal-person", [
            'focal_person_id' => $focalPerson->id,
        ])->assertSessionHasNoErrors();

        $payload = fn (?string $code) => ['payload' => array_filter([
            'share_offering_id' => $offering->id,
            'shares_applied' => 20,
            'focal_person_code' => $code,
        ], fn ($value) => $value !== null)];

        $this->actingAs($applicant->user)->post('/applications/draft', $payload($focalPerson->focal_person_code))
            ->assertSessionHasNoErrors();

        // Submitting the field empty is a deliberate clear, distinct from never
        // sending it at all.
        $this->actingAs($applicant->user)->post('/applications/draft', $payload(''))
            ->assertSessionHasNoErrors();

        $this->assertNull(ShareApplication::firstOrFail()->focal_person_id);
    }
}
