<?php

namespace Modules\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\AllotmentManagement\Repositories\ShareAllotmentRepository;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;
use Modules\PaymentManagement\Repositories\PaymentTransactionRepository;

class AdminDashboardController extends Controller
{
    public function __construct(
        private ShareApplicationRepository $applications,
        private PaymentTransactionRepository $payments,
        private ShareAllotmentRepository $allotments,
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
        ]);
    }
}
