<?php

namespace Modules\VoucherManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Modules\VoucherManagement\Models\Voucher;
use Modules\VoucherManagement\Repositories\VoucherRepository;

class VoucherController extends Controller
{
    public function __construct(private VoucherRepository $vouchers) {}

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
                'payment_date' => $voucher->paymentTransaction?->payment_date?->format('Y-m-d'),
                'generated_at' => $voucher->generated_at?->format('Y-m-d'),
            ] : ['valid' => false];
        }

        return Inertia::render('Vouchers/Verify', [
            'code' => $code,
            'result' => $result,
        ]);
    }
}
