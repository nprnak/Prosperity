<?php

namespace Modules\PaymentManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\PaymentManagement\Repositories\PaymentTransactionRepository;

class AdminPaymentsController extends Controller
{
    public function __construct(private PaymentTransactionRepository $payments)
    {
    }

    public function index(Request $request)
    {
        return Inertia::render('Admin/Payments', [
            'payments' => $this->payments->listForAdmin(),
            'stats' => [
                'verifiedAmount' => $this->payments->verifiedSum(),
                'pendingCount' => $this->payments->pendingCount(),
                'totalCount' => $this->payments->query()->count(),
            ],
        ]);
    }
}
