<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Support\Facades\Storage;

class VoucherController extends Controller
{
    public function download(Voucher $voucher)
    {
        abort_unless($voucher->pdf_path, 404);

        return Storage::disk('private')->download($voucher->pdf_path, 'voucher-'.$voucher->voucher_number.'.pdf');
    }
}
