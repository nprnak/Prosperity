<?php

use Illuminate\Support\Facades\Route;
use Modules\ApplicationManagement\Controllers\AdminApplicationsController;
use Modules\ApplicationManagement\Controllers\ApplicationWizardController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('can:application.submit')->group(function () {
        Route::get('/applications/wizard', [ApplicationWizardController::class, 'index'])->name('applications.wizard');
        Route::post('/applications/draft', [ApplicationWizardController::class, 'storeDraft'])->name('applications.draft');
        // Ownership of {application} is enforced by ShareApplicationPolicy::submit.
        Route::post('/applications/{application}/submit', [ApplicationWizardController::class, 'submit'])->name('applications.submit');
    });

    // Ownership of {application} is enforced by ShareApplicationPolicy::view.
    Route::get('/applications/{application}', [ApplicationWizardController::class, 'show'])
        ->whereNumber('application')->name('applications.show');
    Route::get('/applications/{application}/voucher-image', [ApplicationWizardController::class, 'voucherImage'])
        ->whereNumber('application')->name('applications.voucher-image');

    Route::middleware('can:application.view-any')->group(function () {
        Route::get('/admin/applications', [AdminApplicationsController::class, 'index'])->name('admin.applications');
        Route::get('/admin/applications/{application}', [AdminApplicationsController::class, 'show'])->name('admin.applications.show');
    });
});
