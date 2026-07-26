<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\PaymentManagement\Models\PaymentMethod;
use Modules\PaymentManagement\Repositories\PaymentMethodRepository;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_only_admins_manage_payment_methods(): void
    {
        $finance = User::factory()->create()->assignRole('finance_staff');
        $this->actingAs($finance)->get('/admin/payment-methods')->assertForbidden();

        $admin = User::factory()->create()->assignRole('super_admin');
        $this->actingAs($admin)->get('/admin/payment-methods')->assertOk();
    }

    public function test_admin_can_create_method_with_qr_image(): void
    {
        Storage::fake('private');
        $admin = User::factory()->create()->assignRole('super_admin');

        $this->actingAs($admin)->post('/admin/payment-methods', [
            'name' => 'eSewa',
            'account_name' => 'Prosperity Holdings',
            'instructions' => 'Scan and pay.',
            'status' => 'active',
            'qr_image' => UploadedFile::fake()->image('qr.png', 300, 300),
        ])->assertSessionHasNoErrors();

        $method = PaymentMethod::where('name', 'eSewa')->firstOrFail();
        $this->assertNotNull($method->qr_image_path);
        Storage::disk('private')->assertExists($method->qr_image_path);

        // any verified user (e.g. an applicant) can view the QR to pay
        $applicant = User::factory()->create()->assignRole('applicant');
        $this->actingAs($applicant)->get("/payment-methods/{$method->id}/qr")->assertOk();
    }

    /**
     * Only an active method is offered to an applicant deciding where to pay.
     *
     * This used to be enforced when finance recorded a payment against a
     * method, but submission now creates the transaction itself and there is
     * no record-payment step to validate — so the guard that matters is the
     * one on what the applicant is shown.
     */
    public function test_only_active_methods_are_offered_to_applicants(): void
    {
        $active = PaymentMethod::create(['name' => 'Bank Deposit', 'status' => 'active']);
        PaymentMethod::create(['name' => 'Old Wallet', 'status' => 'inactive']);

        $offered = app(PaymentMethodRepository::class)->active(['id', 'name']);

        $this->assertSame([$active->id], $offered->pluck('id')->all());
    }

    public function test_method_with_payments_cannot_be_deleted(): void
    {
        $method = PaymentMethod::create(['name' => 'Bank Deposit', 'status' => 'active']);
        $application = $this->submittedApplication();
        $application->paymentTransactions()->create([
            'receipt_number' => 'R-1', 'amount' => '100.00', 'payment_mode' => 'cash',
            'payment_method_id' => $method->id,
        ]);

        $admin = User::factory()->create()->assignRole('super_admin');
        $this->actingAs($admin)->delete("/admin/payment-methods/{$method->id}")->assertStatus(422);
        $this->assertNotNull($method->fresh());
    }

    protected function submittedApplication(): ShareApplication
    {
        $user = User::factory()->create()->assignRole('applicant');

        $applicant = $this->minimalProfile($user);

        return ShareApplication::create([
            'applicant_id' => $applicant->id,
            'application_number' => 'APP-TEST-'.$user->id,
            'status' => ApplicationStatus::Submitted,
            'shares_applied' => 10,
            'amount_per_share' => '100.00',
            'total_amount_declared' => '1000.00',
        ]);
    }
}
