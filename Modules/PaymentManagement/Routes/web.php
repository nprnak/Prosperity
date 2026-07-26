<?php

use Illuminate\Support\Facades\Route;
use Modules\PaymentManagement\Controllers\AdminPaymentMethodsController;
use Modules\PaymentManagement\Controllers\AdminPaymentsController;
use Modules\PaymentManagement\Controllers\FinanceController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/finance/dashboard', [FinanceController::class, 'dashboard'])
        ->middleware('can:payment.record')->name('finance.dashboard');
    // Each slip is checked on its own; the receipt is signed off once they all
    // are. There is no "record payment" route any more — submission already
    // creates the transaction and its deposits, so that one only ever produced
    // a duplicate.
    Route::post('/finance/deposits/{deposit}/verify', [FinanceController::class, 'verifyDeposit'])
        ->middleware('can:payment.verify')->name('finance.deposits.verify');
    Route::post('/finance/payments/{payment}/verify', [FinanceController::class, 'verifyPayment'])
        ->middleware('can:payment.verify')->name('finance.payments.verify');

    Route::get('/admin/payments', [AdminPaymentsController::class, 'index'])
        ->middleware('can:payment.view-any')->name('admin.payments');

    Route::middleware('can:payment-method.manage')->group(function () {
        Route::get('/admin/payment-methods', [AdminPaymentMethodsController::class, 'index'])->name('admin.payment-methods');
        Route::post('/admin/payment-methods', [AdminPaymentMethodsController::class, 'store'])->name('admin.payment-methods.store');
        Route::post('/admin/payment-methods/{method}', [AdminPaymentMethodsController::class, 'update'])->name('admin.payment-methods.update');
        Route::delete('/admin/payment-methods/{method}', [AdminPaymentMethodsController::class, 'destroy'])->name('admin.payment-methods.destroy');
    });

    // QR is visible to any verified user (applicants need it to pay).
    Route::get('/payment-methods/{method}/qr', [AdminPaymentMethodsController::class, 'qr'])->name('payment-methods.qr');
});
