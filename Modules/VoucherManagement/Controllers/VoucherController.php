<?php

namespace Modules\VoucherManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Modules\VoucherManagement\Models\Voucher;
use Modules\VoucherManagement\Repositories\VoucherRepository;
use Modules\VoucherManagement\Services\ReceiptPresenter;

class VoucherController extends Controller
{
    public function __construct(private VoucherRepository $vouchers) {}

    /**
     * The receipt on screen, printable, reading from the same presenter the
     * PDF does so the two cannot say different things.
     */
    public function show(Voucher $voucher, ReceiptPresenter $receipt)
    {
        $data = $receipt->data($voucher);

        return Inertia::render('Vouchers/Show', [
            'receipt' => [
                'voucherNumber' => $voucher->voucher_number,
                'verificationCode' => $voucher->verification_code,
                'verificationUrl' => $data['verificationUrl'],
                'verificationQr' => $data['verificationQr'],
                'issuedOn' => $voucher->generated_at?->format('jS F, Y'),
                'receiptNumber' => $data['payment']?->receipt_number,
                'amount' => $data['payment']?->amount,
                'amountInWords' => $data['amountInEnglishWords'],
                'holdingIdNo' => $data['payment']?->holding_id_no,
                'holdingIdLabel' => $data['holdingIdLabel'],
                'applicantName' => $data['application']?->applicant?->full_name_en,
                'applicationNumber' => $data['application']?->application_number,
                'companyName' => $data['companyName'],
                'companyAddress' => $data['companyAddress'],
                'logoDataUri' => $data['logoDataUri'],
                'printedModes' => $data['printedModes'],
                'tickedMode' => $data['tickedMode'],
                'referenceLine' => $data['referenceLine'],
                'paymentDateLine' => $data['paymentDateLine'],
            ],
            'downloadUrl' => route('vouchers.download', $voucher->id),
        ]);
    }

    public function download(Voucher $voucher)
    {
        abort_unless($voucher->pdf_path, 404);

        return Storage::disk('private')->download($voucher->pdf_path, 'voucher-'.$voucher->voucher_number.'.pdf');
    }

    /**
     * Public voucher authenticity check — reachable without login via the
     * QR code / verification code printed on the receipt PDF.
     */
    public function verify(Request $request)
    {
        $code = strtoupper(trim((string) $request->query('code', '')));
        $result = null;

        if ($code !== '') {
            $voucher = $this->vouchers->findByVerificationCode($code);

            $result = $voucher ? [
                'valid' => true,
                'voucher_number' => $voucher->voucher_number,
                'application_number' => $voucher->paymentTransaction?->shareApplication?->application_number,
                'amount' => $voucher->paymentTransaction?->amount,
                // The latest deposit the receipt covers; a receipt may
                // acknowledge several, made on different days.
                'payment_date' => $voucher->paymentTransaction?->deposits
                    ->pluck('payment_date')->filter()->max()?->format('Y-m-d'),
                'generated_at' => $voucher->generated_at?->format('Y-m-d'),
            ] : ['valid' => false];
        }

        return Inertia::render('Vouchers/Verify', [
            'code' => $code,
            'result' => $result,
        ]);
    }
}
