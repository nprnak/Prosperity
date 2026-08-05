<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\PaymentManagement\Models\PaymentTransaction;
use Modules\VoucherManagement\Models\Voucher;
use Modules\VoucherManagement\Services\ReceiptPresenter;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

/**
 * The receipt reproduces the company's paper book, which acknowledges every
 * deposit behind one receipt number: "91723543 & 91723546 (10L & 5L each)" on
 * one line and "23 June, 2026 & 3 July, 2026" on the next.
 */
class ReceiptContentTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('private');
    }

    public function test_the_receipt_lists_every_reference_with_its_amount(): void
    {
        $payment = $this->receiptedPayment();

        $presenter = app(ReceiptPresenter::class);
        $deposits = $payment->deposits;

        $this->assertSame(
            '91723543 & 91723546 (10L & 5L each)',
            $presenter->referenceLine($deposits),
        );
    }

    public function test_the_receipt_lists_every_payment_date(): void
    {
        $payment = $this->receiptedPayment();

        $this->assertSame(
            '23 June, 2026 & 3 July, 2026',
            app(ReceiptPresenter::class)->paymentDateLine($payment->deposits),
        );
    }

    public function test_a_single_deposit_carries_no_amount_annotation(): void
    {
        $payment = $this->receiptedPayment();
        $payment->deposits()->latest('id')->first()->delete();

        $this->assertSame('91723543', app(ReceiptPresenter::class)->referenceLine($payment->fresh()->deposits));
    }

    /**
     * IPS and mobile banking are not boxes on the printed form; both are an
     * online transfer as far as the receipt is concerned. Anything unmapped
     * ticks nothing rather than inventing a box.
     */
    public function test_payment_modes_map_onto_the_four_printed_boxes(): void
    {
        $presenter = app(ReceiptPresenter::class);

        $this->assertSame('online_transfer', $presenter->tickedMode('ips'));
        $this->assertSame('online_transfer', $presenter->tickedMode('mobile_banking'));
        $this->assertSame('cheque', $presenter->tickedMode('cheque'));
        $this->assertSame('self_cheque_deposit', $presenter->tickedMode('self_cheque_deposit'));
        $this->assertNull($presenter->tickedMode('something_else'));

        $this->assertSame(
            ['cheque', 'self_cheque_deposit', 'online_transfer', 'cash'],
            array_keys(ReceiptPresenter::PRINTED_MODES),
        );
    }

    public function test_the_boid_prints_as_the_holding_id(): void
    {
        $payment = $this->receiptedPayment();
        $voucher = Voucher::create([
            'payment_transaction_id' => $payment->id,
            'voucher_number' => '001',
            'generated_by' => User::factory()->create()->id,
            'generated_at' => now(),
        ]);

        $data = app(ReceiptPresenter::class)->data($voucher);

        $this->assertSame('BOID', $data['holdingIdLabel']);
        $this->assertSame('1301010000123456', $data['payment']->holding_id_no);
    }

    private function receiptedPayment(): PaymentTransaction
    {
        $user = User::factory()->create()->assignRole('applicant');
        $applicant = $this->minimalProfile($user, ['boid' => '1301010000123456']);
        $applicant->forceFill(['profile_status' => ProfileStatus::Approved])->save();

        $application = ShareApplication::create([
            'applicant_id' => $applicant->id,
            'application_number' => 'PHL-2083-000057',
            'status' => ApplicationStatus::Approved,
            'shares_applied' => 1500,
            'amount_per_share' => '1000.00',
            'total_amount_declared' => '1500000.00',
        ]);

        $payment = $application->paymentTransactions()->create([
            'receipt_number' => '057',
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

        return $payment->load('deposits');
    }
}
