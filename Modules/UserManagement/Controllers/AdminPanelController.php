<?php

namespace Modules\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\PaymentManagement\Models\PaymentTransaction;
use Modules\AllotmentManagement\Models\ShareAllotment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminPanelController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'totalUsers' => User::query()->count('*'),
            'adminUsers' => User::role('admin')->get()->count(),
            'financeUsers' => User::role('finance_staff')->get()->count(),
            'approverUsers' => User::role('approver')->get()->count(),
            'applicantUsers' => User::role('user')->get()->count(),
            'totalApplications' => ShareApplication::query()->count('*'),
            'pendingApplications' => ShareApplication::query()
                ->where(function ($query) {
                    $query->where('status', ShareApplication::STATUS_SUBMITTED)
                        ->orWhere('status', ShareApplication::STATUS_SENT_TO_BANK)
                        ->orWhere('status', ShareApplication::STATUS_BANK_ACCEPTED)
                        ->orWhere('status', ShareApplication::STATUS_BLOCKED)
                        ->orWhere('status', ShareApplication::STATUS_PAYMENT_PENDING)
                        ->orWhere('status', ShareApplication::STATUS_PAYMENT_VERIFIED);
                })
                ->count(),
            'pendingPayments' => PaymentTransaction::query()->where('verification_status', 'pending')->count(),
            'capitalRaised' => PaymentTransaction::query()->where('verification_status', 'verified')->sum('amount'),
            'verifiedPayments' => PaymentTransaction::query()->where('verification_status', 'verified')->count(),
            'totalSharesAllotted' => ShareAllotment::sum('shares_allotted'),
            'totalAllotments' => ShareAllotment::query()->count('*'),
            'averageSharesPerApp' => ShareAllotment::query()->count('*') > 0
                ? round(ShareAllotment::query()->sum('shares_allotted') / ShareAllotment::query()->count('*'))
                : 0,
        ];

        return Inertia::render('Admin/Panel', [
            'stats' => $stats,
        ]);
    }

    public function roleHub(Request $request)
    {
        $roleUsers = [
            'admin' => User::role('admin')
                ->with('roles:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'created_at']),
            'finance_staff' => User::role('finance_staff')
                ->with('roles:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'created_at']),
            'approver' => User::role('approver')
                ->with('roles:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'created_at']),
            'user' => User::role('user')
                ->with('roles:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'created_at']),
        ];

        $workflowCounts = [
            'pendingFinance' => PaymentTransaction::query()->where('verification_status', 'pending')->count(),
            'pendingApprovals' => ShareApplication::query()
                ->where('status', ShareApplication::STATUS_PAYMENT_VERIFIED)
                ->count(),
            'pendingAllotments' => ShareApplication::query()
                ->where('status', ShareApplication::STATUS_APPROVED)
                ->count(),
        ];

        return Inertia::render('Admin/RoleHub', [
            'roleUsers' => $roleUsers,
            'workflowCounts' => $workflowCounts,
        ]);
    }
}
