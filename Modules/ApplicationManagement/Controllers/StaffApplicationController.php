<?php

namespace Modules\ApplicationManagement\Controllers;

use App\Enums\WorkflowAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Workflow\WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicantManagement\Repositories\ProfileRepository;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;
use Modules\ApplicationManagement\Requests\StoreDraftStepRequest;
use Modules\ApplicationManagement\Requests\SubmitApplicationRequest;
use Modules\ApplicationManagement\Services\ApplicationWizardService;
use Modules\CompanyManagement\Repositories\ShareOfferingRepository;

/**
 * An Application Verifier filing a paper-based share application on behalf
 * of an applicant whose KYC is already approved — reuses the same wizard
 * page and submission logic the applicant's own self-service flow uses.
 *
 * Submitting forwards the application straight to the Reviewer queue: the
 * verifier who transcribed and checked the paper application has already
 * done the verify stage's job, so it is recorded as verified by them rather
 * than queued a second time.
 */
class StaffApplicationController extends Controller
{
    public function __construct(
        private ProfileRepository $profiles,
        private ShareApplicationRepository $applications,
    ) {}

    /** Search approved applicants to file a paper application for. */
    public function pickApplicant(Request $request)
    {
        $search = $request->query('q');

        return Inertia::render('Applications/PickApplicant', [
            'applicants' => $this->profiles->approved($search),
            'filters' => ['q' => $search],
        ]);
    }

    public function create(Request $request, User $applicant, ShareOfferingRepository $offerings)
    {
        abort_unless($applicant->hasRole('applicant'), 404);

        $applicantProfile = $this->profiles->findByUserId($applicant->id);

        if (! $applicantProfile?->isProfileApproved()) {
            return redirect()->route('applications.add.pick')->withErrors([
                'profile' => "{$applicant->name}'s KYC must be approved before an application can be filed for them.",
            ]);
        }

        $draft = $this->applications->latestEditableForUser($applicant->id);

        return Inertia::render('Applications/Wizard', [
            'draft' => $draft,
            'activeApplications' => $this->applications->inFlightForUser($applicant->id),
            'returnedReason' => $draft?->status === ApplicationStatus::Returned
                ? $draft->latest_workflow_remarks
                : null,
            'profile' => $applicantProfile,
            'profileCompleted' => $applicantProfile->isProfileComplete(),
            'profileStatus' => $applicantProfile->profile_status,
            'offerings' => $offerings->openNow(),
            'focalPerson' => $this->focalPersonSummary($draft, $applicantProfile),
            // Staff mode: the form posts to the applicant-scoped routes below
            // instead of the self-service ones, and shows whose application this is.
            'staffApplicant' => $applicant->only(['id', 'name', 'email']),
            'draftRouteName' => 'applications.add.draft',
            'draftRouteParams' => ['applicant' => $applicant->id],
            'submitRouteName' => 'applications.add.submit',
            'submitRouteParams' => ['applicant' => $applicant->id],
        ]);
    }

    private function focalPersonSummary(?ShareApplication $draft, ?Profile $profile): ?array
    {
        $focalPerson = $draft ? $draft->focalPerson : $profile?->focalPerson;

        return $focalPerson ? [
            'code' => $focalPerson->focal_person_code,
            'name' => $focalPerson->name,
        ] : null;
    }

    public function storeDraft(StoreDraftStepRequest $request, User $applicant, ApplicationWizardService $wizard)
    {
        abort_unless($applicant->hasRole('applicant'), 404);

        $wizard->saveDraft($applicant, $request->validated('payload'));

        return back()->with('success', 'Draft saved.');
    }

    public function submitAndVerify(SubmitApplicationRequest $request, User $applicant, ShareApplication $application, ApplicationWizardService $wizard, WorkflowService $workflow)
    {
        abort_unless($applicant->hasRole('applicant'), 404);
        abort_unless($application->applicant?->user_id === $applicant->id, 404);

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        $application->load('applicant');

        // SubmitApplicationRequest::authorize() already gated this via
        // ShareApplicationPolicy::submit(). The staff member is the actor of
        // record for the submission itself.
        $wizard->submit($request->user(), $application);

        $workflow->act($application, $request->user(), WorkflowAction::Approve, $validated['remarks'], $request);

        return redirect()
            ->route('applications.show', $application)
            ->with('success', "Application filed for {$applicant->name} and forwarded to the review team.");
    }
}
