<?php

use Illuminate\Support\Facades\Route;
use Modules\VoucherManagement\Controllers\VoucherController;

Route::middleware('auth')->group(function () {
    Route::get('/vouchers/{voucher}/download', [VoucherController::class, 'download'])->name('vouchers.download');
});
