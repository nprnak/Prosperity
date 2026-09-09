<?php

namespace Modules\VoucherManagement\Services;

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
        private ReceiptPresenter $receipt,
    ) {}

    public function issue(ShareApplication $application, PaymentTransaction $payment, int $generatedBy): Voucher
    {
        // The receipt number is claimed here, with the receipt — not at
        // submission, where one was taken per declared deposit and burned even
        // on applications that were never approved.
        if (! $payment->receipt_number) {
            $companyCode = $application->offering?->company?->code ?? 'PHL';
            $payment->forceFill(['receipt_number' => $this->numbers->generateReceiptNumber($companyCode)])->save();
        }

        /** @var Voucher $voucher */
        $voucher = $this->vouchers->create([
            'payment_transaction_id' => $payment->id,
            'voucher_number' => $this->numbers->generateVoucherNumber(),
            'generated_by' => $generatedBy,
            'generated_at' => now(),
        ]);

        // Landscape, because the receipt book is: the original is wider than
        // it is tall, and a portrait render of it reads as a different
        // document.
        $pdf = Pdf::loadView('pdf.receipt', $this->receipt->data($voucher->refresh()))
            ->setPaper('a4', 'landscape');

        $path = 'vouchers/voucher-'.$voucher->voucher_number.'.pdf';
        Storage::disk('private')->put($path, $pdf->output());
        $voucher->update(['pdf_path' => $path]);

        return $voucher;
    }
}
