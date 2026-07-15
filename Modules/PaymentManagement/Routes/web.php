<?php

use Illuminate\Support\Facades\Route;
use Modules\PaymentManagement\Controllers\AdminPaymentsController;
use Modules\PaymentManagement\Controllers\FinanceController;

Route::middleware('auth')->group(function () {
    Route::get('/finance/dashboard', [FinanceController::class, 'dashboard'])
        ->middleware('can:payment.record')->name('finance.dashboard');
    Route::post('/finance/applications/{application}/payments', [FinanceController::class, 'storePayment'])
        ->middleware('can:payment.record')->name('finance.payments.store');
    Route::post('/finance/payments/{payment}/verify', [FinanceController::class, 'verifyPayment'])
        ->middleware('can:payment.verify')->name('finance.payments.verify');

    Route::get('/admin/payments', [AdminPaymentsController::class, 'index'])
        ->middleware('can:payment.view-any')->name('admin.payments');
});
