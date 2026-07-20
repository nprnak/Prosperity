<?php

namespace Modules\ApplicantManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Workflow\WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicantManagement\Notifications\ProfileApprovedNotification;
use Modules\ApplicantManagement\Notifications\ProfileReturnedNotification;
use Modules\ApplicantManagement\Repositories\ProfileRepository;
use Modules\ApplicantManagement\Requests\ProfileWorkflowActionRequest;

/**
 * KYC review: verifier → reviewer → approver.
 *
 * One endpoint serves all three stages. Which stage the actor occupies is
 * derived from the profile's status, and WorkflowService decides whether they
 * may take it — so a user holding several stage roles needs no handling here.
 */
class ApplicantProfileReviewController extends Controller
{
    public function __construct(
        private ProfileRepository $profiles,
        private WorkflowService $workflow,
    ) {}

    public function queue(Request $request)
    {
        return Inertia::render('Applicants/ReviewQueue', [
            'pending' => $this->profiles->pendingForUser($request->user()),
            'recentlyReviewed' => $this->profiles->recentlyReviewed(),
        ]);
    }

    public function act(ProfileWorkflowActionRequest $request, Profile $applicant)
    {
        $before = $applicant->profile_status;

        $this->workflow->act(
            $applicant,
            $request->user(),
            $request->action(),
            $request->validated('remarks'),
            $request,
        );

        $applicant->refresh();

        $this->notifyApplicant($applicant, $before);

        return back()->with('success', $this->message($applicant));
    }

    /**
     * The applicant hears about outcomes needing their attention and about the
     * final approval — not about each internal stage passing.
     */
    private function notifyApplicant(Profile $applicant, ProfileStatus $before): void
    {
        if ($applicant->profile_status === $before) {
            return;
        }

        match ($applicant->profile_status) {
            ProfileStatus::Approved => $applicant->user?->notify(new ProfileApprovedNotification($applicant)),
            ProfileStatus::Returned => $applicant->user?->notify(new ProfileReturnedNotification($applicant)),
            default => null,
        };
    }

    private function message(Profile $applicant): string
    {
        return match ($applicant->profile_status) {
            ProfileStatus::Approved => 'Profile approved: '.$applicant->full_name_en,
            ProfileStatus::Returned => 'Profile returned to applicant: '.$applicant->full_name_en,
            default => 'Profile moved to: '.$applicant->profile_status->labelEn(),
        };
    }
}
