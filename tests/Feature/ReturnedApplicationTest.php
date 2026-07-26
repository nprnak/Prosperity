<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\CompanyManagement\Models\Company;
use Modules\CompanyManagement\Models\ShareOffering;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

/**
 * Returning an application asks the applicant to correct it, so they have to
 * be able to — and a corrected application has to be signed off again from
 * the start, not wave through on the strength of sign-offs given to the
 * version that was sent back.
 */
class ReturnedApplicationTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('private');
    }

    public function test_a_returned_application_is_edited_in_place_not_replaced(): void
    {
        [$user, $application] = $this->returnedApplication();

        $originalNumber = $application->application_number;

        $this->actingAs($user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $application->share_offering_id,
            'shares_applied' => 30,
            'vouchers' => [
                ['transaction_code' => 'TXN-FIXED', 'image' => UploadedFile::fake()->image('fixed.png')],
            ],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(1, ShareApplication::count(), 'Correcting a returned application created a second one.');

        $application->refresh();

        $this->assertSame($originalNumber, $application->application_number);
        $this->assertSame(30, (int) $application->shares_applied);
        $this->assertSame(ApplicationStatus::Returned, $application->status);
    }

    public function test_the_wizard_offers_the_returned_application_for_correction(): void
    {
        [$user, $application] = $this->returnedApplication();

        $this->actingAs($user)->get('/applications/wizard')
            ->assertInertia(fn ($page) => $page
                ->component('Applications/Wizard', false)
                ->where('draft.id', $application->id)
            );
    }

    public function test_resubmitting_a_returned_application_starts_a_fresh_workflow_cycle(): void
    {
        [$user, $application] = $this->returnedApplication();

        // Refreshed first: workflow_cycle is not fillable, so straight after
        // create() the model holds null and any assertion against it is
        // meaningless.
        $cycleBefore = (int) $application->refresh()->workflow_cycle;

        $this->assertSame(1, $cycleBefore);

        $this->actingAs($user)
            ->post("/applications/{$application->id}/submit", ['declaration_accepted' => true])
            ->assertSessionHasNoErrors();

        $application->refresh();

        $this->assertSame(ApplicationStatus::Submitted, $application->status);
        $this->assertSame($cycleBefore + 1, $application->workflow_cycle);
    }

    /**
     * A settled application must not lock the applicant out of a different
     * offering — max_applications_per_user promises otherwise.
     */
    public function test_a_settled_application_does_not_block_a_new_one_for_another_offering(): void
    {
        $user = User::factory()->create()->assignRole('applicant');
        $applicant = $this->minimalProfile($user);
        $applicant->forceFill(['profile_status' => ProfileStatus::Approved])->save();

        $first = $this->offering('OLD');
        $second = $this->offering('NEW');

        ShareApplication::create([
            'applicant_id' => $applicant->id,
            'share_offering_id' => $first->id,
            'application_number' => 'APP-DONE',
            'status' => ApplicationStatus::Approved,
            'shares_applied' => 10,
            'amount_per_share' => '100.00',
            'total_amount_declared' => '1000.00',
        ]);

        // The block lives in the wizard's props, not in the draft endpoint —
        // so that is what has to stop reporting a settled application as one
        // still under review.
        $this->actingAs($user)->get('/applications/wizard')
            ->assertInertia(fn ($page) => $page
                ->component('Applications/Wizard', false)
                ->where('activeApplications', [])
            );

        $this->actingAs($user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $second->id,
            'shares_applied' => 20,
            'vouchers' => [
                ['transaction_code' => 'TXN-NEW', 'image' => UploadedFile::fake()->image('new.png')],
            ],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(2, ShareApplication::count());
    }

    /**
     * An application still with staff does block a second one for the same
     * offering — otherwise an applicant could apply twice over.
     */
    public function test_an_in_flight_application_still_blocks_its_own_offering(): void
    {
        [$user, $application] = $this->returnedApplication();
        $application->forceFill(['status' => ApplicationStatus::Reviewed])->save();

        $this->actingAs($user)->get('/applications/wizard')
            ->assertInertia(fn ($page) => $page
                ->component('Applications/Wizard', false)
                ->where('activeApplications.0.share_offering_id', $application->share_offering_id)
            );
    }

    private function offering(string $code): ShareOffering
    {
        $company = Company::firstOrCreate(
            ['code' => $code],
            ['name' => 'Company '.$code, 'status' => 'active'],
        );

        return $company->offerings()->create([
            'title' => 'Offering '.$code,
            'fiscal_year' => '2082/83',
            'total_shares' => 100000,
            'share_rate' => '100.00',
            'min_shares' => 10,
            'max_shares' => 500,
            'status' => ShareOffering::STATUS_OPEN,
        ]);
    }

    /** @return array{0: User, 1: ShareApplication} */
    private function returnedApplication(): array
    {
        $user = User::factory()->create()->assignRole('applicant');
        $applicant = $this->minimalProfile($user);
        $applicant->forceFill(['profile_status' => ProfileStatus::Approved])->save();

        $offering = $this->offering('TCO');

        $application = ShareApplication::create([
            'applicant_id' => $applicant->id,
            'share_offering_id' => $offering->id,
            'application_number' => 'PHL-2083-000042',
            'status' => ApplicationStatus::Returned,
            'shares_applied' => 20,
            'amount_per_share' => '100.00',
            'total_amount_declared' => '2000.00',
        ]);

        $application->vouchers()->create([
            'transaction_code' => 'TXN-OLD',
            'image_path' => 'applications/'.$applicant->id.'/old.png',
        ]);

        Storage::disk('private')->put('applications/'.$applicant->id.'/old.png', 'slip');

        return [$user, $application];
    }
}
