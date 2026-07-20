<?php

namespace Modules\PaymentManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
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
    ) {}

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

        return back()->with('success', 'Payment recorded: receipt '.$payment->receipt_number);
    }

    /**
     * Two-officer sign-off: the first "verified" click checks the payment
     * (checked_by); a second, different finance officer's click re-verifies it
     * (verified_by) and only then does the payment count as verified.
     */
    public function verifyPayment(VerifyPaymentRequest $request, PaymentTransaction $payment)
    {
        $status = $request->validated('status');

        if ($status === 'verified' && ! $payment->checked_by) {
            $payment->update([
                'checked_by' => $request->user()->id,
                'checked_at' => now(),
                'notes' => $request->validated('notes'),
            ]);

            return back()->with('success', 'Payment checked. Awaiting re-verification by another finance officer.');
        }

        if ($status === 'verified') {
            abort_if($payment->checked_by === $request->user()->id, 422,
                'A different finance officer must re-verify this payment.');
        }

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

        if ($application->status === ApplicationStatus::PaymentVerified) {
            if ($application->applicant?->email) {
                Notification::route('mail', $application->applicant->email)
                    ->notify(new PaymentVerifiedNotification($application));
            }

            $application->applicant?->user?->notify(new PaymentVerifiedNotification($application));
        }

        return back()->with('success', 'Payment verification updated.');
    }
}
