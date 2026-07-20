<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\CompanyManagement\Models\Company;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function applicationFor(Company $company, ApplicationStatus $status = ApplicationStatus::Submitted): ShareApplication
    {
        $user = User::factory()->create()->assignRole('applicant');

        $applicant = $this->minimalProfile($user);

        $offering = $company->offerings()->create([
            'title' => 'IPO '.$company->code, 'fiscal_year' => '2082/83', 'total_shares' => 100000,
            'share_rate' => '100.00', 'min_shares' => 10, 'max_shares' => 1000, 'status' => 'open',
        ]);

        return ShareApplication::create([
            'applicant_id' => $applicant->id,
            'share_offering_id' => $offering->id,
            'application_number' => 'APP-'.$company->code.'-'.$user->id,
            'status' => $status,
            'shares_applied' => 10,
            'amount_per_share' => '100.00',
            'total_amount_declared' => '1000.00',
        ]);
    }

    public function test_reports_require_permission(): void
    {
        $applicant = User::factory()->create()->assignRole('applicant');
        $this->actingAs($applicant)->get('/admin/reports')->assertForbidden();
        $this->actingAs($applicant)->get('/admin/reports/export?format=csv')->assertForbidden();
    }

    public function test_report_filters_by_company_and_status(): void
    {
        $alpha = Company::create(['name' => 'Alpha Ltd', 'code' => 'ALP', 'status' => 'active']);
        $beta = Company::create(['name' => 'Beta Ltd', 'code' => 'BET', 'status' => 'active']);
        $this->applicationFor($alpha, ApplicationStatus::Submitted);
        $this->applicationFor($alpha, ApplicationStatus::Approved);
        $this->applicationFor($beta, ApplicationStatus::Submitted);

        $admin = User::factory()->create()->assignRole('super_admin');

        $all = $this->actingAs($admin)->get('/admin/reports');
        $all->assertOk();
        $this->assertSame(3, $all->viewData('page')['props']['summary']['totalApplications']);

        $filtered = $this->actingAs($admin)->get("/admin/reports?company_id={$alpha->id}&status=approved");
        $this->assertSame(1, $filtered->viewData('page')['props']['summary']['totalApplications']);
    }

    public function test_exports_download_in_each_format(): void
    {
        $company = Company::create(['name' => 'Alpha Ltd', 'code' => 'ALP', 'status' => 'active']);
        $this->applicationFor($company);
        $admin = User::factory()->create()->assignRole('super_admin');

        $xlsx = $this->actingAs($admin)->get('/admin/reports/export?format=xlsx');
        $xlsx->assertOk();
        $this->assertStringContainsString('.xlsx', $xlsx->headers->get('content-disposition'));

        $csv = $this->actingAs($admin)->get('/admin/reports/export?format=csv');
        $csv->assertOk();
        $this->assertStringContainsString('.csv', $csv->headers->get('content-disposition'));

        $pdf = $this->actingAs($admin)->get('/admin/reports/export?format=pdf');
        $pdf->assertOk();
        $this->assertStringContainsString('.pdf', $pdf->headers->get('content-disposition'));
    }
}
