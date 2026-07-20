<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\CompanyManagement\Models\Company;
use Modules\CompanyManagement\Models\ShareOffering;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

class CompanyOfferingTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function openOffering(array $overrides = []): ShareOffering
    {
        $company = Company::firstOrCreate(
            ['code' => 'TCO'],
            ['name' => 'Test Company', 'status' => 'active'],
        );

        return $company->offerings()->create(array_merge([
            'title' => 'Test IPO',
            'fiscal_year' => '2082/83',
            'total_shares' => 100000,
            'share_rate' => '250.00',
            'min_shares' => 10,
            'max_shares' => 500,
            'status' => ShareOffering::STATUS_OPEN,
        ], $overrides));
    }

    protected function approvedApplicant(): User
    {
        $user = User::factory()->create()->assignRole('applicant');

        $this->minimalProfile($user)->forceFill(['profile_status' => ProfileStatus::Approved])->save();

        return $user;
    }

    public function test_only_company_managers_can_access_company_admin(): void
    {
        $applicant = User::factory()->create()->assignRole('applicant');
        $this->actingAs($applicant)->get('/admin/companies')->assertForbidden();

        $admin = User::factory()->create()->assignRole('super_admin');
        $this->actingAs($admin)->get('/admin/companies')->assertOk();
    }

    public function test_admin_can_create_company_and_offering(): void
    {
        $admin = User::factory()->create()->assignRole('super_admin');

        $this->actingAs($admin)->post('/admin/companies', [
            'name' => 'New Ventures Ltd', 'code' => 'NVL', 'status' => 'active',
        ])->assertSessionHasNoErrors();

        $company = Company::where('code', 'NVL')->firstOrFail();

        $this->actingAs($admin)->post("/admin/companies/{$company->id}/offerings", [
            'title' => 'FPO 2083', 'fiscal_year' => '2083/84', 'total_shares' => 50000,
            'share_rate' => '150.00', 'min_shares' => 10, 'max_shares' => 200,
            'status' => 'open',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $company->offerings()->count());
    }

    public function test_draft_snapshots_rate_from_offering_and_ignores_client_amounts(): void
    {
        $offering = $this->openOffering(['share_rate' => '250.00']);
        $user = $this->approvedApplicant();

        $this->actingAs($user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $offering->id,
            'shares_applied' => 20,
            // a tampering client sending its own price must be ignored
            'amount_per_share' => '1.00',
            'total_amount_declared' => '20.00',
        ]])->assertSessionHasNoErrors();

        $application = ShareApplication::firstOrFail();
        $this->assertSame($offering->id, $application->share_offering_id);
        $this->assertSame('250.00', $application->amount_per_share);
        $this->assertSame('5000.00', $application->total_amount_declared);
        $this->assertSame('TCO-2082/83', $application->issue_code);
    }

    public function test_draft_rejects_shares_outside_offering_limits(): void
    {
        $offering = $this->openOffering(['min_shares' => 10, 'max_shares' => 500]);
        $user = $this->approvedApplicant();

        $this->actingAs($user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $offering->id, 'shares_applied' => 5,
        ]])->assertSessionHasErrors('payload.shares_applied');

        $this->actingAs($user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $offering->id, 'shares_applied' => 501,
        ]])->assertSessionHasErrors('payload.shares_applied');
    }

    public function test_draft_rejects_closed_or_out_of_window_offering(): void
    {
        $user = $this->approvedApplicant();

        $closed = $this->openOffering(['status' => ShareOffering::STATUS_CLOSED]);
        $this->actingAs($user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $closed->id, 'shares_applied' => 20,
        ]])->assertSessionHasErrors('payload.share_offering_id');

        $expired = $this->openOffering(['closes_at' => now()->subDay()->toDateString()]);
        $this->actingAs($user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $expired->id, 'shares_applied' => 20,
        ]])->assertSessionHasErrors('payload.share_offering_id');
    }

    public function test_submit_blocked_when_offering_window_closes_after_drafting(): void
    {
        $offering = $this->openOffering();
        $user = $this->approvedApplicant();

        $this->actingAs($user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $offering->id, 'shares_applied' => 20,
        ]])->assertSessionHasNoErrors();

        $offering->update(['status' => ShareOffering::STATUS_CLOSED]);

        $application = ShareApplication::firstOrFail();

        $this->actingAs($user)->post("/applications/{$application->id}/submit", [
            'declaration_accepted' => true,
        ])->assertSessionHasErrors('profile');

        $this->assertSame(ApplicationStatus::Draft, $application->fresh()->status);
    }
}
