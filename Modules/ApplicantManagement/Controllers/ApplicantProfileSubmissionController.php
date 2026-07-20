<?php

namespace Modules\ApplicantManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicantManagement\Repositories\ProfileRepository;

class ApplicantProfileSubmissionController extends Controller
{
    public function __construct(private ProfileRepository $profiles) {}

    /**
     * Applicant submits their own profile for KYC review.
     */
    public function store(Request $request)
    {
        $applicant = $this->profiles->findByUserId($request->user()->id);

        if (! $applicant || ! $applicant->isProfileComplete()) {
            return back()->withErrors([
                'profile' => 'Please complete all required profile fields (including documents and bank details) before submitting for review.',
            ]);
        }

        if (! $applicant->profile_status->isEditableByApplicant()) {
            return back()->withErrors([
                'profile' => 'Your profile is already '.$applicant->profile_status->labelEn().'.',
            ]);
        }

        // A profile coming back after a return or rejection starts a fresh
        // cycle, so earlier sign-offs no longer count toward the act-once rule
        // and three people must sign the corrected version.
        if ($applicant->profile_status !== ProfileStatus::Incomplete) {
            $applicant->restartWorkflowCycle();
        }

        $applicant->forceFill([
            'profile_status' => ProfileStatus::Submitted,
            'profile_submitted_at' => now(),
            'profile_rejection_reason' => null,
        ])->save();

        return back()->with('success', 'Profile submitted for review. You will be notified once it is approved.');
    }
}
