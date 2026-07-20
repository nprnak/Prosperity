<?php

namespace Modules\AllotmentManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Modules\AllotmentManagement\Exports\ShareholderRegisterExport;
use Modules\AllotmentManagement\Repositories\ShareAllotmentRepository;
use Modules\AllotmentManagement\Requests\StoreAllotmentRequest;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApplicationManagement\Repositories\ApplicationEventRepository;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;

class ShareAllotmentController extends Controller
{
    public function __construct(
        private ShareAllotmentRepository $allotments,
        private ApplicationEventRepository $events,
    ) {}

    public function index(Request $request, ShareApplicationRepository $applications)
    {
        $search = $request->string('q')->toString();
        $sort = $request->string('sort')->toString() ?: 'desc';

        return Inertia::render('Allotments/Register', [
            'allotments' => $this->allotments->register($search, $sort),
            'pendingApplications' => $applications->listByStatus(ApplicationStatus::Approved, ['applicant']),
            'totalShares' => $this->allotments->totalShares(),
            'filters' => ['q' => $search, 'sort' => $sort],
        ]);
    }

    public function store(StoreAllotmentRequest $request, ShareApplication $application)
    {
        abort_unless(in_array($application->status, [ApplicationStatus::Approved, ApplicationStatus::Allotted, ApplicationStatus::PartiallyAllotted], true), 422);

        $this->allotments->upsertForApplication($application, $request->validated());

        $targetStatus = (int) $request->validated('shares_allotted') < (int) $application->shares_applied
            ? ApplicationStatus::PartiallyAllotted
            : ApplicationStatus::Allotted;

        $fromStatus = $application->status;
        $application->update(['status' => $targetStatus]);

        $this->events->record($application, $request->user()->id, $fromStatus, $targetStatus,
            'Share allotment saved.',
            ['shares_allotted' => (int) $request->validated('shares_allotted')]);

        return back()->with('success', 'Share allotment saved.');
    }

    public function export()
    {
        return Excel::download(new ShareholderRegisterExport, 'shareholder-register.xlsx');
    }
}
