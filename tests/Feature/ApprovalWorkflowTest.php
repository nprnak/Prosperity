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

        // Approval is also the money sign-off — no separate finance step
        // verifies the receipt.
        $payment = $application->paymentTransactions()->firstOrFail();
        $this->assertSame('verified', $payment->verification_status);
        $this->assertSame($approver->id, $payment->verified_by);

        // three distinct signatures, each with remarks
        $events = $application->workflowEvents()->reorder('id')->get();
        $this->assertCount(3, $events);
        $this->assertSame(3, $events->pluck('actor_id')->unique()->count());
        $this->assertTrue($events->every(fn ($event) => filled($event->remarks)));
    }

    public function test_submitted_application_completes_three_stage_chain_without_finance_step(): void
    {
        Storage::fake('private');
        Notification::fake();

        $application = $this->paymentVerifiedApplication();
        $application->update(['status' => ApplicationStatus::Submitted]);
        $application->paymentTransactions()->latest()->first()?->update(['verification_status' => 'pending']);

        $verifier = User::factory()->create()->assignRole('application_verifier');
        $reviewer = User::factory()->create()->assignRole('application_reviewer');
        $approver = User::factory()->create()->assignRole('application_approver');

        $this->actingAs($verifier)
            ->get("/admin/applications/{$application->id}")
            ->assertOk();

        $this->actingAs($verifier)
            ->post("/verifier/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Verified at stage 1.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(ApplicationStatus::Verified, $application->refresh()->status);

        $this->actingAs($reviewer)
            ->get("/admin/applications/{$application->id}")
            ->assertOk();

        $this->actingAs($reviewer)
            ->post("/reviewer/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Reviewed at stage 2.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(ApplicationStatus::Reviewed, $application->refresh()->status);

        $this->actingAs($approver)
            ->get("/admin/applications/{$application->id}")
            ->assertOk();

        $this->actingAs($approver)
            ->post("/approver/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Approved at stage 3.'])
            ->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame(ApplicationStatus::Approved, $application->status);
        $this->assertNotNull($application->approved_at);

        $payment = $application->paymentTransactions()->latest()->first();
        $this->assertNotNull($payment?->voucher);
        $this->assertSame('verified', $payment?->verification_status);
    }

    /**
     * A payment is settled once, at final approval — not at stage 1. An
     * application can still be sent back after the verifier signs it off, so
     * marking the payment verified this early would call money confirmed on
     * an application nobody has actually approved yet.
     */
    public function test_verifier_stage_does_not_mark_payment_verified(): void
    {
        $application = $this->paymentVerifiedApplication();
        $application->update(['status' => ApplicationStatus::Submitted]);

        $payment = $application->paymentTransactions()->latest()->firstOrFail();
        $payment->update([
            'verification_status' => 'pending',
            'verified_by' => null,
            'verified_at' => null,
        ]);

        $verifier = User::factory()->create()->assignRole('application_verifier');

        $this->actingAs($verifier)
            ->get("/admin/applications/{$application->id}")
            ->assertOk();

        $this->actingAs($verifier)
            ->post("/verifier/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Verified at stage 1.'])
            ->assertSessionHasNoErrors();

        $payment->refresh();

        $this->assertSame('pending', $payment->verification_status);
        $this->assertNull($payment->verified_by);
        $this->assertNull($payment->verified_at);
    }

    /**
     * The rule the whole chain exists to enforce: every transaction on an
     * application is marked verified once, automatically, the moment it is
     * finally approved — not before, and not by a separate finance sign-off.
     */
    public function test_approval_marks_every_transaction_on_the_application_verified(): void
    {
        Storage::fake('private');
        Notification::fake();

        $application = $this->paymentVerifiedApplication();
        $application->update(['status' => ApplicationStatus::Submitted]);
        $application->paymentTransactions()->update([
            'verification_status' => 'pending',
            'verified_by' => null,
            'verified_at' => null,
        ]);

        $approver = User::factory()->create()->assignRole('application_approver');

        foreach (['application_verifier', 'application_reviewer'] as $role) {
            $staff = User::factory()->create()->assignRole($role);

            $this->actingAs($staff)
                ->get("/admin/applications/{$application->id}")
                ->assertOk();
            $this->actingAs($staff)
                ->post('/'.str_replace('application_', '', $role)."/applications/{$application->id}/act",
                    ['action' => 'approve', 'remarks' => 'Checked.'])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($approver)
            ->get("/admin/applications/{$application->id}")
            ->assertOk();
        $this->actingAs($approver)
            ->post("/approver/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Approved.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ApplicationStatus::Approved, $application->fresh()->status);

        $payment = $application->paymentTransactions()->firstOrFail();
        $this->assertSame('verified', $payment->verification_status);
        $this->assertSame($approver->id, $payment->verified_by);
        $this->assertNotNull($payment->verified_at);
    }

    public function test_verifier_must_view_application_form_before_marking_verified(): void
    {
        $application = $this->paymentVerifiedApplication();
        $application->update(['status' => ApplicationStatus::Submitted]);

        $verifier = User::factory()->create()->assignRole('application_verifier');

        $this->actingAs($verifier)
            ->post("/verifier/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Verified at stage 1.'])
            ->assertSessionHasErrors('workflow');

        $this->actingAs($verifier)
            ->get("/admin/applications/{$application->id}")
            ->assertOk();

        $this->actingAs($verifier)
            ->post("/verifier/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Verified at stage 1.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ApplicationStatus::Verified, $application->fresh()->status);
    }

    public function test_reviewer_must_view_application_form_before_marking_reviewed(): void
    {
        $application = $this->paymentVerifiedApplication();

        $verifier = User::factory()->create()->assignRole('application_verifier');
        $reviewer = User::factory()->create()->assignRole('application_reviewer');

        $this->actingAs($verifier)
            ->get("/admin/applications/{$application->id}")
            ->assertOk();

        $this->actingAs($verifier)
            ->post("/verifier/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Verified at stage 1.'])
            ->assertSessionHasNoErrors();

        $this->actingAs($reviewer)
            ->post("/reviewer/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Reviewed at stage 2.'])
            ->assertSessionHasErrors('workflow');

        $this->actingAs($reviewer)
            ->get("/admin/applications/{$application->id}")
            ->assertOk();

        $this->actingAs($reviewer)
            ->post("/reviewer/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Reviewed at stage 2.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ApplicationStatus::Reviewed, $application->fresh()->status);
    }

    public function test_approver_must_view_application_form_before_marking_approved(): void
    {
        Storage::fake('private');
        Notification::fake();

        $application = $this->paymentVerifiedApplication();

        $verifier = User::factory()->create()->assignRole('application_verifier');
        $reviewer = User::factory()->create()->assignRole('application_reviewer');
        $approver = User::factory()->create()->assignRole('application_approver');

        $this->actingAs($verifier)
            ->get("/admin/applications/{$application->id}")
            ->assertOk();

        $this->actingAs($verifier)
            ->post("/verifier/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Verified at stage 1.'])
            ->assertSessionHasNoErrors();

        $this->actingAs($reviewer)
            ->get("/admin/applications/{$application->id}")
            ->assertOk();

        $this->actingAs($reviewer)
            ->post("/reviewer/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Reviewed at stage 2.'])
            ->assertSessionHasNoErrors();

        $this->actingAs($approver)
            ->post("/approver/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Approved at stage 3.'])
            ->assertSessionHasErrors('workflow');

        $this->actingAs($approver)
            ->get("/admin/applications/{$application->id}")
            ->assertOk();

        $this->actingAs($approver)
            ->post("/approver/applications/{$application->id}/act",
                ['action' => 'approve', 'remarks' => 'Approved at stage 3.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ApplicationStatus::Approved, $application->fresh()->status);
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
                ->has('pending.data', 15)
                ->where('pending.total', 16)
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
                ->component('ApplicationReview', false)
                ->has('pending.data', 1)
                ->where('pending.data.0.id', $pending->id)
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
