<?php

namespace App\Http\Controllers;

use App\Exports\ShareholderRegisterExport;
use App\Http\Requests\Allotment\StoreAllotmentRequest;
use App\Models\ApplicationEvent;
use App\Models\ShareAllotment;
use App\Models\ShareApplication;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShareAllotmentController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['approver', 'admin']), 403);

        $search = $request->string('q')->toString();
        $sort = $request->string('sort')->toString() ?: 'desc';

        $allotments = ShareAllotment::query()
            ->with(['applicant', 'shareApplication'])
            ->when($search, fn ($q) => $q->whereHas('applicant', fn ($aq) => $aq->where('full_name_english', 'like', '%'.$search.'%')))
            ->orderBy('allotment_date', $sort === 'asc' ? 'asc' : 'desc')
            ->get();

        $pending = ShareApplication::query()
            ->where('status', ShareApplication::STATUS_APPROVED)
            ->with('applicant')
            ->get();

        $totalShares = (int) ShareAllotment::query()->sum('shares_allotted');

        return Inertia::render('Allotments/Register', [
            'allotments' => $allotments,
            'pendingApplications' => $pending,
            'totalShares' => $totalShares,
            'filters' => ['q' => $search, 'sort' => $sort],
        ]);
    }

    public function store(StoreAllotmentRequest $request, ShareApplication $application)
    {
        abort_unless(in_array($application->status, [ShareApplication::STATUS_APPROVED, ShareApplication::STATUS_ALLOTTED, ShareApplication::STATUS_PARTIALLY_ALLOTTED], true), 422);

        ShareAllotment::updateOrCreate(
            ['share_application_id' => $application->id],
            [
                ...$request->validated(),
                'applicant_id' => $application->applicant_id,
            ]
        );

        $targetStatus = (int) $request->validated('shares_allotted') < (int) $application->shares_applied
            ? ShareApplication::STATUS_PARTIALLY_ALLOTTED
            : ShareApplication::STATUS_ALLOTTED;

        $fromStatus = $application->status;
        $application->update(['status' => $targetStatus]);

        ApplicationEvent::query()->create([
            'share_application_id' => $application->id,
            'actor_id' => $request->user()->id,
            'from_status' => $fromStatus,
            'to_status' => $targetStatus,
            'remarks' => 'Share allotment saved.',
            'meta' => ['shares_allotted' => (int) $request->validated('shares_allotted')],
        ]);

        return back()->with('success', 'Share allotment saved.');
    }

    public function export()
    {
        return Excel::download(new ShareholderRegisterExport(), 'shareholder-register.xlsx');
    }
}
