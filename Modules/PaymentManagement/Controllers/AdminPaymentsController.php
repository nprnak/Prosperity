<?php

namespace Modules\PaymentManagement\Controllers;

use App\Http\Controllers\Controller;
use Modules\PaymentManagement\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminPaymentsController extends Controller
{
    public function index(Request $request)
    {
        $payments = PaymentTransaction::with('share_application.applicant')->latest()->get();

        $stats = [
            'verifiedAmount' => PaymentTransaction::where('verification_status', 'verified')->sum('amount'),
            'pendingCount' => PaymentTransaction::where('verification_status', 'pending')->count(),
            'totalCount' => PaymentTransaction::count(),
        ];

        return Inertia::render('Admin/Payments', [
            'payments' => $payments,
            'stats' => $stats,
        ]);
    }
}
