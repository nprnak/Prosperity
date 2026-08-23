<?php

namespace Modules\ApplicantManagement\Controllers;

use App\Enums\WorkflowAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Workflow\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ApplicantManagement\Enums\EducationLevel;
use Modules\ApplicantManagement\Enums\Gender;
use Modules\ApplicantManagement\Enums\MaritalStatus;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicantManagement\Enums\SourceOfFunds;
use Modules\ApplicantManagement\Enums\Title;
use Modules\ApplicantManagement\Repositories\ProfileRepository;
use Modules\ApplicantManagement\Requests\ApplicantProfileUpdateRequest;
use Modules\ApplicantManagement\Services\ApplicantProfileService;
use Modules\ApplicantManagement\Services\ProfileDocumentService;
use Modules\SettingsManagement\Repositories\GeographyRepository;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A KYC Verifier entering a paper-based applicant's details on their behalf
 * — a walk-in who filled a physical form rather than registering online.
 *
 * Two steps: create the applicant's login account (store), then fill in
 * their KYC (editKyc/updateKyc), reusing the exact same profile-update
 * logic the applicant's own self-service form uses. Submitting forwards the
 * record straight to the Reviewer queue: the verifier who transcribed and
 * checked the paper form has already done the verify stage's job, so it
 * is recorded as verified by them rather than queued a second time.
 */
class StaffApplicantController extends Controller
{
    public function __construct(private ProfileRepository $profiles) {}

    public function create(): Response
    {
        return Inertia::render('Applicants/AddApplicant');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
        ]);

        $applicant = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make(Str::random(40)),
            ]);
            $user->assignRole('applicant');

            return $user;
        });

        // The applicant sets their own password via the standard reset-link
        // flow — nobody types or transmits a password on their behalf.
        Password::sendResetLink(['email' => $applicant->email]);

        return redirect()
            ->route('applicants.add.kyc.edit', $applicant)
            ->with('success', "Applicant account created for {$applicant->name}. A password-setup email has been sent to {$applicant->email}. Continue entering their KYC below.");
    }

    public function editKyc(Request $request, User $applicant, GeographyRepository $geography): Response
    {
        abort_unless($applicant->hasRole('applicant'), 404);

        $profile = $this->profiles->findByUserIdWithKyc($applicant->id);

        return Inertia::render('Applicants/StaffKycForm', [
            'applicant' => $applicant->only(['id', 'name', 'email']),
            'profile' => $profile,
            'completionPercent' => $profile?->completionPercent() ?? 0,
            'geography' => $geography->flat(),
            'options' => [
                'titles' => Title::options(),
                'genders' => Gender::options(),
                'maritalStatuses' => MaritalStatus::options(),
                'educationLevels' => EducationLevel::options(),
                'sourcesOfFunds' => SourceOfFunds::options(),
            ],
        ]);
    }

    public function updateKyc(ApplicantProfileUpdateRequest $request, User $applicant, ApplicantProfileService $applicantProfiles): RedirectResponse
    {
        abort_unless($applicant->hasRole('applicant'), 404);

        $applicantProfiles->update($applicant, $request);

        return back()->with('success', 'KYC details saved.');
    }

    /**
     * Submits the paper KYC and, in the same step, records the entering
     * verifier's own sign-off — forwarding it straight to the Reviewer.
     */
    public function submitAndVerify(Request $request, User $applicant, WorkflowService $workflow): RedirectResponse
    {
        abort_unless($applicant->hasRole('applicant'), 404);

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        $profile = $this->profiles->findByUserId($applicant->id);

        if (! $profile || ! $profile->isProfileComplete()) {
            return back()->withErrors([
                'profile' => 'Complete all required KYC fields (including documents and bank details) before forwarding to review.',
            ]);
        }

        if (! $profile->profile_status->isEditableByApplicant()) {
            return back()->withErrors([
                'profile' => 'This profile is already '.$profile->profile_status->labelEn().'.',
            ]);
        }

        if ($profile->profile_status !== ProfileStatus::Incomplete) {
            $profile->restartWorkflowCycle();
        }

        $profile->forceFill([
            'profile_status' => ProfileStatus::Submitted,
            'profile_submitted_at' => now(),
            'profile_rejection_reason' => null,
        ])->save();

        $workflow->act($profile, $request->user(), WorkflowAction::Approve, $validated['remarks'], $request);

        return redirect()
            ->route('applicants.review')
            ->with('success', "{$applicant->name}'s KYC has been forwarded to the review team.");
    }

    public function document(Request $request, User $applicant, string $type, ProfileDocumentService $documents): BinaryFileResponse
    {
        abort_unless($applicant->hasRole('applicant'), 404);

        $profile = $this->profiles->findByUserId($applicant->id);

        abort_unless($profile !== null, 404);

        return $documents->respond($profile, $type, $request->query('mode') === 'download');
    }
}
