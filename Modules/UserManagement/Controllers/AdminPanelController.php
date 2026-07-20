<?php

namespace Modules\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\AllotmentManagement\Repositories\ShareAllotmentRepository;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
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
    ) {}

    public function index(Request $request)
    {
        $totalAllotments = $this->allotments->allotmentCount();
        $totalSharesAllotted = $this->allotments->totalShares();

        $stats = [
            'totalUsers' => $this->users->query()->count(),
            'adminUsers' => $this->users->countByRole('super_admin'),
            'financeUsers' => $this->users->countByRole('finance_staff'),
            'approverUsers' => $this->users->countByRole('application_approver'),
            'applicantUsers' => $this->users->countByRole('applicant'),
            'totalApplications' => $this->applications->query()->count(),
            'pendingApplications' => $this->applications->countByStatus([
                ...ShareApplicationRepository::PENDING_STATUSES,
                ApplicationStatus::PaymentVerified,
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
            'super_admin' => $this->users->listByRole('super_admin'),
            'finance_staff' => $this->users->listByRole('finance_staff'),
            'profile_verifier' => $this->users->listByRole('profile_verifier'),
            'profile_reviewer' => $this->users->listByRole('profile_reviewer'),
            'profile_approver' => $this->users->listByRole('profile_approver'),
            'application_verifier' => $this->users->listByRole('application_verifier'),
            'application_reviewer' => $this->users->listByRole('application_reviewer'),
            'application_approver' => $this->users->listByRole('application_approver'),
            'applicant' => $this->users->listByRole('applicant'),
        ];

        $workflowCounts = [
            'pendingFinance' => $this->payments->pendingCount(),
            'pendingApprovals' => $this->applications->countByStatus(ApplicationStatus::PaymentVerified),
            'pendingAllotments' => $this->applications->countByStatus(ApplicationStatus::Approved),
        ];

        return Inertia::render('Admin/RoleHub', [
            'roleUsers' => $roleUsers,
            'workflowCounts' => $workflowCounts,
        ]);
    }
}
