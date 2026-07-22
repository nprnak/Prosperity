<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

/**
 * The printable form doubles as a pre-submission preview, so it has to render
 * for a draft, carry the applicant's own signature, and offer the attachments
 * that print alongside it.
 */
class ApplicationPrintFormTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function draftFor(User $user): ShareApplication
    {
        $profile = $this->approvedProfile($user);

        return ShareApplication::create([
            'applicant_id' => $profile->id,
            'application_number' => 'DRAFT-000001',
            'status' => ApplicationStatus::Draft,
            'shares_applied' => 10,
            'amount_per_share' => '100.00',
            'total_amount_declared' => '1000.00',
        ]);
    }

    public function test_an_applicant_can_preview_the_form_of_an_unsubmitted_draft(): void
    {
        $user = User::factory()->create()->assignRole('applicant');
        $draft = $this->draftFor($user);

        $this->actingAs($user)
            ->get("/applications/{$draft->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('application.status', ApplicationStatus::Draft->value)
                ->where('application.application_number', 'DRAFT-000001'));
    }

    public function test_the_owners_signature_is_offered_to_the_form(): void
    {
        $user = User::factory()->create()->assignRole('applicant');
        $draft = $this->draftFor($user);

        $this->actingAs($user)
            ->get("/applications/{$draft->id}")
            ->assertInertia(fn ($page) => $page
                ->where('signatureUrl', route('profile.documents.show', 'signature'))
                ->where('photoUrl', route('profile.documents.show', 'photo')));
    }

    public function test_a_missing_document_is_offered_as_null_rather_than_a_broken_link(): void
    {
        $user = User::factory()->create()->assignRole('applicant');
        $draft = $this->draftFor($user);

        $draft->applicant->documents()->where('document_type', 'signature')->delete();

        $this->actingAs($user)
            ->get("/applications/{$draft->id}")
            ->assertInertia(fn ($page) => $page->where('signatureUrl', null));
    }

    public function test_staff_viewing_someone_elses_form_get_no_document_links(): void
    {
        $applicant = User::factory()->create()->assignRole('applicant');
        $draft = $this->draftFor($applicant);

        $staff = User::factory()->create()->assignRole('super_admin');

        // The document route serves the logged-in user's own files, so handing
        // staff those URLs would show them their own documents, not the applicant's.
        $this->actingAs($staff)
            ->get("/applications/{$draft->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('signatureUrl', null)
                ->where('photoUrl', null));
    }

    public function test_citizenship_printing_is_offered_on_the_admin_screen_only(): void
    {
        $applicant = User::factory()->create()->assignRole('applicant');
        $draft = $this->draftFor($applicant);

        $staff = User::factory()->create()->assignRole('super_admin');

        $this->actingAs($staff)
            ->get("/admin/applications/{$draft->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->count('citizenshipUrls', 2));
    }

    public function test_an_applicant_cannot_reach_the_admin_citizenship_route(): void
    {
        $applicant = User::factory()->create()->assignRole('applicant');
        $draft = $this->draftFor($applicant);

        // Their own scan, but the route is staff-only by design.
        $this->actingAs($applicant)
            ->get("/admin/applications/{$draft->id}/citizenship/front")
            ->assertForbidden();
    }

    public function test_staff_can_fetch_the_citizenship_scan_for_printing(): void
    {
        $applicant = User::factory()->create()->assignRole('applicant');
        $draft = $this->draftFor($applicant);
        $staff = User::factory()->create()->assignRole('super_admin');

        Storage::fake('private');
        Storage::disk('private')->put('docs/citizenship_front.jpg', 'scan-bytes');

        $this->actingAs($staff)
            ->get("/admin/applications/{$draft->id}/citizenship/front")
            ->assertOk();
    }
}
