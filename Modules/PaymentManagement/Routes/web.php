<?php

use Illuminate\Support\Facades\Route;
use Modules\PaymentManagement\Controllers\AdminPaymentsController;
use Modules\PaymentManagement\Controllers\FinanceController;

Route::middleware('auth')->group(function () {
    Route::get('/finance/dashboard', [FinanceController::class, 'dashboard'])->name('finance.dashboard');
    Route::post('/finance/applications/{application}/payments', [FinanceController::class, 'storePayment'])->name('finance.payments.store');
    Route::post('/finance/payments/{payment}/verify', [FinanceController::class, 'verifyPayment'])->name('finance.payments.verify');

    Route::get('/admin/payments', [AdminPaymentsController::class, 'index'])->name('admin.payments');
});
