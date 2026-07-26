<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApprovalManagement\Notifications\ApplicationReturnedNotification;
use Modules\VoucherManagement\Models\Voucher;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_application_travels_the_full_verify_review_approve_chain(): void
    {
        Storage::fake('private');
        Notification::fake();

        $application = $this->paymentVerifiedApplication();
        $verifier = User::factory()->create()->assignRole('application_verifier');
        $reviewer = User::factory()->create()->assignRole('application_reviewer');
        $approver = User::factory()->create()->assignRole('application_approver');

        // 1. verifier
        $this->actingAs($verifier)
            ->post("/verifier/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Payment evidence matches.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(ApplicationStatus::Verified, $application->refresh()->status);

        // 2. reviewer
        $this->actingAs($reviewer)
            ->post("/reviewer/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Application consistent with KYC.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(ApplicationStatus::Reviewed, $application->refresh()->status);

        // 3. approver — final sign-off issues the voucher
        $this->actingAs($approver)
            ->post("/approver/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Approved.'])
            ->assertSessionHasNoErrors();
        $application->refresh();
        $this->assertSame(ApplicationStatus::Approved, $application->status);
        $this->assertSame($approver->id, $application->approved_by);

        // three distinct signatures, each with remarks
        $events = $application->workflowEvents()->reorder('id')->get();
        $this->assertCount(3, $events);
        $this->assertSame(3, $events->pluck('actor_id')->unique()->count());
        $this->assertTrue($events->every(fn ($event) => filled($event->remarks)));
    }

    public function test_stages_cannot_be_skipped(): void
    {
        $application = $this->paymentVerifiedApplication();

        // awaiting the verifier, so the reviewer cannot pick it up
        $this->actingAs(User::factory()->create()->assignRole('application_reviewer'))
            ->post("/reviewer/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Skipping ahead.'])
            ->assertSessionHasErrors('workflow');

        // nor can the approver reach past both earlier stages
        $this->actingAs(User::factory()->create()->assignRole('application_approver'))
            ->post("/approver/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Straight to approval.'])
            ->assertSessionHasErrors('workflow');

        $this->assertSame(ApplicationStatus::PaymentVerified, $application->fresh()->status);
    }

    public function test_one_person_cannot_take_two_stages_of_the_same_application(): void
    {
        $application = $this->paymentVerifiedApplication();

        $wearer = User::factory()->create()
            ->assignRole('application_verifier')
            ->assignRole('application_reviewer');

        $this->actingAs($wearer)
            ->post("/verifier/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Verified by me.'])
            ->assertSessionHasNoErrors();

        $this->actingAs($wearer)
            ->post("/reviewer/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'And reviewed by me.'])
            ->assertSessionHasErrors('workflow');

        $this->assertSame(ApplicationStatus::Verified, $application->fresh()->status);
    }

    public function test_remarks_are_required_on_every_action(): void
    {
        $application = $this->paymentVerifiedApplication();

        $this->actingAs(User::factory()->create()->assignRole('application_verifier'))
            ->post("/verifier/applications/{$application->id}/act", ['action' => 'approve'])
            ->assertSessionHasErrors('remarks');
    }

    public function test_reviewer_can_send_an_application_back_one_stage(): void
    {
        $application = $this->paymentVerifiedApplication();
        $verifier = User::factory()->create()->assignRole('application_verifier');

        $this->actingAs($verifier)
            ->post("/verifier/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Looks right.']);

        $this->actingAs(User::factory()->create()->assignRole('application_reviewer'))
            ->post("/reviewer/applications/{$application->id}/act",
                ['action' => 'send_back', 'remarks' => 'Payment reference does not match.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ApplicationStatus::PaymentVerified, $application->fresh()->status);
    }

    public function test_stage_dashboards_are_permission_gated(): void
    {
        $reviewer = User::factory()->create()->assignRole('application_reviewer');
        $verifier = User::factory()->create()->assignRole('application_verifier');

        $this->actingAs($reviewer)->get('/reviewer/dashboard')->assertOk();
        $this->actingAs($reviewer)->get('/verifier/dashboard')->assertForbidden();
        $this->actingAs($reviewer)->get('/approver/dashboard')->assertForbidden();

        $this->actingAs($verifier)->get('/verifier/dashboard')->assertOk();
        $this->actingAs($verifier)->get('/reviewer/dashboard')->assertForbidden();
    }

    public function test_verifier_can_return_an_application_to_the_applicant(): void
    {
        Notification::fake();

        $application = $this->paymentVerifiedApplication(email: 'applicant@example.com');
        $verifier = User::factory()->create()->assignRole('application_verifier');

        $this->actingAs($verifier)
            ->post("/verifier/applications/{$application->id}/act",
                ['action' => 'return_to_applicant', 'remarks' => 'Documents unreadable.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ApplicationStatus::Returned, $application->fresh()->status);
        Notification::assertSentOnDemand(ApplicationReturnedNotification::class);

        // with the applicant now, so no stage can act on it
        $this->actingAs($verifier)
            ->post("/verifier/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Changed my mind.'])
            ->assertSessionHasErrors('workflow');
    }

    public function test_application_queue_paginates(): void
    {
        foreach (range(1, 16) as $ignored) {
            $this->paymentVerifiedApplication();
        }

        $this->actingAs(User::factory()->create()->assignRole('application_verifier'))
            ->get('/verifier/dashboard')
            ->assertInertia(fn ($page) => $page
                ->has('applications.data', 15)
                ->where('applications.total', 16)
            );
    }

    public function test_verifier_dashboard_lists_only_applications_awaiting_that_stage(): void
    {
        $pending = $this->paymentVerifiedApplication();
        $draft = $this->paymentVerifiedApplication();
        $draft->update(['status' => ApplicationStatus::Draft]);

        $verifier = User::factory()->create()->assignRole('application_verifier');

        $this->actingAs($verifier)->get('/verifier/dashboard')
            ->assertInertia(fn ($page) => $page
                ->component('Verifier/Dashboard', false)
                ->has('applications.data', 1)
                ->where('applications.data.0.id', $pending->id)
            );
    }

    /**
     * The detail page offers to name who verified and who reviewed an
     * application, so the chain has to actually record it — otherwise those
     * fields sit at a dash forever and the audit trail lives only in the
     * workflow events nobody reads.
     */
    public function test_each_stage_records_who_signed_it_off(): void
    {
        Storage::fake('private');
        Notification::fake();

        $application = $this->paymentVerifiedApplication();
        $verifier = User::factory()->create()->assignRole('application_verifier');
        $reviewer = User::factory()->create()->assignRole('application_reviewer');
        $approver = User::factory()->create()->assignRole('application_approver');

        $this->actingAs($verifier)->post("/verifier/applications/{$application->id}/act",
            ['action' => 'approve', 'remarks' => 'Slips match the statement.']);
        $this->actingAs($reviewer)->post("/reviewer/applications/{$application->id}/act",
            ['action' => 'approve', 'remarks' => 'Details check out.']);
        $this->actingAs($approver)->post("/approver/applications/{$application->id}/act",
            ['action' => 'approve', 'remarks' => 'Approved.']);

        $application->refresh();

        $this->assertSame($verifier->id, $application->verified_by);
        $this->assertSame($reviewer->id, $application->reviewed_by);
        $this->assertSame($approver->id, $application->approved_by);

        $this->assertNotNull($application->verified_at);
        $this->assertNotNull($application->reviewed_at);
        $this->assertNotNull($application->approved_at);
    }

    /**
     * Finance recomputing the payment total must never rewind sign-offs that
     * have already been given. An application sitting with the approver does
     * not drop back into the verifier's queue because one more slip was
     * checked — and it certainly must not do so silently, with no event on
     * the record to say why.
     */
    public function test_finance_verification_does_not_rewind_completed_sign_offs(): void
    {
        $application = $this->paymentVerifiedApplication();

        $this->actingAs(User::factory()->create()->assignRole('application_verifier'))
            ->post("/verifier/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Slips match the statement.']);
        $this->actingAs(User::factory()->create()->assignRole('application_reviewer'))
            ->post("/reviewer/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Details check out.']);

        $this->assertSame(ApplicationStatus::Reviewed, $application->fresh()->status);

        // A further deposit turns up and finance works through it.
        $second = $application->paymentTransactions()->create([
            'receipt_number' => 'R-second-'.$application->id,
            'amount' => '500.00',
            'payment_mode' => 'cash',
            'payment_date' => now(),
            'verification_status' => 'pending',
        ]);

        $this->actingAs(User::factory()->create()->assignRole('finance_staff'))
            ->post("/finance/payments/{$second->id}/verify", ['status' => 'verified']);
        $this->actingAs(User::factory()->create()->assignRole('finance_staff'))
            ->post("/finance/payments/{$second->id}/verify", ['status' => 'verified']);

        $this->assertSame(ApplicationStatus::Reviewed, $application->fresh()->status);
    }

    /**
     * A payment rejected after the chain has passed the application must not
     * rewind it into an earlier queue — but nor can it be left sitting at
     * Reviewed with money nobody confirmed. It goes back to the applicant,
     * who is the only one who can do anything about a failed deposit.
     */
    public function test_rejecting_a_payment_after_review_returns_the_application(): void
    {
        Notification::fake();

        $application = $this->paymentVerifiedApplication();

        $this->actingAs(User::factory()->create()->assignRole('application_verifier'))
            ->post("/verifier/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Slips match the statement.']);

        $this->assertSame(ApplicationStatus::Verified, $application->fresh()->status);

        $payment = $application->paymentTransactions()->first();

        $this->actingAs(User::factory()->create()->assignRole('finance_staff'))
            ->post("/finance/payments/{$payment->id}/verify",
                ['status' => 'rejected', 'notes' => 'Bank reversed the transfer.']);

        $application->refresh();

        $this->assertSame(ApplicationStatus::Returned, $application->status);
        // Not rewound into the verifier's queue — returned, which is a state
        // the applicant can act on.
        $this->assertNotSame(ApplicationStatus::PaymentVerified, $application->status);
    }

    /**
     * A deposit rejected while finance still holds the application just moves
     * it back to PaymentPending; there is no sign-off to protect yet.
     */
    public function test_rejecting_a_deposit_before_review_leaves_it_with_finance(): void
    {
        $application = $this->paymentVerifiedApplication();
        $payment = $application->paymentTransactions()->firstOrFail();

        $deposit = $payment->deposits()->create([
            'reference_no' => 'TXN-A', 'amount' => '1000.00',
            'payment_date' => '2026-07-01', 'verification_status' => 'pending',
        ]);

        $this->actingAs(User::factory()->create()->assignRole('finance_staff'))
            ->post("/finance/deposits/{$deposit->id}/verify", ['status' => 'rejected'])
            ->assertSessionHasNoErrors();

        $this->assertSame('rejected', $deposit->fresh()->verification_status);
        $this->assertNotSame(ApplicationStatus::Returned, $application->fresh()->status);
    }

    /**
     * The same failed deposit, after the chain has taken the application on,
     * does send it back.
     */
    public function test_rejecting_a_deposit_after_review_returns_the_application(): void
    {
        Notification::fake();

        $application = $this->paymentVerifiedApplication();
        $payment = $application->paymentTransactions()->firstOrFail();

        $deposit = $payment->deposits()->create([
            'reference_no' => 'TXN-A', 'amount' => '1000.00',
            'payment_date' => '2026-07-01', 'verification_status' => 'verified',
        ]);

        $this->actingAs(User::factory()->create()->assignRole('application_verifier'))
            ->post("/verifier/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Slips match the statement.']);

        $this->assertSame(ApplicationStatus::Verified, $application->fresh()->status);

        $this->actingAs(User::factory()->create()->assignRole('finance_staff'))
            ->post("/finance/deposits/{$deposit->id}/verify",
                ['status' => 'rejected', 'notes' => 'Bank reversed the transfer.'])
            ->assertSessionHasNoErrors();

        $application->refresh();

        $this->assertSame(ApplicationStatus::Returned, $application->status);
        $this->assertStringContainsString('Bank reversed the transfer.', (string) $application->rejection_reason);
    }

    /**
     * The two-officer sign-off is the last step, so it cannot run while a slip
     * on the same receipt is still unchecked — the receipt would acknowledge
     * money nobody confirmed arrived.
     */
    public function test_a_receipt_cannot_be_signed_off_until_every_deposit_is(): void
    {
        $application = $this->paymentVerifiedApplication();
        $payment = $application->paymentTransactions()->firstOrFail();

        $payment->deposits()->create([
            'reference_no' => 'TXN-A', 'amount' => '500.00',
            'payment_date' => '2026-07-01', 'verification_status' => 'verified',
        ]);
        $unchecked = $payment->deposits()->create([
            'reference_no' => 'TXN-B', 'amount' => '500.00',
            'payment_date' => '2026-07-02', 'verification_status' => 'pending',
        ]);

        $this->actingAs(User::factory()->create()->assignRole('finance_staff'))
            ->post("/finance/payments/{$payment->id}/verify", ['status' => 'verified'])
            ->assertStatus(422);

        $this->assertNull($payment->fresh()->checked_by);

        // Once the last slip is checked off, the sign-off may proceed.
        $this->actingAs(User::factory()->create()->assignRole('finance_staff'))
            ->post("/finance/deposits/{$unchecked->id}/verify", ['status' => 'verified'])
            ->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->create()->assignRole('finance_staff'))
            ->post("/finance/payments/{$payment->id}/verify", ['status' => 'verified'])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($payment->fresh()->checked_by);
    }

    /**
     * The receipt number is claimed with the receipt. Taking one per declared
     * deposit at submission burned numbers on applications that were never
     * approved, and gave one application several.
     */
    public function test_the_receipt_number_is_issued_with_the_receipt(): void
    {
        Storage::fake('private');
        Notification::fake();

        $application = $this->paymentVerifiedApplication();
        $payment = $application->paymentTransactions()->firstOrFail();

        $payment->forceFill(['receipt_number' => null])->save();

        foreach (['application_verifier', 'application_reviewer', 'application_approver'] as $role) {
            $this->actingAs(User::factory()->create()->assignRole($role))
                ->post('/'.str_replace('application_', '', $role)."/applications/{$application->id}/act",
                    ['action' => 'approve', 'remarks' => 'Checked.']);
        }

        $this->assertNotNull($payment->fresh()->receipt_number);
        $this->assertNotNull($payment->fresh()->voucher);
    }

    protected function paymentVerifiedApplication(?string $email = null): ShareApplication
    {
        $user = User::factory()->create()->assignRole('applicant');

        $applicant = $this->minimalProfile($user, ['email' => $email]);

        $application = ShareApplication::create([
            'applicant_id' => $applicant->id,
            'application_number' => 'APP-TEST-'.$user->id,
            'status' => ApplicationStatus::PaymentVerified,
            'shares_applied' => 10,
            'amount_per_share' => '100.00',
            'total_amount_declared' => '1000.00',
        ]);

        $application->paymentTransactions()->create([
            'receipt_number' => 'R-'.$user->id,
            'amount' => '1000.00',
            'payment_mode' => 'cash',
            'payment_date' => now(),
            'verification_status' => 'verified',
        ]);

        return $application;
    }
}
