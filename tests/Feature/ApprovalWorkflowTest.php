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
