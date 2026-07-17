<?php

namespace Modules\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\AllotmentManagement\Repositories\ShareAllotmentRepository;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;
use Modules\PaymentManagement\Repositories\PaymentTransactionRepository;
use Modules\UserManagement\Repositories\UserRepository;

class AdminPanelController extends Controller
{
    public function __construct(
        private UserRepository $users,
        private ShareApplicationRepository $applications,
        private PaymentTransactionRepository $payments,
        private ShareAllotmentRepository $allotments,
    ) {
    }

    public function index(Request $request)
    {
        $totalAllotments = $this->allotments->allotmentCount();
        $totalSharesAllotted = $this->allotments->totalShares();

        $stats = [
            'totalUsers' => $this->users->query()->count(),
            'adminUsers' => $this->users->countByRole('admin'),
            'financeUsers' => $this->users->countByRole('finance_staff'),
            'approverUsers' => $this->users->countByRole('approver'),
            'applicantUsers' => $this->users->countByRole('user'),
            'totalApplications' => $this->applications->query()->count(),
            'pendingApplications' => $this->applications->countByStatus([
                ...ShareApplicationRepository::PENDING_STATUSES,
                ShareApplication::STATUS_PAYMENT_VERIFIED,
            ]),
            'pendingPayments' => $this->payments->pendingCount(),
            'capitalRaised' => $this->payments->verifiedSum(),
            'verifiedPayments' => $this->payments->verifiedCount(),
            'totalSharesAllotted' => $totalSharesAllotted,
            'totalAllotments' => $totalAllotments,
            'averageSharesPerApp' => $totalAllotments > 0 ? round($totalSharesAllotted / $totalAllotments) : 0,
        ];

        return Inertia::render('Admin/Panel', [
            'stats' => $stats,
        ]);
    }

    public function roleHub(Request $request)
    {
        $roleUsers = [
            'admin' => $this->users->listByRole('admin'),
            'finance_staff' => $this->users->listByRole('finance_staff'),
            'approver' => $this->users->listByRole('approver'),
            'user' => $this->users->listByRole('user'),
        ];

        $workflowCounts = [
            'pendingFinance' => $this->payments->pendingCount(),
            'pendingApprovals' => $this->applications->countByStatus(ShareApplication::STATUS_PAYMENT_VERIFIED),
            'pendingAllotments' => $this->applications->countByStatus(ShareApplication::STATUS_APPROVED),
        ];

        return Inertia::render('Admin/RoleHub', [
            'roleUsers' => $roleUsers,
            'workflowCounts' => $workflowCounts,
        ]);
    }
}
