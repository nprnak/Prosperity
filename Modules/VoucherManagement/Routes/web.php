<?php

use Illuminate\Support\Facades\Route;
use Modules\VoucherManagement\Controllers\VoucherController;

// Public authenticity check, linked from the QR code on printed vouchers.
Route::get('/vouchers/verify', [VoucherController::class, 'verify'])
    ->middleware('throttle:30,1')->name('vouchers.verify');

Route::middleware(['auth', 'verified'])->group(function () {
    // VoucherPolicy::download — owner or voucher.download-any. The same
    // ability guards reading the receipt on screen: seeing it and taking a
    // copy of it are the same disclosure.
    Route::get('/vouchers/{voucher}', [VoucherController::class, 'show'])
        ->whereNumber('voucher')->middleware('can:download,voucher')->name('vouchers.show');
    Route::get('/vouchers/{voucher}/download', [VoucherController::class, 'download'])
        ->middleware('can:download,voucher')->name('vouchers.download');
});
