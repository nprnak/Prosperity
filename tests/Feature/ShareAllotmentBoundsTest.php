<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\CompanyManagement\Models\Company;
use Modules\CompanyManagement\Models\ShareOffering;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

/**
 * An allotment is the company issuing its own capital, so the two ceilings —
 * what the applicant asked for, and what the offering has left — are the whole
 * point of the record. Neither was enforced.
 */
class ShareAllotmentBoundsTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_an_allotment_cannot_exceed_the_shares_applied_for(): void
    {
        $application = $this->approvedApplication(sharesApplied: 100);

        $this->actingAs($this->allotter())
            ->post("/allotments/{$application->id}", [
                'shares_allotted' => 101,
                'allotment_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('shares_allotted');

        $this->assertNull($application->fresh()->allotment);
    }

    public function test_an_allotment_cannot_take_the_offering_past_its_total_shares(): void
    {
        $offering = $this->offering(totalShares: 150);

        $first = $this->approvedApplication(sharesApplied: 100, offering: $offering);
        $second = $this->approvedApplication(sharesApplied: 100, offering: $offering);

        $this->actingAs($this->allotter())
            ->post("/allotments/{$first->id}", [
                'shares_allotted' => 100,
                'allotment_date' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        // Only 50 of the offering's 150 shares are left.
        $this->actingAs($this->allotter())
            ->post("/allotments/{$second->id}", [
                'shares_allotted' => 51,
                'allotment_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('shares_allotted');

        $this->actingAs($this->allotter())
            ->post("/allotments/{$second->id}", [
                'shares_allotted' => 50,
                'allotment_date' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();
    }

    /**
     * Revising an application's own allotment must not count that allotment
     * against the offering twice, or it becomes impossible to correct.
     */
    public function test_revising_an_allotment_does_not_count_itself_against_the_offering(): void
    {
        $offering = $this->offering(totalShares: 100);
        $application = $this->approvedApplication(sharesApplied: 100, offering: $offering);

        $allot = fn (int $shares) => $this->actingAs($this->allotter())
            ->post("/allotments/{$application->id}", [
                'shares_allotted' => $shares,
                'allotment_date' => now()->toDateString(),
            ]);

        $allot(80)->assertSessionHasNoErrors();
        $allot(100)->assertSessionHasNoErrors();

        $this->assertSame(100, (int) $application->fresh()->allotment->shares_allotted);
    }

    public function test_a_zero_allotment_marks_the_application_not_allotted(): void
    {
        $application = $this->approvedApplication(sharesApplied: 100);

        $this->actingAs($this->allotter())
            ->post("/allotments/{$application->id}", [
                'shares_allotted' => 0,
                'allotment_date' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(ApplicationStatus::NotAllotted, $application->fresh()->status);
    }

    public function test_a_partial_allotment_is_still_recorded_as_partial(): void
    {
        $application = $this->approvedApplication(sharesApplied: 100);

        $this->actingAs($this->allotter())
            ->post("/allotments/{$application->id}", [
                'shares_allotted' => 60,
                'allotment_date' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(ApplicationStatus::PartiallyAllotted, $application->fresh()->status);
    }

    private function allotter(): User
    {
        return User::factory()->create()->assignRole('application_approver');
    }

    private function offering(int $totalShares): ShareOffering
    {
        $company = Company::firstOrCreate(
            ['code' => 'TCO'],
            ['name' => 'Test Company', 'status' => 'active'],
        );

        return $company->offerings()->create([
            'title' => 'Test Offering',
            'fiscal_year' => '2082/83',
            'total_shares' => $totalShares,
            'share_rate' => '100.00',
            'min_shares' => 1,
            'max_shares' => $totalShares,
            'status' => ShareOffering::STATUS_OPEN,
        ]);
    }

    private function approvedApplication(int $sharesApplied, ?ShareOffering $offering = null): ShareApplication
    {
        $offering ??= $this->offering(totalShares: 100000);
        $user = User::factory()->create()->assignRole('applicant');
        $applicant = $this->minimalProfile($user);

        return ShareApplication::create([
            'applicant_id' => $applicant->id,
            'share_offering_id' => $offering->id,
            'application_number' => 'APP-TEST-'.$user->id,
            'status' => ApplicationStatus::Approved,
            'shares_applied' => $sharesApplied,
            'amount_per_share' => '100.00',
            'total_amount_declared' => (string) ($sharesApplied * 100).'.00',
        ]);
    }
}
