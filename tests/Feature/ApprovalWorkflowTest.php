<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\ApplicantManagement\Models\Applicant;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApprovalManagement\Notifications\ApplicationRejectedNotification;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_application_travels_the_full_review_verify_approve_chain(): void
    {
        Storage::fake('private');
        Notification::fake();

        $application = $this->paymentVerifiedApplication();
        $reviewer = User::factory()->create()->assignRole('reviewer');
        $verifier = User::factory()->create()->assignRole('verifier');
        $approver = User::factory()->create()->assignRole('approver');

        // 1. reviewer
        $this->actingAs($reviewer)
            ->post("/reviewer/applications/{$application->id}/review")
            ->assertSessionHasNoErrors();
        $application->refresh();
        $this->assertSame(ShareApplication::STATUS_REVIEWED, $application->status);
        $this->assertSame($reviewer->id, $application->reviewed_by);

        // 2. verifier
        $this->actingAs($verifier)
            ->post("/verifier/applications/{$application->id}/verify")
            ->assertSessionHasNoErrors();
        $application->refresh();
        $this->assertSame(ShareApplication::STATUS_VERIFIED, $application->status);
        $this->assertSame($verifier->id, $application->verified_by);

        // 3. approver
        $this->actingAs($approver)
            ->post("/approver/applications/{$application->id}/approve")
            ->assertSessionHasNoErrors();
        $application->refresh();
        $this->assertSame(ShareApplication::STATUS_APPROVED, $application->status);
        $this->assertSame($approver->id, $application->approved_by);

        // the voucher generated on approval carries a verification code
        $voucher = \Modules\VoucherManagement\Models\Voucher::firstOrFail();
        $this->assertNotNull($voucher->verification_code);

        // every transition is recorded with the actor's IP and browser
        $events = $application->events()->orderBy('id')->get();
        $this->assertCount(3, $events);
        foreach ($events as $event) {
            $this->assertArrayHasKey('ip', $event->meta);
            $this->assertArrayHasKey('user_agent', $event->meta);
        }
        $this->assertSame(
            [ShareApplication::STATUS_REVIEWED, ShareApplication::STATUS_VERIFIED, ShareApplication::STATUS_APPROVED],
            $events->pluck('to_status')->all(),
        );
    }

    public function test_stages_cannot_be_skipped(): void
    {
        $application = $this->paymentVerifiedApplication();
        $verifier = User::factory()->create()->assignRole('verifier');
        $approver = User::factory()->create()->assignRole('approver');

        // verifier cannot pick up an application that has not been reviewed
        $this->actingAs($verifier)
            ->post("/verifier/applications/{$application->id}/verify")
            ->assertStatus(422);

        // approver can no longer approve straight from payment_verified
        $this->actingAs($approver)
            ->post("/approver/applications/{$application->id}/approve")
            ->assertStatus(422);

        $this->assertSame(ShareApplication::STATUS_PAYMENT_VERIFIED, $application->fresh()->status);
    }

    public function test_stage_dashboards_are_permission_gated(): void
    {
        $reviewer = User::factory()->create()->assignRole('reviewer');
        $verifier = User::factory()->create()->assignRole('verifier');

        $this->actingAs($reviewer)->get('/reviewer/dashboard')->assertOk();
        $this->actingAs($reviewer)->get('/verifier/dashboard')->assertForbidden();
        $this->actingAs($reviewer)->get('/approver/dashboard')->assertForbidden();

        $this->actingAs($verifier)->get('/verifier/dashboard')->assertOk();
        $this->actingAs($verifier)->get('/reviewer/dashboard')->assertForbidden();
    }

    public function test_reviewer_only_rejects_applications_in_their_stage(): void
    {
        Notification::fake();

        $application = $this->paymentVerifiedApplication(email: 'applicant@example.com');
        $reviewer = User::factory()->create()->assignRole('reviewer');

        $this->actingAs($reviewer)
            ->post("/reviewer/applications/{$application->id}/reject", ['rejection_reason' => 'Documents unreadable.'])
            ->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame(ShareApplication::STATUS_REJECTED, $application->status);
        $this->assertSame('Documents unreadable.', $application->rejection_reason);
        Notification::assertSentOnDemand(ApplicationRejectedNotification::class);

        // already rejected — no longer in the review stage
        $this->actingAs($reviewer)
            ->post("/reviewer/applications/{$application->id}/reject", ['rejection_reason' => 'Again.'])
            ->assertStatus(422);
    }

    public function test_reviewer_dashboard_lists_only_payment_verified_applications(): void
    {
        $pending = $this->paymentVerifiedApplication();
        $draft = $this->paymentVerifiedApplication();
        $draft->update(['status' => ShareApplication::STATUS_DRAFT]);

        $reviewer = User::factory()->create()->assignRole('reviewer');

        $this->actingAs($reviewer)->get('/reviewer/dashboard')
            ->assertInertia(fn ($page) => $page
                ->component('Reviewer/Dashboard', false)
                ->has('applications', 1)
                ->where('applications.0.id', $pending->id)
            );
    }

    protected function paymentVerifiedApplication(?string $email = null): ShareApplication
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
            'email' => $email,
        ]);

        $application = ShareApplication::create([
            'applicant_id' => $applicant->id,
            'application_number' => 'APP-TEST-'.$user->id,
            'status' => ShareApplication::STATUS_PAYMENT_VERIFIED,
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
