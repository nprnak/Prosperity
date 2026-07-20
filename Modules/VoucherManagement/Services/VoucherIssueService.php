<?php

namespace Modules\VoucherManagement\Services;

use App\Services\NepaliAmountWordsService;
use App\Services\NumberGeneratorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\PaymentManagement\Models\PaymentTransaction;
use Modules\SettingsManagement\Models\Setting;
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
    ) {}

    public function issue(ShareApplication $application, PaymentTransaction $payment, int $generatedBy): Voucher
    {
        /** @var Voucher $voucher */
        $voucher = $this->vouchers->create([
            'payment_transaction_id' => $payment->id,
            'voucher_number' => $this->numbers->generateVoucherNumber(),
            'generated_by' => $generatedBy,
            'generated_at' => now(),
        ]);

        $application->load(['applicant', 'offering.company']);
        $company = $application->offering?->company;

        $logoDataUri = null;
        if ($company?->logo_path && Storage::disk('private')->exists($company->logo_path)) {
            $logoDataUri = 'data:'.Storage::disk('private')->mimeType($company->logo_path)
                .';base64,'.base64_encode(Storage::disk('private')->get($company->logo_path));
        }

        $pdf = Pdf::loadView('pdf.receipt', [
            'application' => $application,
            'payment' => $payment,
            'voucher' => $voucher,
            'companyName' => $company?->name ?? Setting::get('org_name', 'Prosperity Holdings Limited'),
            'companyAddress' => $company?->address ?? Setting::get('org_address'),
            'logoDataUri' => $logoDataUri,
            'amountInEnglishWords' => $this->words->toEnglishWords($payment->amount),
            'verificationUrl' => $this->qr->verificationUrl($voucher),
            'verificationQr' => $this->qr->qrDataUri($voucher),
        ]);

        $path = 'vouchers/voucher-'.$voucher->voucher_number.'.pdf';
        Storage::disk('private')->put($path, $pdf->output());
        $voucher->update(['pdf_path' => $path]);

        return $voucher;
    }
}
