<?php

namespace Modules\ApplicantManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ApplicantManagement\Models\Applicant;

class ApplicantProfileSubmissionController extends Controller
{
    /**
     * Applicant submits their own profile for KYC review.
     */
    public function store(Request $request)
    {
        $applicant = Applicant::query()->where('user_id', $request->user()->id)->first();

        if (! $applicant || ! $applicant->isProfileComplete()) {
            return back()->withErrors([
                'profile' => 'Please complete all required profile fields (including documents and bank details) before submitting for review.',
            ]);
        }

        if (! in_array($applicant->profile_status, [Applicant::PROFILE_DRAFT, Applicant::PROFILE_REJECTED], true)) {
            return back()->withErrors([
                'profile' => 'Your profile is already '.$applicant->profile_status.'.',
            ]);
        }

        $applicant->forceFill([
            'profile_status' => Applicant::PROFILE_SUBMITTED,
            'profile_submitted_at' => now(),
            'profile_rejection_reason' => null,
        ])->save();

        return back()->with('success', 'Profile submitted for review. You will be notified once it is approved.');
    }
}
