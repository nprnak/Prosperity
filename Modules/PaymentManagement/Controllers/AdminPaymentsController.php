<?php

namespace Modules\PaymentManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\PaymentManagement\Repositories\PaymentTransactionRepository;

class AdminPaymentsController extends Controller
{
    public function __construct(private PaymentTransactionRepository $payments) {}

    public function index(Request $request)
    {
        $status = $request->string('status')->toString();

        if (! in_array($status, ['pending', 'verified', 'rejected'], true)) {
            $status = '';
        }

        return Inertia::render('Admin/Payments', [
            'payments' => $this->payments->listForAdmin($status ?: null),
            'filters' => ['status' => $status],
            'stats' => [
                'verifiedAmount' => $this->payments->verifiedSum(),
                'pendingCount' => $this->payments->pendingCount(),
                'totalCount' => $this->payments->query()->count(),
            ],
        ]);
    }
}
