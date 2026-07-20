<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicantManagement\Models\Profile;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

/**
 * The detail page a KYC stage decides on, and the document access it needs.
 * Before it existed a reviewer could only see the queue's summary line, so
 * the identity documents the review exists to check were unreachable.
 */
class ProfileReviewDetailTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function submittedProfile(): Profile
    {
        $profile = $this->completeProfile(User::factory()->create()->assignRole('applicant'));

        $profile->forceFill(['profile_status' => ProfileStatus::Submitted->value])->save();

        return $profile;
    }

    public function test_verifier_sees_the_full_profile_and_may_act(): void
    {
        $profile = $this->submittedProfile();

        $this->actingAs(User::factory()->create()->assignRole('profile_verifier'))
            ->get("/applicants/{$profile->id}/profile")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Module pages are not on Inertia's testing view-finder path.
                ->component('Applicants/ProfileShow', false)
                ->where('applicant.national_id_number', $profile->national_id_number)
                ->where('applicant.boid', $profile->boid)
                ->where('canAct', true)
                // The relations the page renders must arrive loaded.
                ->has('applicant.permanent_address')
                ->has('applicant.documents')
            );
    }

    public function test_reviewer_can_open_an_applicants_document(): void
    {
        Storage::fake('private');

        $profile = $this->submittedProfile();

        $path = 'docs/citizenship_front.jpg';
        Storage::disk('private')->put($path, UploadedFile::fake()->image('cz.jpg')->getContent());
        $profile->documents()->where('document_type', 'citizenship_front')->update(['file_path' => $path]);

        $this->actingAs(User::factory()->create()->assignRole('profile_reviewer'))
            ->get("/applicants/{$profile->id}/profile/documents/citizenship-front")
            ->assertOk();
    }

    public function test_an_applicant_cannot_open_another_applicants_profile(): void
    {
        $profile = $this->submittedProfile();

        $this->actingAs(User::factory()->create()->assignRole('applicant'))
            ->get("/applicants/{$profile->id}/profile")
            ->assertForbidden();

        $this->actingAs(User::factory()->create()->assignRole('applicant'))
            ->get("/applicants/{$profile->id}/profile/documents/citizenship-front")
            ->assertForbidden();
    }

    /**
     * The act-once rule decides the buttons, not the route gate: someone who
     * verified this cycle may still read the record, but cannot also review it.
     */
    public function test_a_stage_already_acted_can_read_but_not_act(): void
    {
        $profile = $this->submittedProfile();

        $staff = User::factory()->create();
        $staff->assignRole('profile_verifier');
        $staff->assignRole('profile_reviewer');

        $this->actingAs($staff)->post("/applicants/{$profile->id}/profile/act", [
            'action' => 'approve',
            'remarks' => 'Documents legible.',
        ]);

        $this->actingAs($staff)
            ->get("/applicants/{$profile->id}/profile")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canAct', false));
    }

    public function test_acting_returns_the_reviewer_to_the_queue(): void
    {
        $profile = $this->submittedProfile();

        $this->actingAs(User::factory()->create()->assignRole('profile_verifier'))
            ->post("/applicants/{$profile->id}/profile/act", [
                'action' => 'approve',
                'remarks' => 'Documents legible.',
            ])
            ->assertRedirect('/applicants/review');
    }

    public function test_an_unknown_document_slug_is_not_found(): void
    {
        $profile = $this->submittedProfile();

        $this->actingAs(User::factory()->create()->assignRole('profile_reviewer'))
            ->get("/applicants/{$profile->id}/profile/documents/passport")
            ->assertNotFound();
    }
}
