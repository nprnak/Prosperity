<?php

use Illuminate\Support\Facades\Route;
use Modules\VoucherManagement\Controllers\VoucherController;

// Public authenticity check, linked from the QR code on printed vouchers.
Route::get('/vouchers/verify', [VoucherController::class, 'verify'])
    ->middleware('throttle:30,1')->name('vouchers.verify');

Route::middleware(['auth', 'verified'])->group(function () {
    // VoucherPolicy::download — owner or voucher.download-any.
    Route::get('/vouchers/{voucher}/download', [VoucherController::class, 'download'])
        ->middleware('can:download,voucher')->name('vouchers.download');
});
