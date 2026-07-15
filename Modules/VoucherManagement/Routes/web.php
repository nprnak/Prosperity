<?php

use Illuminate\Support\Facades\Route;
use Modules\VoucherManagement\Controllers\VoucherController;

Route::middleware(['auth', 'verified'])->group(function () {
    // VoucherPolicy::download — owner or voucher.download-any.
    Route::get('/vouchers/{voucher}/download', [VoucherController::class, 'download'])
        ->middleware('can:download,voucher')->name('vouchers.download');
});
