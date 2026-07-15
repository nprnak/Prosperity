<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\ApplicantManagement\Models\Applicant;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\PaymentManagement\Models\PaymentMethod;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
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

        $admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($admin)->get('/admin/payment-methods')->assertOk();
    }

    public function test_admin_can_create_method_with_qr_image(): void
    {
        Storage::fake('private');
        $admin = User::factory()->create()->assignRole('admin');

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

    public function test_finance_can_record_payment_against_active_method_only(): void
    {
        $active = PaymentMethod::create(['name' => 'Bank Deposit', 'status' => 'active']);
        $inactive = PaymentMethod::create(['name' => 'Old Wallet', 'status' => 'inactive']);
        $application = $this->submittedApplication();
        $finance = User::factory()->create()->assignRole('finance_staff');

        $base = [
            'amount' => '1000.00', 'payment_mode' => 'online_transfer', 'payment_date' => now()->toDateString(),
        ];

        $this->actingAs($finance)
            ->post("/finance/applications/{$application->id}/payments", [...$base, 'payment_method_id' => $inactive->id])
            ->assertSessionHasErrors('payment_method_id');

        $this->actingAs($finance)
            ->post("/finance/applications/{$application->id}/payments", [...$base, 'payment_method_id' => $active->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($active->id, $application->paymentTransactions()->first()->payment_method_id);
    }

    public function test_method_with_payments_cannot_be_deleted(): void
    {
        $method = PaymentMethod::create(['name' => 'Bank Deposit', 'status' => 'active']);
        $application = $this->submittedApplication();
        $application->paymentTransactions()->create([
            'receipt_number' => 'R-1', 'amount' => '100.00', 'payment_mode' => 'cash',
            'payment_date' => now(), 'payment_method_id' => $method->id,
        ]);

        $admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($admin)->delete("/admin/payment-methods/{$method->id}")->assertStatus(422);
        $this->assertNotNull($method->fresh());
    }

    protected function submittedApplication(): ShareApplication
    {
        $user = User::factory()->create()->assignRole('applicant');

        $applicant = Applicant::create([
            'user_id' => $user->id,
            'full_name_nepali' => 'परीक्षण',
            'full_name_english' => 'Applicant '.$user->id,
            'date_of_birth' => '1990-01-01',
            'age' => 36,
            'father_name' => 'F',
            'grandfather_name' => 'GF',
            'marital_status' => 'single',
            'permanent_district' => 'KTM',
            'permanent_municipality' => 'KMC',
            'permanent_ward' => '1',
            'mobile_number' => '98000000'.$user->id,
        ]);

        return ShareApplication::create([
            'applicant_id' => $applicant->id,
            'application_number' => 'APP-TEST-'.$user->id,
            'status' => ShareApplication::STATUS_SUBMITTED,
            'shares_applied' => 10,
            'amount_per_share' => '100.00',
            'total_amount_declared' => '1000.00',
        ]);
    }
}
