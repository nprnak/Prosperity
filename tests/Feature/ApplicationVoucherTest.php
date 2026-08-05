<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\CompanyManagement\Models\Company;
use Modules\CompanyManagement\Models\ShareOffering;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

/**
 * An applicant may pay in several deposits, so vouchers and their transaction
 * codes are rows rather than single columns, and investment sources are a set.
 */
class ApplicationVoucherTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('private');
    }

    protected function openOffering(): ShareOffering
    {
        $company = Company::firstOrCreate(
            ['code' => 'TCO'],
            ['name' => 'Test Company', 'status' => 'active'],
        );

        return $company->offerings()->create([
            'title' => 'Test IPO',
            'fiscal_year' => '2082/83',
            'total_shares' => 100000,
            'share_rate' => '250.00',
            'min_shares' => 10,
            'max_shares' => 500,
            'status' => ShareOffering::STATUS_OPEN,
        ]);
    }

    protected function approvedApplicant(): User
    {
        $user = User::factory()->create()->assignRole('applicant');

        $this->minimalProfile($user)->forceFill(['profile_status' => ProfileStatus::Approved])->save();

        return $user;
    }

    public function test_a_draft_stores_several_vouchers_each_with_its_own_code_and_slip(): void
    {
        $offering = $this->openOffering();
        $user = $this->approvedApplicant();

        $this->actingAs($user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $offering->id,
            'shares_applied' => 20,
            'vouchers' => [
                [
                    'payment_type' => 'connect_ips',
                    'deposited_bank' => 'Nepal Bank Limited',
                    'transaction_code' => 'TXN-001',
                    'asba_reference' => 'ASBA-NBL-1',
                    'amount' => '3000.00',
                    'image' => UploadedFile::fake()->image('one.png'),
                ],
                [
                    'payment_type' => 'cheque',
                    'deposited_bank' => 'Rastriya Banijya Bank',
                    'transaction_code' => 'CHQ-002',
                    'asba_reference' => 'ASBA-RBB-2',
                    'amount' => '2000.00',
                    'image' => UploadedFile::fake()->image('two.png'),
                ],
            ],
        ]])->assertSessionHasNoErrors();

        $vouchers = ShareApplication::firstOrFail()->vouchers()->orderBy('id')->get();

        $this->assertCount(2, $vouchers);
        $this->assertSame(['TXN-001', 'CHQ-002'], $vouchers->pluck('transaction_code')->all());
        $this->assertSame(['Nepal Bank Limited', 'Rastriya Banijya Bank'], $vouchers->pluck('deposited_bank')->all());
        // Each bank issues its own ASBA reference, so it rides with the deposit.
        $this->assertSame(['ASBA-NBL-1', 'ASBA-RBB-2'], $vouchers->pluck('asba_reference')->all());
        $this->assertSame(['connect_ips', 'cheque'], $vouchers->pluck('payment_type')->all());

        foreach ($vouchers as $voucher) {
            Storage::disk('private')->assertExists($voucher->image_path);
        }
    }

    public function test_removing_a_voucher_from_the_form_deletes_its_row_and_slip(): void
    {
        $offering = $this->openOffering();
        $user = $this->approvedApplicant();

        $payload = fn (array $vouchers) => [
            'share_offering_id' => $offering->id,
            'shares_applied' => 20,
            'vouchers' => $vouchers,
        ];

        $this->actingAs($user)->post('/applications/draft', ['payload' => $payload([
            ['transaction_code' => 'TXN-001', 'image' => UploadedFile::fake()->image('one.png')],
            ['transaction_code' => 'TXN-002', 'image' => UploadedFile::fake()->image('two.png')],
        ])])->assertSessionHasNoErrors();

        $application = ShareApplication::firstOrFail();
        $kept = $application->vouchers()->orderBy('id')->first();
        $dropped = $application->vouchers()->orderBy('id')->get()->last();

        // Re-saving with only the first row should take the second one with it.
        $this->actingAs($user)->post('/applications/draft', ['payload' => $payload([
            ['id' => $kept->id, 'transaction_code' => 'TXN-001'],
        ])])->assertSessionHasNoErrors();

        $this->assertSame(['TXN-001'], $application->vouchers()->pluck('transaction_code')->all());
        Storage::disk('private')->assertExists($kept->image_path);
        Storage::disk('private')->assertMissing($dropped->image_path);
    }

    /**
     * The wizard's form state survives a draft save, so on the second save its
     * rows can still be carrying no id even though the server has long since
     * assigned them. Deleting a row's slip off the back of a missing id makes
     * an irreversible operation depend on the client keeping score, so the
     * transaction code — which the form already requires to be distinct per
     * deposit — is matched too.
     */
    public function test_re_saving_a_draft_without_row_ids_keeps_the_uploaded_slips(): void
    {
        $offering = $this->openOffering();
        $user = $this->approvedApplicant();

        $payload = fn (array $vouchers) => [
            'share_offering_id' => $offering->id,
            'shares_applied' => 20,
            'vouchers' => $vouchers,
        ];

        $this->actingAs($user)->post('/applications/draft', ['payload' => $payload([
            ['transaction_code' => 'TXN-001', 'image' => UploadedFile::fake()->image('one.png')],
            ['transaction_code' => 'TXN-002', 'image' => UploadedFile::fake()->image('two.png')],
        ])])->assertSessionHasNoErrors();

        $application = ShareApplication::firstOrFail();
        $paths = $application->vouchers()->orderBy('id')->pluck('image_path')->all();

        // An unrelated field changes and the draft is saved again; the rows go
        // back carrying their codes but no ids.
        $this->actingAs($user)->post('/applications/draft', ['payload' => $payload([
            ['transaction_code' => 'TXN-001'],
            ['transaction_code' => 'TXN-002'],
        ])])->assertSessionHasNoErrors();

        $vouchers = $application->vouchers()->orderBy('id')->get();

        $this->assertCount(2, $vouchers);
        $this->assertSame(['TXN-001', 'TXN-002'], $vouchers->pluck('transaction_code')->all());
        $this->assertSame($paths, $vouchers->pluck('image_path')->all());

        foreach ($paths as $path) {
            Storage::disk('private')->assertExists($path);
        }
    }

    public function test_submission_requires_every_voucher_to_pair_a_code_with_a_slip(): void
    {
        $offering = $this->openOffering();
        $user = $this->approvedApplicant();

        // A code with no slip attached.
        $this->actingAs($user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $offering->id,
            'shares_applied' => 20,
            'vouchers' => [['transaction_code' => 'TXN-001']],
        ]])->assertSessionHasNoErrors();

        $application = ShareApplication::firstOrFail();

        $this->actingAs($user)
            ->post("/applications/{$application->id}/submit", ['declaration_accepted' => true])
            ->assertSessionHasErrors('profile');

        $this->assertSame(ApplicationStatus::Draft, $application->fresh()->status);
    }

    /**
     * Submitting is a one-way door. Re-posting the submit route on an
     * application that has already been through the chain must not reset it
     * to Submitted — its receipt has been issued and its money accounted for,
     * and the sign-offs behind it would be silently discarded.
     */
    public function test_an_application_past_draft_cannot_be_submitted_again(): void
    {
        $offering = $this->openOffering();
        $user = $this->approvedApplicant();

        $this->actingAs($user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $offering->id,
            'shares_applied' => 20,
            'vouchers' => [
                ['transaction_code' => 'TXN-001', 'amount' => '5000.00', 'payment_type' => 'connect_ips', 'image' => UploadedFile::fake()->image('one.png')],
            ],
        ]])->assertSessionHasNoErrors();

        $application = ShareApplication::firstOrFail();

        $this->actingAs($user)
            ->post("/applications/{$application->id}/submit", ['declaration_accepted' => true])
            ->assertSessionHasNoErrors();

        $application->forceFill(['status' => ApplicationStatus::Approved])->save();
        $submittedAt = $application->fresh()->submitted_at;

        $this->actingAs($user)
            ->post("/applications/{$application->id}/submit", ['declaration_accepted' => true])
            ->assertSessionHasErrors('profile');

        $application->refresh();

        $this->assertSame(ApplicationStatus::Approved, $application->status);
        $this->assertEquals($submittedAt, $application->submitted_at);
    }

    public function test_a_returned_application_may_still_be_resubmitted(): void
    {
        $offering = $this->openOffering();
        $user = $this->approvedApplicant();

        $this->actingAs($user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $offering->id,
            'shares_applied' => 20,
            'vouchers' => [
                ['transaction_code' => 'TXN-001', 'amount' => '5000.00', 'payment_type' => 'connect_ips', 'image' => UploadedFile::fake()->image('one.png')],
            ],
        ]])->assertSessionHasNoErrors();

        $application = ShareApplication::firstOrFail();
        $application->forceFill(['status' => ApplicationStatus::Returned])->save();

        $this->actingAs($user)
            ->post("/applications/{$application->id}/submit", ['declaration_accepted' => true])
            ->assertSessionHasNoErrors();

        $this->assertSame(ApplicationStatus::Submitted, $application->fresh()->status);
    }

    /**
     * One application earns one receipt, and the receipt lists the deposits it
     * acknowledges. Making each deposit its own transaction meant the receipt
     * covered whichever one happened to be picked and quietly ignored the
     * rest — and burned a receipt number apiece at submission.
     */
    public function test_declared_vouchers_become_deposits_under_one_receipt(): void
    {
        $offering = $this->openOffering();
        $user = $this->approvedApplicant();

        $this->actingAs($user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $offering->id,
            'shares_applied' => 20,
            'vouchers' => [
                ['transaction_code' => 'TXN-001', 'amount' => '3000.00', 'payment_type' => 'connect_ips', 'payment_date' => '2026-06-23', 'image' => UploadedFile::fake()->image('one.png')],
                ['transaction_code' => 'CHQ-002', 'amount' => '2000.00', 'payment_type' => 'cheque', 'payment_date' => '2026-07-03', 'image' => UploadedFile::fake()->image('two.png')],
            ],
        ]])->assertSessionHasNoErrors();

        $application = ShareApplication::firstOrFail();

        $this->actingAs($user)
            ->post("/applications/{$application->id}/submit", ['declaration_accepted' => true])
            ->assertSessionHasNoErrors();

        $payments = $application->paymentTransactions()->get();

        $this->assertCount(1, $payments);

        $payment = $payments->first();

        // No number is claimed until a receipt is actually issued.
        $this->assertNull($payment->receipt_number);
        $this->assertSame('5000.00', (string) $payment->amount);

        $deposits = $payment->deposits()->orderBy('id')->get();

        $this->assertCount(2, $deposits);
        $this->assertEqualsCanonicalizing(['3000.00', '2000.00'], $deposits->pluck('amount')->map(fn ($a) => (string) $a)->all());
        $this->assertEqualsCanonicalizing(
            ['2026-06-23', '2026-07-03'],
            $deposits->pluck('payment_date')->map(fn ($d) => $d->format('Y-m-d'))->all(),
        );
        // A cheque's code is a cheque number, everything else a reference.
        $this->assertSame('CHQ-002', $deposits->firstWhere('cheque_no', 'CHQ-002')?->cheque_no);
        $this->assertSame('TXN-001', $deposits->firstWhere('reference_no', 'TXN-001')?->reference_no);

        // IPS is not a box on the printed receipt; the first declared deposit
        // sets the mode and an online transfer is what it amounts to.
        $this->assertSame('online_transfer', $payment->payment_mode);
    }

    public function test_blank_voucher_amounts_split_the_declared_total(): void
    {
        $offering = $this->openOffering();
        $user = $this->approvedApplicant();

        // 20 shares at 250 = 5000.00 declared, with nothing stated per voucher.
        $this->actingAs($user)->post('/applications/draft', ['payload' => [
            'share_offering_id' => $offering->id,
            'shares_applied' => 20,
            'vouchers' => [
                ['transaction_code' => 'TXN-001', 'image' => UploadedFile::fake()->image('one.png')],
                ['transaction_code' => 'TXN-002', 'image' => UploadedFile::fake()->image('two.png')],
            ],
        ]])->assertSessionHasNoErrors();

        $application = ShareApplication::firstOrFail();

        $this->actingAs($user)
            ->post("/applications/{$application->id}/submit", ['declaration_accepted' => true])
            ->assertSessionHasNoErrors();

        $payment = $application->paymentTransactions()->firstOrFail();
        $amounts = $payment->deposits()->orderBy('id')->pluck('amount')->map(fn ($a) => (string) $a);

        $this->assertSame(['2500.00', '2500.00'], $amounts->all());
        // The receipt's own figure is the sum of what it acknowledges.
        $this->assertSame('5000.00', (string) $payment->amount);
    }

    public function test_investment_sources_are_a_set_that_unticking_shrinks(): void
    {
        $offering = $this->openOffering();
        $user = $this->approvedApplicant();

        $payload = fn (array $sources) => [
            'share_offering_id' => $offering->id,
            'shares_applied' => 20,
            'investment_sources' => $sources,
        ];

        $this->actingAs($user)
            ->post('/applications/draft', ['payload' => $payload(['salary', 'house_rent', 'dividend'])])
            ->assertSessionHasNoErrors();

        $applicant = ShareApplication::firstOrFail()->applicant;

        $this->assertEqualsCanonicalizing(
            ['salary', 'house_rent', 'dividend'],
            $applicant->sourcesOfFunds()->pluck('source_type')->all(),
        );

        $this->actingAs($user)
            ->post('/applications/draft', ['payload' => $payload(['salary'])])
            ->assertSessionHasNoErrors();

        $this->assertSame(['salary'], $applicant->sourcesOfFunds()->pluck('source_type')->all());
    }
}
