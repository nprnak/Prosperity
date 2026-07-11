<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\VerifyPaymentRequest;
use App\Models\PaymentTransaction;
use App\Models\ShareApplication;
use App\Notifications\PaymentVerifiedNotification;
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
            $application->update(['status' => ShareApplication::STATUS_PAYMENT_PENDING]);
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
        $application->syncPaymentVerificationStatus();

        if ($application->status === ShareApplication::STATUS_PAYMENT_VERIFIED && $application->applicant?->email) {
            Notification::route('mail', $application->applicant->email)
                ->notify(new PaymentVerifiedNotification($application));
        }

        return back()->with('success', 'Payment verification updated.');
    }
}
