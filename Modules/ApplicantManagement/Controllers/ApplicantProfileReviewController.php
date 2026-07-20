<?php

namespace Modules\ApplicantManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Workflow\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicantManagement\Notifications\ProfileApprovedNotification;
use Modules\ApplicantManagement\Notifications\ProfileReturnedNotification;
use Modules\ApplicantManagement\Repositories\ProfileRepository;
use Modules\ApplicantManagement\Requests\ProfileWorkflowActionRequest;
use Modules\ApplicantManagement\Services\ProfileDocumentService;

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

    /**
     * The record a stage decides on. Actions live here rather than on the
     * queue, so a sign-off is only ever given beside the evidence for it.
     */
    public function show(Request $request, Profile $applicant)
    {
        Gate::authorize('view', $applicant);

        return Inertia::render('Applicants/ProfileShow', [
            'applicant' => $this->profiles->loadForReview($applicant),
            'completionChecks' => $applicant->completionChecks(),
            'completionPercent' => $applicant->completionPercent(),
            // The act-once rule and stage permissions decide this, not the
            // route gate — a queue holder can open a record they cannot act on.
            'canAct' => $this->workflow->mayAct($applicant, $request->user()),
            'documentTypes' => Profile::REQUIRED_DOCUMENT_TYPES,
        ]);
    }

    public function document(Request $request, Profile $applicant, string $type, ProfileDocumentService $documents)
    {
        Gate::authorize('view', $applicant);

        return $documents->respond($applicant, $type, $request->query('mode') === 'download');
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

        // Back to the queue rather than the detail page: whichever way this
        // went, the record has left this reviewer's hands.
        return redirect()->route('applicants.review')->with('success', $this->message($applicant));
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
