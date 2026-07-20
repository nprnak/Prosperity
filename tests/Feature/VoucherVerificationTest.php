<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\VoucherManagement\Models\Voucher;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

class VoucherVerificationTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_vouchers_get_a_verification_code_on_creation(): void
    {
        $voucher = $this->voucher();

        $this->assertNotNull($voucher->verification_code);
        $this->assertSame(12, strlen($voucher->verification_code));
    }

    public function test_anyone_can_verify_a_genuine_voucher_without_logging_in(): void
    {
        $voucher = $this->voucher();

        $this->get('/vouchers/verify?code='.$voucher->verification_code)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vouchers/Verify', false)
                ->where('result.valid', true)
                ->where('result.voucher_number', $voucher->voucher_number)
                ->where('result.application_number', $voucher->paymentTransaction->shareApplication->application_number)
            );
    }

    public function test_lookup_is_case_insensitive(): void
    {
        $voucher = $this->voucher();

        $this->get('/vouchers/verify?code='.strtolower($voucher->verification_code))
            ->assertInertia(fn ($page) => $page->where('result.valid', true));
    }

    public function test_unknown_code_reports_invalid(): void
    {
        $this->get('/vouchers/verify?code=NOTAREALCODE')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vouchers/Verify', false)
                ->where('result.valid', false)
            );
    }

    public function test_blank_code_shows_the_lookup_form_without_result(): void
    {
        $this->get('/vouchers/verify')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vouchers/Verify', false)
                ->where('result', null)
            );
    }

    protected function voucher(): Voucher
    {
        $user = User::factory()->create()->assignRole('applicant');

        $applicant = $this->minimalProfile($user);

        $application = ShareApplication::create([
            'applicant_id' => $applicant->id,
            'application_number' => 'APP-TEST-'.$user->id,
            'status' => ApplicationStatus::Approved,
            'shares_applied' => 10,
            'amount_per_share' => '100.00',
            'total_amount_declared' => '1000.00',
        ]);

        $payment = $application->paymentTransactions()->create([
            'receipt_number' => 'R-'.$user->id,
            'amount' => '1000.00',
            'payment_mode' => 'cash',
            'payment_date' => now(),
            'verification_status' => 'verified',
        ]);

        return Voucher::create([
            'payment_transaction_id' => $payment->id,
            'voucher_number' => 'VCH-'.$user->id,
            'generated_by' => $user->id,
            'generated_at' => now(),
        ]);
    }
}
