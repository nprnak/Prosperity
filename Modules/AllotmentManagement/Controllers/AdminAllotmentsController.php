<?php

namespace Modules\AllotmentManagement\Controllers;

use App\Http\Controllers\Controller;
use Modules\AllotmentManagement\Models\ShareAllotment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminAllotmentsController extends Controller
{
    public function index(Request $request)
    {
        $allotments = ShareAllotment::with('shareApplication.applicant')->latest()->get();

        $stats = [
            'totalAllotted' => ShareAllotment::sum('shares_allotted'),
            'totalApplicants' => ShareAllotment::distinct('share_application_id')->count(),
            'averagePerApplicant' => $allotments->count() > 0 ? round(ShareAllotment::sum('shares_allotted') / ShareAllotment::distinct('share_application_id')->count()) : 0,
            // Price per share lives on the application, not the allotment.
            'totalRaised' => ShareAllotment::query()
                ->join('share_applications', 'share_applications.id', '=', 'share_allotments.share_application_id')
                ->sum(\DB::raw('share_allotments.shares_allotted * share_applications.amount_per_share')),
        ];

        return Inertia::render('Admin/Allotments', [
            'allotments' => $allotments,
            'stats' => $stats,
        ]);
    }
}
