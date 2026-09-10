<?php

use Illuminate\Support\Facades\Route;
use Modules\PaymentManagement\Controllers\AdminPaymentMethodsController;
use Modules\PaymentManagement\Controllers\AdminPaymentsController;
use Modules\PaymentManagement\Controllers\FinanceController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Read-only now: a payment is settled automatically when the application
    // it belongs to is approved (see ApproverController), so there is no
    // manual deposit- or receipt-level verification step left to route to.
    Route::get('/finance/dashboard', [FinanceController::class, 'dashboard'])
        ->middleware('can:payment.record')->name('finance.dashboard');

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
