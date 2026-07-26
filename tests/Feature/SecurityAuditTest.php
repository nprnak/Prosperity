<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\CompanyManagement\Models\Company;
use Modules\CompanyManagement\Models\ShareOffering;
use Modules\VoucherManagement\Models\Voucher;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

/**
 * Standing checks on the controls a pre-release audit relies on.
 *
 * These are here so the guarantees are asserted rather than assumed: the
 * private disk is not publicly served, one applicant cannot reach another's
 * records, and the columns that decide money and KYC outcomes cannot be set
 * from a request body.
 */
class SecurityAuditTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function offering(): ShareOffering
    {
        $company = Company::firstOrCreate(['code' => 'SEC'], ['name' => 'Sec Ltd', 'status' => 'active']);

        return $company->offerings()->create([
            'title' => 'Issue', 'fiscal_year' => '2082/83', 'total_shares' => 100000,
            'share_rate' => '100.00', 'min_shares' => 10, 'max_shares' => 1000,
            'status' => ShareOffering::STATUS_OPEN,
        ]);
    }

    // ------------------------------------------------------- private storage

    public function test_the_private_disk_is_not_served_without_a_signature(): void
    {
        // config/filesystems.php sets serve => true on a disk rooted at
        // storage/app/private, which registers GET /storage/{path}. That is only
        // safe while the disk stays private-visibility, so it is asserted.
        Storage::disk('private')->put('profiles/9/citizenship_front.jpg', 'secret scan');

        $this->get('/storage/profiles/9/citizenship_front.jpg')->assertForbidden();

        // Being logged in is not a signature either.
        $this->actingAs(User::factory()->create())
            ->get('/storage/profiles/9/citizenship_front.jpg')
            ->assertForbidden();
    }

    public function test_the_storage_upload_route_rejects_unsigned_writes(): void
    {
        $this->put('/storage/profiles/9/planted.jpg', ['file' => 'x'])->assertForbidden();

        $this->assertFalse(Storage::disk('private')->exists('profiles/9/planted.jpg'));
    }

    public function test_the_storage_route_refuses_path_traversal(): void
    {
        // Denied at the signature check before the path is ever resolved, so
        // this is a 403 rather than a 404 — either is a refusal; what matters is
        // that no file content comes back.
        foreach (['/storage/'.urlencode('../../../.env'), '/storage/..%2F..%2F..%2F.env'] as $uri) {
            $response = $this->get($uri);

            $this->assertContains($response->status(), [403, 404], "traversal not refused for {$uri}");
            $this->assertStringNotContainsString('APP_KEY', $response->getContent());
        }
    }

    // ------------------------------------------------------------------ IDOR

    public function test_an_applicant_cannot_open_another_applicants_application(): void
    {
        $offering = $this->offering();
        $mine = $this->approvedProfile(User::factory()->create()->assignRole('applicant'));
        $theirs = $this->approvedProfile(User::factory()->create()->assignRole('applicant'));

        $application = ShareApplication::create([
            'applicant_id' => $theirs->id,
            'share_offering_id' => $offering->id,
            'application_number' => 'SEC-1',
            'status' => ApplicationStatus::Submitted,
            'shares_applied' => 10,
            'amount_per_share' => '100.00',
            'total_amount_declared' => '1000.00',
        ]);

        // A real voucher on their application, so the refusal comes from the
        // ownership check rather than from route binding failing to find it.
        $voucher = $application->vouchers()->create([
            'transaction_code' => 'TXN-SEC',
            'image_path' => 'applications/'.$application->id.'/slip.jpg',
        ]);

        $this->actingAs($mine->user)->get("/applications/{$application->id}")->assertForbidden();
        $this->actingAs($mine->user)
            ->get("/applications/{$application->id}/vouchers/{$voucher->id}/image")
            ->assertForbidden();
    }

    public function test_an_applicant_cannot_open_another_applicants_kyc_profile(): void
    {
        $mine = $this->approvedProfile(User::factory()->create()->assignRole('applicant'));
        $theirs = $this->approvedProfile(User::factory()->create()->assignRole('applicant'));

        $this->actingAs($mine->user)->get("/applicants/{$theirs->id}/profile")->assertForbidden();
        $this->actingAs($mine->user)
            ->get("/applicants/{$theirs->id}/profile/documents/citizenship-front")
            ->assertForbidden();
    }

    public function test_an_applicant_cannot_download_another_applicants_voucher(): void
    {
        $offering = $this->offering();
        $mine = $this->approvedProfile(User::factory()->create()->assignRole('applicant'));
        $theirs = $this->approvedProfile(User::factory()->create()->assignRole('applicant'));

        $application = ShareApplication::create([
            'applicant_id' => $theirs->id,
            'share_offering_id' => $offering->id,
            'application_number' => 'SEC-2',
            'status' => ApplicationStatus::Approved,
            'shares_applied' => 10,
            'amount_per_share' => '100.00',
            'total_amount_declared' => '1000.00',
        ]);

        $payment = $application->paymentTransactions()->create([
            'receipt_number' => 'SEC-RCP-2',
            'amount' => '1000.00',
            'payment_mode' => 'online_transfer',
            'payment_date' => now()->toDateString(),
            'verification_status' => 'verified',
        ]);

        $voucher = Voucher::create([
            'payment_transaction_id' => $payment->id,
            'voucher_number' => '001',
            'pdf_path' => 'vouchers/voucher-001.pdf',
        ]);

        $this->actingAs($mine->user)->get("/vouchers/{$voucher->id}/download")->assertForbidden();
    }

    // ------------------------------------------------- privilege escalation

    public function test_an_applicant_cannot_approve_their_own_kyc_through_the_profile_form(): void
    {
        $profile = $this->completeProfile(User::factory()->create()->assignRole('applicant'));

        // profile_status decides who may buy shares, so it must not be reachable
        // by mass assignment from the applicant's own profile form.
        $this->actingAs($profile->user)->patch('/profile/applicant', [
            'profile_status' => ProfileStatus::Approved->value,
            'full_name_en' => 'Still Me',
        ]);

        $this->assertNotSame(ProfileStatus::Approved, $profile->fresh()->profile_status);
    }

    public function test_an_applicant_cannot_set_their_application_status_or_approver_from_the_wizard(): void
    {
        $offering = $this->offering();
        $profile = $this->approvedProfile(User::factory()->create()->assignRole('applicant'));
        $admin = User::factory()->create()->assignRole('super_admin');

        $this->actingAs($profile->user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $offering->id,
            'shares_applied' => 20,
            // Everything below is hostile: none of it is a declared payload key.
            'status' => ApplicationStatus::Approved->value,
            'approved_by' => $admin->id,
            'total_amount_declared' => '1.00',
            'amount_per_share' => '1.00',
        ]])->assertSessionHasNoErrors();

        $application = ShareApplication::firstOrFail();

        $this->assertSame(ApplicationStatus::Draft, $application->status);
        $this->assertNull($application->approved_by);
        // Rate and total are taken from the offering, never from the client.
        $this->assertSame('100.00', $application->amount_per_share);
        $this->assertSame('2000.00', $application->total_amount_declared);
    }

    public function test_an_applicant_cannot_reach_staff_screens(): void
    {
        $applicant = User::factory()->create()->assignRole('applicant');

        foreach ([
            '/admin/users',
            '/admin/companies',
            '/admin/payments',
            '/admin/settings',
            '/admin/logs',
            '/admin/focal-persons',
            '/admin/reports',
            '/allotments/register',
        ] as $uri) {
            $this->actingAs($applicant)->get($uri)->assertForbidden();
        }
    }

    // ------------------------------------------------------- token handling

    public function test_the_email_otp_is_stored_hashed_with_an_expiry(): void
    {
        $user = User::factory()->unverified()->create();

        $user->sendEmailVerificationNotification();
        $user->refresh();

        // A readable OTP column would turn any database read into account access.
        $this->assertNotNull($user->email_otp_code);
        $this->assertDoesNotMatchRegularExpression('/^\d{6}$/', $user->email_otp_code);
        $this->assertTrue($user->email_otp_expires_at->isFuture());
        $this->assertTrue($user->email_otp_expires_at->lessThanOrEqualTo(now()->addMinutes(10)));
    }

    public function test_the_otp_and_token_columns_never_reach_the_client(): void
    {
        $user = User::factory()->create();
        $user->sendEmailVerificationNotification();

        $serialised = $user->fresh()->toArray();

        foreach (['password', 'remember_token', 'email_otp_code', 'email_otp_expires_at'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $serialised);
        }
    }

    public function test_a_voucher_verification_code_is_long_and_random(): void
    {
        $codes = collect(range(1, 25))->map(fn () => Voucher::generateVerificationCode());

        // Guessable codes would let anyone forge a "verified" voucher check.
        $this->assertCount(25, $codes->unique());
        $codes->each(function (string $code) {
            $this->assertSame(12, strlen($code));
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{12}$/', $code);
        });
    }
}
