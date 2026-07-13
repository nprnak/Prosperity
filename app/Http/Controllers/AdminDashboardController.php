<?php

namespace App\Http\Controllers;

use App\Models\ShareAllotment;
use App\Models\ShareApplication;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        $capitalRaised = (string) PaymentTransaction::query()
            ->where('verification_status', 'verified')
            ->sum('amount');

        $pendingApplications = ShareApplication::query()
            ->where(function ($query) {
                $query->where('status', ShareApplication::STATUS_SUBMITTED)
                    ->orWhere('status', ShareApplication::STATUS_SENT_TO_BANK)
                    ->orWhere('status', ShareApplication::STATUS_BANK_ACCEPTED)
                    ->orWhere('status', ShareApplication::STATUS_BLOCKED)
                    ->orWhere('status', ShareApplication::STATUS_PAYMENT_PENDING);
            })
            ->count();

        $pendingPaymentVerification = PaymentTransaction::query()
            ->where('verification_status', 'pending')
            ->count();

        $totalSharesAllotted = (int) ShareAllotment::query()->sum('shares_allotted');

        $series = PaymentTransaction::query()
            ->where('verification_status', 'verified')
            ->selectRaw('DATE(payment_date) as date, SUM(amount) as amount')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'metrics' => [
                'capitalRaised' => $capitalRaised,
                'pendingApplications' => $pendingApplications,
                'pendingPaymentVerification' => $pendingPaymentVerification,
                'totalSharesAllotted' => $totalSharesAllotted,
            ],
            'capitalSeries' => $series,
        ]);
    }
}
