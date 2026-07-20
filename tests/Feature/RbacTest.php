<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\PaymentManagement\Models\PaymentTransaction;
use Modules\VoucherManagement\Models\Voucher;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    public function test_applicant_cannot_access_staff_or_admin_pages(): void
    {
        $applicant = $this->userWithRole('applicant');

        foreach (['/finance/dashboard', '/approver/dashboard', '/allotments/register', '/admin/users', '/admin/dashboard', '/admin/settings', '/admin/logs'] as $uri) {
            $this->actingAs($applicant)->get($uri)->assertForbidden();
        }
    }

    public function test_applicant_can_access_wizard(): void
    {
        $this->actingAs($this->userWithRole('applicant'))
            ->get('/applications/wizard')
            ->assertOk();
    }

    public function test_finance_staff_can_access_finance_dashboard_but_not_admin_or_approver_pages(): void
    {
        $finance = $this->userWithRole('finance_staff');

        $this->actingAs($finance)->get('/finance/dashboard')->assertOk();
        $this->actingAs($finance)->get('/approver/dashboard')->assertForbidden();
        $this->actingAs($finance)->get('/admin/users')->assertForbidden();
        $this->actingAs($finance)->get('/applications/wizard')->assertForbidden();
    }

    public function test_approver_can_access_approver_and_allotment_pages_but_not_finance_or_admin(): void
    {
        $approver = $this->userWithRole('application_approver');

        $this->actingAs($approver)->get('/approver/dashboard')->assertOk();
        $this->actingAs($approver)->get('/allotments/register')->assertOk();
        $this->actingAs($approver)->get('/finance/dashboard')->assertForbidden();
        $this->actingAs($approver)->get('/admin/users')->assertForbidden();
    }

    public function test_admin_can_access_everything(): void
    {
        $admin = $this->userWithRole('super_admin');

        foreach (['/admin/dashboard', '/admin/users', '/admin/applications', '/admin/payments', '/admin/allotments', '/admin/reports', '/admin/settings', '/admin/logs', '/finance/dashboard', '/approver/dashboard', '/allotments/register', '/applications/wizard'] as $uri) {
            $this->actingAs($admin)->get($uri)->assertOk();
        }
    }

    public function test_applicant_cannot_submit_another_applicants_application(): void
    {
        $owner = $this->userWithRole('applicant');
        $intruder = $this->userWithRole('applicant');
        $application = $this->draftApplicationFor($owner);

        $this->actingAs($intruder)
            ->post("/applications/{$application->id}/submit")
            ->assertForbidden();
    }

    public function test_applicant_cannot_download_another_applicants_voucher(): void
    {
        $owner = $this->userWithRole('applicant');
        $intruder = $this->userWithRole('applicant');
        $voucher = $this->voucherFor($owner);

        $this->actingAs($intruder)
            ->get("/vouchers/{$voucher->id}/download")
            ->assertForbidden();
    }

    public function test_approver_can_download_any_voucher(): void
    {
        $owner = $this->userWithRole('applicant');
        $voucher = $this->voucherFor($owner);

        // 404 (missing pdf file), never 403: authorization passed.
        $response = $this->actingAs($this->userWithRole('application_approver'))
            ->get("/vouchers/{$voucher->id}/download");

        $this->assertNotSame(403, $response->status());
    }

    protected function draftApplicationFor(User $user): ShareApplication
    {
        $applicant = $this->minimalProfile($user);

        return ShareApplication::create([
            'applicant_id' => $applicant->id,
            'application_number' => 'DRAFT-TEST-'.$user->id,
            'status' => ApplicationStatus::Draft,
            'shares_applied' => 10,
            'amount_per_share' => '100.00',
            'total_amount_declared' => '1000.00',
        ]);
    }

    protected function voucherFor(User $user): Voucher
    {
        $application = $this->draftApplicationFor($user);

        $payment = PaymentTransaction::create([
            'share_application_id' => $application->id,
            'receipt_number' => 'RCPT-TEST-'.$user->id,
            'amount' => '1000.00',
            'payment_mode' => 'online_transfer',
            'payment_date' => now(),
        ]);

        return Voucher::create([
            'payment_transaction_id' => $payment->id,
            'voucher_number' => 'VCH-TEST-'.$user->id,
            'pdf_path' => 'vouchers/vch-test-'.$user->id.'.pdf',
            'generated_by' => $user->id,
            'generated_at' => now(),
        ]);
    }
}
