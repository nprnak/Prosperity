<?php

namespace Modules\VoucherManagement\Services;

use App\Services\NepaliAmountWordsService;
use App\Services\NumberGeneratorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\PaymentManagement\Models\PaymentTransaction;
use Modules\VoucherManagement\Models\Voucher;
use Modules\VoucherManagement\Repositories\VoucherRepository;

/**
 * Issues the payment voucher for an approved application: creates the
 * voucher record and renders + stores its receipt PDF.
 */
class VoucherIssueService
{
    public function __construct(
        private VoucherRepository $vouchers,
        private NumberGeneratorService $numbers,
        private NepaliAmountWordsService $words,
        private VoucherQrService $qr,
    ) {
    }

    public function issue(ShareApplication $application, PaymentTransaction $payment, int $generatedBy): Voucher
    {
        /** @var Voucher $voucher */
        $voucher = $this->vouchers->create([
            'payment_transaction_id' => $payment->id,
            'voucher_number' => $this->numbers->generateVoucherNumber(),
            'generated_by' => $generatedBy,
            'generated_at' => now(),
        ]);

        $pdf = Pdf::loadView('pdf.receipt', [
            'application' => $application->load('applicant'),
            'payment' => $payment,
            'voucher' => $voucher,
            'amountInWords' => $this->words->toWords($payment->amount),
            'verificationUrl' => $this->qr->verificationUrl($voucher),
            'verificationQr' => $this->qr->qrDataUri($voucher),
        ]);

        $path = 'vouchers/voucher-'.$voucher->voucher_number.'.pdf';
        Storage::disk('private')->put($path, $pdf->output());
        $voucher->update(['pdf_path' => $path]);

        return $voucher;
    }
}
