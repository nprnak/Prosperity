<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ShareApplication;
use App\Models\PaymentTransaction;
use App\Models\ShareAllotment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class AdminPanelController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        $stats = [
            'totalUsers' => User::count(),
            'totalApplications' => ShareApplication::count(),
            'pendingApplications' => ShareApplication::where('status', 'submitted')->count(),
            'pendingPayments' => PaymentTransaction::where('verification_status', 'pending')->count(),
            'capitalRaised' => PaymentTransaction::where('verification_status', 'verified')->sum('amount'),
            'verifiedPayments' => PaymentTransaction::where('verification_status', 'verified')->count(),
            'totalSharesAllotted' => ShareAllotment::sum('shares_allotted'),
            'totalAllotments' => ShareAllotment::count(),
            'averageSharesPerApp' => ShareAllotment::count() > 0 ? round(ShareAllotment::sum('shares_allotted') / ShareAllotment::count()) : 0,
        ];

        return Inertia::render('Admin/Panel', [
            'stats' => $stats,
        ]);
    }
}
