<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\VoucherManagement\Models\Voucher;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

/**
 * The receipt has to be reachable from the list staff actually work in, and
 * only by the people entitled to see it.
 */
class AdminReceiptAccessTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('private');
    }

    public function test_the_applications_list_carries_the_receipt_number_and_its_voucher(): void
    {
        [$application, $voucher] = $this->approvedApplicationWithReceipt();

        $this->actingAs(User::factory()->create()->assignRole('super_admin'))
            ->get('/admin/applications')
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Applications', false)
                ->where('applications.0.payment_transactions.0.receipt_number', '057')
                ->where('applications.0.payment_transactions.0.voucher.id', $voucher->id)
            );

        $this->assertSame($application->id, $voucher->paymentTransaction->share_application_id);
    }

    /**
     * Before approval there is no receipt, so the column reads as a dash and
     * has nothing to link to.
     */
    public function test_an_application_without_a_receipt_carries_no_receipt_number(): void
    {
        $this->approvedApplicationWithReceipt(issueReceipt: false);

        $this->actingAs(User::factory()->create()->assignRole('super_admin'))
            ->get('/admin/applications')
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Applications', false)
                ->where('applications.0.payment_transactions.0.receipt_number', null)
            );
    }

    public function test_finance_staff_can_open_a_receipt(): void
    {
        [, $voucher] = $this->approvedApplicationWithReceipt();

        $this->actingAs(User::factory()->create()->assignRole('finance_staff'))
            ->get("/vouchers/{$voucher->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vouchers/Show', false)
                ->where('receipt.receiptNumber', '057')
                ->where('receipt.referenceLine', '91723543 & 91723546 (10L & 5L each)')
                ->where('receipt.paymentDateLine', '23 June, 2026 & 3 July, 2026')
                ->where('receipt.tickedMode', 'self_cheque_deposit')
                ->where('receipt.holdingIdLabel', 'BOID')
            );
    }

    /**
     * A reviewer sees the list, and so sees the number — but the document
     * itself stays behind voucher.download-any.
     */
    public function test_an_application_reviewer_cannot_open_a_receipt(): void
    {
        [, $voucher] = $this->approvedApplicationWithReceipt();

        $this->actingAs(User::factory()->create()->assignRole('application_reviewer'))
            ->get("/vouchers/{$voucher->id}")
            ->assertForbidden();
    }

    public function test_an_unrelated_applicant_cannot_open_a_receipt(): void
    {
        [, $voucher] = $this->approvedApplicationWithReceipt();

        $this->actingAs(User::factory()->create()->assignRole('applicant'))
            ->get("/vouchers/{$voucher->id}")
            ->assertForbidden();
    }

    /** @return array{0: ShareApplication, 1: Voucher} */
    private function approvedApplicationWithReceipt(bool $issueReceipt = true): array
    {
        $user = User::factory()->create()->assignRole('applicant');
        $applicant = $this->minimalProfile($user, ['boid' => '1301010000123456']);

        $application = ShareApplication::create([
            'applicant_id' => $applicant->id,
            'application_number' => 'PHL-2083-000057',
            'status' => ApplicationStatus::Approved,
            'shares_applied' => 1500,
            'amount_per_share' => '1000.00',
            'total_amount_declared' => '1500000.00',
        ]);

        $payment = $application->paymentTransactions()->create([
            'receipt_number' => $issueReceipt ? '057' : null,
            'amount' => '1500000.00',
            'payment_mode' => 'self_cheque_deposit',
            'holding_id_no' => $applicant->boid,
            'id_type' => 'boid',
            'verification_status' => 'verified',
        ]);

        $payment->deposits()->create([
            'reference_no' => '91723543', 'amount' => '1000000.00',
            'payment_date' => '2026-06-23', 'verification_status' => 'verified',
        ]);
        $payment->deposits()->create([
            'reference_no' => '91723546', 'amount' => '500000.00',
            'payment_date' => '2026-07-03', 'verification_status' => 'verified',
        ]);

        $voucher = $issueReceipt ? Voucher::create([
            'payment_transaction_id' => $payment->id,
            'voucher_number' => '001',
            'generated_by' => User::factory()->create()->id,
            'generated_at' => now(),
        ]) : null;

        return [$application, $voucher];
    }
}
