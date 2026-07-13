<?php

namespace App\Http\Controllers;

use App\Models\ShareAllotment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminAllotmentsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        $allotments = ShareAllotment::with('share_application.applicant')->latest()->get();

        $stats = [
            'totalAllotted' => ShareAllotment::sum('shares_allotted'),
            'totalApplicants' => ShareAllotment::distinct('share_application_id')->count(),
            'averagePerApplicant' => $allotments->count() > 0 ? round(ShareAllotment::sum('shares_allotted') / ShareAllotment::distinct('share_application_id')->count()) : 0,
            'totalRaised' => ShareAllotment::sum(\DB::raw('shares_allotted * cost_per_share')),
        ];

        return Inertia::render('Admin/Allotments', [
            'allotments' => $allotments,
            'stats' => $stats,
        ]);
    }
}
