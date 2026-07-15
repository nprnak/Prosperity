<?php

namespace Modules\ApprovalManagement\Controllers;

use App\Http\Controllers\Controller;
use Modules\ApprovalManagement\Requests\ApproveApplicationRequest;
use Modules\ApprovalManagement\Requests\RejectApplicationRequest;
use Modules\ApplicationManagement\Models\ApplicationEvent;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\VoucherManagement\Models\Voucher;
use Modules\ApprovalManagement\Notifications\ApplicationApprovedNotification;
use Modules\ApprovalManagement\Notifications\ApplicationRejectedNotification;
use App\Services\NepaliAmountWordsService;
use App\Services\NumberGeneratorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ApproverController extends Controller
{
    public function dashboard(Request $request)
    {
        $applications = ShareApplication::query()
            ->where('status', ShareApplication::STATUS_PAYMENT_VERIFIED)
            ->with(['applicant', 'paymentTransactions'])
            ->latest()
            ->get();

        return Inertia::render('Approver/Dashboard', [
            'applications' => $applications,
        ]);
    }

    public function approve(ApproveApplicationRequest $request, ShareApplication $application, NumberGeneratorService $numbers, NepaliAmountWordsService $words)
    {
        abort_unless($application->status === ShareApplication::STATUS_PAYMENT_VERIFIED, 422);

        $payment = $application->paymentTransactions()->where('verification_status', 'verified')->latest()->firstOrFail();
        $payment->update(['approved_by' => $request->user()->id]);

        $voucher = Voucher::create([
            'payment_transaction_id' => $payment->id,
            'voucher_number' => $numbers->generateVoucherNumber(),
            'generated_by' => $request->user()->id,
            'generated_at' => now(),
        ]);

        $pdf = Pdf::loadView('pdf.receipt', [
            'application' => $application->load('applicant'),
            'payment' => $payment,
            'voucher' => $voucher,
            'amountInWords' => $words->toWords($payment->amount),
        ]);

        $path = 'vouchers/voucher-'.$voucher->voucher_number.'.pdf';
        Storage::disk('private')->put($path, $pdf->output());
        $voucher->update(['pdf_path' => $path]);

        $fromStatus = $application->status;

        $application->update([
            'status' => ShareApplication::STATUS_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        ApplicationEvent::query()->create([
            'share_application_id' => $application->id,
            'actor_id' => $request->user()->id,
            'from_status' => $fromStatus,
            'to_status' => ShareApplication::STATUS_APPROVED,
            'remarks' => 'Application approved by approver.',
        ]);

        if ($application->applicant?->email) {
            Notification::route('mail', $application->applicant->email)
                ->notify(new ApplicationApprovedNotification($application, $voucher));
        }

        return back()->with('success', 'Application approved and voucher generated.');
    }

    public function reject(RejectApplicationRequest $request, ShareApplication $application)
    {
        $fromStatus = $application->status;

        $application->update([
            'status' => ShareApplication::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $request->validated('rejection_reason'),
        ]);

        ApplicationEvent::query()->create([
            'share_application_id' => $application->id,
            'actor_id' => $request->user()->id,
            'from_status' => $fromStatus,
            'to_status' => ShareApplication::STATUS_REJECTED,
            'remarks' => 'Application rejected by approver.',
            'meta' => ['reason' => $request->validated('rejection_reason')],
        ]);

        if ($application->applicant?->email) {
            Notification::route('mail', $application->applicant->email)
                ->notify(new ApplicationRejectedNotification($application));
        }

        return back()->with('success', 'Application rejected.');
    }
}
