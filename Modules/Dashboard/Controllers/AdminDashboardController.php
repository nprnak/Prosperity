<?php

namespace Modules\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\AllotmentManagement\Repositories\ShareAllotmentRepository;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;
use Modules\PaymentManagement\Repositories\PaymentTransactionRepository;
use Modules\ReportManagement\Reports\FocalPersonReport;

class AdminDashboardController extends Controller
{
    public function __construct(
        private ShareApplicationRepository $applications,
        private PaymentTransactionRepository $payments,
        private ShareAllotmentRepository $allotments,
        private FocalPersonReport $focalPersons,
    ) {}

    public function index(Request $request)
    {
        return Inertia::render('Admin/Dashboard', [
            'metrics' => [
                'capitalRaised' => $this->payments->verifiedSum(),
                'pendingApplications' => $this->applications->countByStatus(ShareApplicationRepository::PENDING_STATUSES),
                'pendingPaymentVerification' => $this->payments->pendingCount(),
                'totalSharesAllotted' => $this->allotments->totalShares(),
            ],
            'capitalSeries' => $this->payments->verifiedDailySeries(),
            // Note 4 of the Focal Personwise format: the same figures belong on
            // the dashboard, not only in the report.
            'focalPersons' => $request->user()->can('report.view')
                ? $this->focalPersons->dashboardSummary()
                : [],
        ]);
    }
}
