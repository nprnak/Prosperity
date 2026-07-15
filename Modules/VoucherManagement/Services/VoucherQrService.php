<?php

namespace Modules\VoucherManagement\Services;

use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Modules\VoucherManagement\Models\Voucher;

class VoucherQrService
{
    public function verificationUrl(Voucher $voucher): string
    {
        return route('vouchers.verify', ['code' => $voucher->verification_code]);
    }

    /**
     * PNG data URI of a QR code pointing at the public verification URL,
     * suitable for embedding in the DomPDF receipt.
     */
    public function qrDataUri(Voucher $voucher): string
    {
        $options = new QROptions;
        $options->outputInterface = QRGdImagePNG::class;
        $options->scale = 4;
        $options->outputBase64 = true;

        return (new QRCode($options))->render($this->verificationUrl($voucher));
    }
}
