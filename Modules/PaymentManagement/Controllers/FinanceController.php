<?php

namespace Modules\PaymentManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;
use Modules\PaymentManagement\Repositories\PaymentMethodRepository;

/**
 * Read-only view of what finance has coming in.
 *
 * A payment is settled automatically once the application it belongs to is
 * approved (ApproverController marks every one of its transactions verified
 * at that point) rather than through a separate finance sign-off, so this
 * page exists only to show where money stands while an application is still
 * moving through the review chain.
 */
class FinanceController extends Controller
{
    public function __construct(private ShareApplicationRepository $applications) {}

    public function dashboard(Request $request, PaymentMethodRepository $paymentMethods)
    {
        $status = $request->string('status')->toString();

        $applications = $this->applications->listByStatus(
            $status ?: [
                ApplicationStatus::Submitted,
                ApplicationStatus::SentToBank,
                ApplicationStatus::BankAccepted,
                ApplicationStatus::Blocked,
                ApplicationStatus::PaymentPending,
                ApplicationStatus::PaymentVerified,
            ],
            ['applicant', 'paymentTransactions.deposits', 'paymentTransactions.checker:id,name',
                'paymentTransactions.verifier:id,name', 'vouchers'],
        );

        return Inertia::render('Finance/Dashboard', [
            'applications' => $applications,
            'status' => $status,
            'paymentMethods' => $paymentMethods->active(['id', 'name']),
        ]);
    }
}
