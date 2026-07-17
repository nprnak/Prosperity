<?php

namespace Modules\ApplicantManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicantManagement\Notifications\ProfileApprovedNotification;
use Modules\ApplicantManagement\Notifications\ProfileRejectedNotification;
use Modules\ApplicantManagement\Repositories\ProfileRepository;
use Modules\ApplicantManagement\Requests\RejectProfileRequest;

class ApplicantProfileReviewController extends Controller
{
    public function __construct(private ProfileRepository $profiles)
    {
    }

    public function queue(Request $request)
    {
        return Inertia::render('Applicants/ReviewQueue', [
            'pending' => $this->profiles->pendingReviewQueue(),
            'recentlyReviewed' => $this->profiles->recentlyReviewed(),
        ]);
    }

    public function approve(Request $request, Profile $applicant)
    {
        abort_unless($applicant->profile_status === Profile::PROFILE_SUBMITTED, 422);

        $applicant->forceFill([
            'profile_status' => Profile::PROFILE_APPROVED,
            'profile_reviewed_by' => $request->user()->id,
            'profile_reviewed_at' => now(),
            'profile_rejection_reason' => null,
        ])->save();

        $applicant->user?->notify(new ProfileApprovedNotification($applicant));

        return back()->with('success', 'Profile approved: '.$applicant->full_name_en);
    }

    public function reject(RejectProfileRequest $request, Profile $applicant)
    {
        abort_unless($applicant->profile_status === Profile::PROFILE_SUBMITTED, 422);

        $applicant->forceFill([
            'profile_status' => Profile::PROFILE_REJECTED,
            'profile_reviewed_by' => $request->user()->id,
            'profile_reviewed_at' => now(),
            'profile_rejection_reason' => $request->validated('rejection_reason'),
        ])->save();

        $applicant->user?->notify(new ProfileRejectedNotification($applicant));

        return back()->with('success', 'Profile rejected: '.$applicant->full_name_en);
    }
}
