<?php

namespace Modules\VoucherManagement\Services;

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
    public function qrDataUri(Voucher $voucher): ?string
    {
        // Keep voucher generation available even if the QR package is
        // missing in an environment; the verify URL still prints as text.
        if (! class_exists('chillerlan\\QRCode\\QROptions')
            || ! class_exists('chillerlan\\QRCode\\Output\\QRGdImagePNG')
            || ! class_exists('chillerlan\\QRCode\\QRCode')) {
            return null;
        }

        $options = new \chillerlan\QRCode\QROptions;
        $options->outputInterface = \chillerlan\QRCode\Output\QRGdImagePNG::class;
        $options->scale = 4;
        $options->outputBase64 = true;

        return (new \chillerlan\QRCode\QRCode($options))->render($this->verificationUrl($voucher));
    }
}
