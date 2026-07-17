<?php

namespace Modules\PaymentManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApplicationManagement\Repositories\ApplicationEventRepository;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;
use Modules\PaymentManagement\Models\PaymentTransaction;
use Modules\PaymentManagement\Notifications\PaymentVerifiedNotification;
use Modules\PaymentManagement\Repositories\PaymentMethodRepository;
use Modules\PaymentManagement\Requests\StorePaymentRequest;
use Modules\PaymentManagement\Requests\VerifyPaymentRequest;

class FinanceController extends Controller
{
    public function __construct(
        private ShareApplicationRepository $applications,
        private ApplicationEventRepository $events,
    ) {
    }

    public function dashboard(Request $request, PaymentMethodRepository $paymentMethods)
    {
        $status = $request->string('status')->toString();

        $applications = $this->applications->listByStatus(
            $status ?: [
                ShareApplication::STATUS_SUBMITTED,
                ShareApplication::STATUS_SENT_TO_BANK,
                ShareApplication::STATUS_BANK_ACCEPTED,
                ShareApplication::STATUS_BLOCKED,
                ShareApplication::STATUS_PAYMENT_PENDING,
                ShareApplication::STATUS_PAYMENT_VERIFIED,
            ],
            ['applicant', 'paymentTransactions'],
        );

        return Inertia::render('Finance/Dashboard', [
            'applications' => $applications,
            'status' => $status,
            'paymentMethods' => $paymentMethods->active(['id', 'name']),
        ]);
    }

    public function storePayment(StorePaymentRequest $request, ShareApplication $application, NumberGeneratorService $numbers)
    {
        $payment = $application->paymentTransactions()->create([
            ...$request->validated(),
            'receipt_number' => $numbers->generateReceiptNumber(),
            'verification_status' => 'pending',
            'issued_by' => $request->user()->id,
        ]);

        if ($application->status === ShareApplication::STATUS_SUBMITTED) {
            $application->update([
                'status' => ShareApplication::STATUS_BLOCKED,
                'blocked_amount' => $payment->amount,
                'blocked_at' => now(),
            ]);

            $this->events->record($application, $request->user()->id,
                ShareApplication::STATUS_SUBMITTED, ShareApplication::STATUS_BLOCKED,
                'Payment recorded and marked as blocked by finance.');
        }

        return back()->with('success', 'Payment recorded: receipt '.$payment->receipt_number);
    }

    public function verifyPayment(VerifyPaymentRequest $request, PaymentTransaction $payment)
    {
        $status = $request->validated('status');

        $payment->update([
            'verification_status' => $status,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'notes' => $request->validated('notes'),
        ]);

        $application = $payment->shareApplication;
        $oldStatus = $application->status;
        $application->syncPaymentVerificationStatus();

        if ($application->status !== $oldStatus) {
            $this->events->record($application, $request->user()->id,
                $oldStatus, $application->status,
                'Payment verification updated by finance.');
        }

        if ($application->status === ShareApplication::STATUS_PAYMENT_VERIFIED) {
            if ($application->applicant?->email) {
                Notification::route('mail', $application->applicant->email)
                    ->notify(new PaymentVerifiedNotification($application));
            }

            $application->applicant?->user?->notify(new PaymentVerifiedNotification($application));
        }

        return back()->with('success', 'Payment verification updated.');
    }
}
