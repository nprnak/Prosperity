<?php

namespace Tests\Feature;

use App\Enums\WorkflowAction;
use App\Enums\WorkflowStage;
use App\Models\User;
use App\Workflow\Exceptions\WorkflowException;
use App\Workflow\WorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicantManagement\Models\Profile;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

class WorkflowEngineTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected WorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->workflow = app(WorkflowService::class);
    }

    protected function submittedProfile(): Profile
    {
        $profile = $this->completeProfile(User::factory()->create()->assignRole('applicant'));
        $profile->forceFill(['profile_status' => ProfileStatus::Submitted])->save();

        return $profile->refresh();
    }

    protected function staff(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    public function test_profile_advances_through_all_three_stages(): void
    {
        $profile = $this->submittedProfile();

        $this->assertSame(WorkflowStage::Verifier, $profile->pendingStage());

        $this->workflow->act($profile, $this->staff('profile_verifier'), WorkflowAction::Approve, 'Documents legible.');
        $this->assertSame(ProfileStatus::Verified, $profile->refresh()->profile_status);
        $this->assertSame(WorkflowStage::Reviewer, $profile->pendingStage());

        $this->workflow->act($profile, $this->staff('profile_reviewer'), WorkflowAction::Approve, 'Details match documents.');
        $this->assertSame(ProfileStatus::Reviewed, $profile->refresh()->profile_status);

        $this->workflow->act($profile, $this->staff('profile_approver'), WorkflowAction::Approve, 'Approved for share application.');
        $this->assertSame(ProfileStatus::Approved, $profile->refresh()->profile_status);

        // the full chain is on the record, in order, with remarks
        $events = $profile->workflowEvents()->reorder('id')->get();
        $this->assertCount(3, $events);
        $this->assertSame(
            [WorkflowStage::Verifier, WorkflowStage::Reviewer, WorkflowStage::Approver],
            $events->pluck('stage')->all(),
        );
        $this->assertSame(3, $events->pluck('actor_id')->unique()->count());
        $this->assertNotEmpty($events->first()->remarks);
    }

    public function test_stages_cannot_be_skipped(): void
    {
        $profile = $this->submittedProfile();

        // the record is awaiting the verifier, so the reviewer cannot pick it up
        $this->expectException(WorkflowException::class);
        $this->workflow->act($profile, $this->staff('profile_reviewer'), WorkflowAction::Approve, 'Trying to skip ahead.');
    }

    public function test_one_person_cannot_occupy_two_stages_of_the_same_cycle(): void
    {
        $profile = $this->submittedProfile();

        // holds every stage role, which is allowed
        $wearer = User::factory()->create()
            ->assignRole('profile_verifier')
            ->assignRole('profile_reviewer');

        $this->workflow->act($profile, $wearer, WorkflowAction::Approve, 'Verified by me.');

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessageMatches('/already acted/');
        $this->workflow->act($profile->refresh(), $wearer, WorkflowAction::Approve, 'And reviewed by me too.');
    }

    public function test_super_admin_is_not_exempt_from_the_act_once_rule(): void
    {
        $profile = $this->submittedProfile();
        $superAdmin = $this->staff('super_admin');

        $this->workflow->act($profile, $superAdmin, WorkflowAction::Approve, 'Verified.');

        $this->expectException(WorkflowException::class);
        $this->workflow->act($profile->refresh(), $superAdmin, WorkflowAction::Approve, 'Also reviewed.');
    }

    public function test_send_back_moves_exactly_one_stage_and_the_same_person_may_redo_their_stage(): void
    {
        $profile = $this->submittedProfile();
        $verifier = $this->staff('profile_verifier');
        $reviewer = $this->staff('profile_reviewer');

        $this->workflow->act($profile, $verifier, WorkflowAction::Approve, 'Looks fine.');
        $this->workflow->act($profile->refresh(), $reviewer, WorkflowAction::SendBack, 'PAN does not match citizenship.');

        // back with the verifier, not the applicant
        $this->assertSame(ProfileStatus::Submitted, $profile->refresh()->profile_status);
        $this->assertSame(WorkflowStage::Verifier, $profile->pendingStage());

        // repeating your own stage after a send-back is allowed
        $this->workflow->act($profile, $verifier, WorkflowAction::Approve, 'Re-checked, PAN corrected.');
        $this->assertSame(ProfileStatus::Verified, $profile->refresh()->profile_status);
    }

    public function test_first_stage_cannot_send_back_and_is_told_to_return_instead(): void
    {
        $profile = $this->submittedProfile();

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessageMatches('/Return it to the applicant/');
        $this->workflow->act($profile, $this->staff('profile_verifier'), WorkflowAction::SendBack, 'Nothing behind me.');
    }

    public function test_any_stage_can_return_straight_to_the_applicant(): void
    {
        $profile = $this->submittedProfile();

        $this->workflow->act($profile, $this->staff('profile_verifier'), WorkflowAction::Approve, 'Fine.');
        $this->workflow->act($profile->refresh(), $this->staff('profile_reviewer'), WorkflowAction::Approve, 'Fine.');
        $this->workflow->act($profile->refresh(), $this->staff('profile_approver'), WorkflowAction::ReturnToApplicant, 'Citizenship scan unreadable.');

        $this->assertSame(ProfileStatus::Returned, $profile->refresh()->profile_status);
        $this->assertTrue($profile->profile_status->isEditableByApplicant());
    }

    public function test_restarting_the_cycle_retires_earlier_signoffs(): void
    {
        $profile = $this->submittedProfile();
        $verifier = $this->staff('profile_verifier');

        $this->workflow->act($profile, $verifier, WorkflowAction::Approve, 'Verified.');
        $this->workflow->act($profile->refresh(), $this->staff('profile_reviewer'), WorkflowAction::ReturnToApplicant, 'Fix your BOID.');

        // applicant corrects and resubmits
        $profile->refresh()->restartWorkflowCycle();
        $profile->forceFill(['profile_status' => ProfileStatus::chainStart()])->save();
        $profile->refresh();

        $this->assertSame(2, $profile->workflow_cycle);
        $this->assertSame([], $profile->stagesActedByUser($verifier->id));

        // the original verifier may verify the corrected form again
        $this->workflow->act($profile, $verifier, WorkflowAction::Approve, 'BOID now correct.');
        $this->assertSame(ProfileStatus::Verified, $profile->refresh()->profile_status);

        // but is still barred from a second stage within this new cycle
        $verifier->assignRole('profile_reviewer');
        $this->expectException(WorkflowException::class);
        $this->workflow->act($profile->refresh(), $verifier, WorkflowAction::Approve, 'Reviewing my own work.');
    }

    public function test_queue_payload_carries_what_the_ui_needs(): void
    {
        $profile = $this->submittedProfile();
        $verifier = $this->staff('profile_verifier');

        $this->workflow->act($profile, $verifier, WorkflowAction::Approve, 'Documents legible.');
        $reviewer = $this->staff('profile_reviewer');

        $this->actingAs($reviewer)->get('/applicants/review')
            ->assertInertia(fn ($page) => $page
                ->component('Applicants/ReviewQueue', false)
                ->has('pending.data', 1)
                // the queue tells the reviewer which stage it awaits, whether a
                // send-back has anywhere to go, and who acted before them
                ->where('pending.data.0.pending_stage_label', 'Reviewer')
                ->where('pending.data.0.can_send_back', true)
                ->where('pending.data.0.workflow_events.0.remarks', 'Documents legible.')
                ->where('pending.data.0.workflow_events.0.actor.name', $verifier->name)
                ->where('pending.data.0.workflow_events.0.stage_label', 'Verifier')
                ->where('pending.data.0.workflow_events.0.action_label', 'Approve')
            );
    }

    public function test_first_stage_queue_reports_no_send_back_target(): void
    {
        $this->submittedProfile();

        $this->actingAs($this->staff('profile_verifier'))->get('/applicants/review')
            ->assertInertia(fn ($page) => $page
                ->where('pending.data.0.pending_stage_label', 'Verifier')
                ->where('pending.data.0.can_send_back', false)
            );
    }

    public function test_queue_pagination_matches_the_engines_act_once_rule(): void
    {
        // One profile this user may act on, one they are barred from because
        // they already took a different stage of the same cycle.
        $wearer = User::factory()->create()
            ->assignRole('profile_verifier')
            ->assignRole('profile_reviewer');

        $open = $this->submittedProfile();

        $barred = $this->submittedProfile();
        $this->workflow->act($barred, $wearer, WorkflowAction::Approve, 'Verified by me.');

        // $barred now sits with the reviewer, a stage $wearer holds but is
        // barred from. The SQL filter must agree with WorkflowService.
        $this->assertTrue($this->workflow->mayAct($open->refresh(), $wearer));
        $this->assertFalse($this->workflow->mayAct($barred->refresh(), $wearer));

        $this->actingAs($wearer)->get('/applicants/review')
            ->assertInertia(fn ($page) => $page
                ->has('pending.data', 1)
                ->where('pending.data.0.id', $open->id)
                ->where('pending.total', 1)
            );
    }

    public function test_queue_paginates_rather_than_returning_everything(): void
    {
        foreach (range(1, 17) as $ignored) {
            $this->submittedProfile();
        }

        $this->actingAs($this->staff('profile_verifier'))->get('/applicants/review')
            ->assertInertia(fn ($page) => $page
                ->has('pending.data', 15)
                ->where('pending.total', 17)
            );

        $this->actingAs($this->staff('profile_verifier'))->get('/applicants/review?page=2')
            ->assertInertia(fn ($page) => $page->has('pending.data', 2));
    }

    public function test_the_two_queues_on_the_review_screen_page_independently(): void
    {
        // 17 waiting on the verifier, 12 already decided.
        foreach (range(1, 17) as $ignored) {
            $this->submittedProfile();
        }

        foreach (range(1, 12) as $ignored) {
            $this->completeProfile(User::factory()->create()->assignRole('applicant'))
                ->forceFill(['profile_status' => ProfileStatus::Approved])->save();
        }

        $verifier = $this->staff('profile_verifier');

        $this->actingAs($verifier)->get('/applicants/review')
            ->assertInertia(fn ($page) => $page
                ->has('pending.data', 15)
                ->where('pending.total', 17)
                ->has('recentlyReviewed.data', 10)
                ->where('recentlyReviewed.total', 12)
            );

        // Paging the decided list must not disturb the pending queue, and
        // vice versa — they use separate query parameters.
        $this->actingAs($verifier)->get('/applicants/review?decided=2')
            ->assertInertia(fn ($page) => $page
                ->has('recentlyReviewed.data', 2)
                ->has('pending.data', 15)
            );

        $this->actingAs($verifier)->get('/applicants/review?page=2')
            ->assertInertia(fn ($page) => $page
                ->has('pending.data', 2)
                ->has('recentlyReviewed.data', 10)
            );
    }

    public function test_a_record_with_no_status_yet_serialises_without_blowing_up(): void
    {
        $profile = new Profile;

        $this->assertNull($profile->workflowStatus());
        $this->assertNull($profile->pendingStage());
        $this->assertFalse($profile->toArray()['can_send_back']);
    }

    public function test_remarks_are_required(): void
    {
        $profile = $this->submittedProfile();

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessageMatches('/Remarks are required/');
        $this->workflow->act($profile, $this->staff('profile_verifier'), WorkflowAction::Approve, '   ');
    }

    public function test_a_user_without_the_stage_role_is_refused(): void
    {
        $profile = $this->submittedProfile();

        $this->expectException(WorkflowException::class);
        $this->workflow->act($profile, $this->staff('finance_staff'), WorkflowAction::Approve, 'Not my job.');
    }

    public function test_records_outside_the_chain_are_refused(): void
    {
        $profile = $this->completeProfile(User::factory()->create()->assignRole('applicant'));

        // still incomplete — no stage is waiting on it
        $this->assertNull($profile->pendingStage());

        $this->expectException(WorkflowException::class);
        $this->workflow->act($profile, $this->staff('profile_verifier'), WorkflowAction::Approve, 'Too early.');
    }
}
