<?php

namespace Modules\ApprovalManagement\Controllers;

use App\Http\Controllers\Controller;
use Modules\ApprovalManagement\Concerns\RecordsStageTransitions;
use Modules\ApprovalManagement\Requests\ApproveApplicationRequest;
use Modules\ApprovalManagement\Requests\RejectApplicationRequest;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\VoucherManagement\Models\Voucher;
use Modules\VoucherManagement\Services\VoucherQrService;
use Modules\ApprovalManagement\Notifications\ApplicationApprovedNotification;
use App\Services\NepaliAmountWordsService;
use App\Services\NumberGeneratorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ApproverController extends Controller
{
    use RecordsStageTransitions;

    public function dashboard(Request $request)
    {
        $applications = ShareApplication::query()
            ->where('status', ShareApplication::STATUS_VERIFIED)
            ->with(['applicant', 'paymentTransactions', 'reviewer', 'verifier'])
            ->latest()
            ->get();

        return Inertia::render('Approver/Dashboard', [
            'applications' => $applications,
        ]);
    }

    public function approve(ApproveApplicationRequest $request, ShareApplication $application, NumberGeneratorService $numbers, NepaliAmountWordsService $words, VoucherQrService $qr)
    {
        abort_unless($application->status === ShareApplication::STATUS_VERIFIED, 422);

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
            'verificationUrl' => $qr->verificationUrl($voucher),
            'verificationQr' => $qr->qrDataUri($voucher),
        ]);

        $path = 'vouchers/voucher-'.$voucher->voucher_number.'.pdf';
        Storage::disk('private')->put($path, $pdf->output());
        $voucher->update(['pdf_path' => $path]);

        $this->transition($request, $application, ShareApplication::STATUS_APPROVED,
            'Application approved by approver.',
            ['approved_by' => $request->user()->id, 'approved_at' => now(), 'rejection_reason' => null]);

        if ($application->applicant?->email) {
            Notification::route('mail', $application->applicant->email)
                ->notify(new ApplicationApprovedNotification($application, $voucher));
        }

        $application->applicant?->user?->notify(new ApplicationApprovedNotification($application, $voucher));

        return back()->with('success', 'Application approved and voucher generated.');
    }

    public function reject(RejectApplicationRequest $request, ShareApplication $application)
    {
        return $this->rejectAtStage($request, $application, 'approval');
    }
}
