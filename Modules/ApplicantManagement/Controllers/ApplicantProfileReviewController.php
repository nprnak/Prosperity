<?php

namespace Modules\ApplicantManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\ApplicantManagement\Models\Applicant;
use Modules\ApplicantManagement\Notifications\ProfileApprovedNotification;
use Modules\ApplicantManagement\Notifications\ProfileRejectedNotification;
use Modules\ApplicantManagement\Requests\RejectProfileRequest;

class ApplicantProfileReviewController extends Controller
{
    public function queue(Request $request)
    {
        return Inertia::render('Applicants/ReviewQueue', [
            'pending' => Applicant::query()
                ->where('profile_status', Applicant::PROFILE_SUBMITTED)
                ->orderBy('profile_submitted_at')
                ->get(),
            'recentlyReviewed' => Applicant::query()
                ->whereIn('profile_status', [Applicant::PROFILE_APPROVED, Applicant::PROFILE_REJECTED])
                ->with('profileReviewer:id,name')
                ->latest('profile_reviewed_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function approve(Request $request, Applicant $applicant)
    {
        abort_unless($applicant->profile_status === Applicant::PROFILE_SUBMITTED, 422);

        $applicant->forceFill([
            'profile_status' => Applicant::PROFILE_APPROVED,
            'profile_reviewed_by' => $request->user()->id,
            'profile_reviewed_at' => now(),
            'profile_rejection_reason' => null,
        ])->save();

        $applicant->user?->notify(new ProfileApprovedNotification($applicant));

        return back()->with('success', 'Profile approved: '.$applicant->full_name_english);
    }

    public function reject(RejectProfileRequest $request, Applicant $applicant)
    {
        abort_unless($applicant->profile_status === Applicant::PROFILE_SUBMITTED, 422);

        $applicant->forceFill([
            'profile_status' => Applicant::PROFILE_REJECTED,
            'profile_reviewed_by' => $request->user()->id,
            'profile_reviewed_at' => now(),
            'profile_rejection_reason' => $request->validated('rejection_reason'),
        ])->save();

        $applicant->user?->notify(new ProfileRejectedNotification($applicant));

        return back()->with('success', 'Profile rejected: '.$applicant->full_name_english);
    }
}
