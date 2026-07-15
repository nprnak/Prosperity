<?php

namespace Modules\PaymentManagement\Controllers;

use App\Http\Controllers\Controller;
use Modules\PaymentManagement\Requests\StorePaymentRequest;
use Modules\PaymentManagement\Requests\VerifyPaymentRequest;
use Modules\ApplicationManagement\Models\ApplicationEvent;
use Modules\PaymentManagement\Models\PaymentTransaction;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\PaymentManagement\Notifications\PaymentVerifiedNotification;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class FinanceController extends Controller
{
    public function dashboard(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['finance_staff', 'admin']), 403);

        $status = $request->string('status')->toString();

        $applications = ShareApplication::query()
            ->with(['applicant', 'paymentTransactions'])
            ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->whereIn('status', [
                ShareApplication::STATUS_SUBMITTED,
                ShareApplication::STATUS_SENT_TO_BANK,
                ShareApplication::STATUS_BANK_ACCEPTED,
                ShareApplication::STATUS_BLOCKED,
                ShareApplication::STATUS_PAYMENT_PENDING,
                ShareApplication::STATUS_PAYMENT_VERIFIED,
            ]))
            ->latest()
            ->get();

        return Inertia::render('Finance/Dashboard', [
            'applications' => $applications,
            'status' => $status,
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

            ApplicationEvent::query()->create([
                'share_application_id' => $application->id,
                'actor_id' => $request->user()->id,
                'from_status' => ShareApplication::STATUS_SUBMITTED,
                'to_status' => ShareApplication::STATUS_BLOCKED,
                'remarks' => 'Payment recorded and marked as blocked by finance.',
            ]);
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
            ApplicationEvent::query()->create([
                'share_application_id' => $application->id,
                'actor_id' => $request->user()->id,
                'from_status' => $oldStatus,
                'to_status' => $application->status,
                'remarks' => 'Payment verification updated by finance.',
            ]);
        }

        if ($application->status === ShareApplication::STATUS_PAYMENT_VERIFIED && $application->applicant?->email) {
            Notification::route('mail', $application->applicant->email)
                ->notify(new PaymentVerifiedNotification($application));
        }

        return back()->with('success', 'Payment verification updated.');
    }
}
