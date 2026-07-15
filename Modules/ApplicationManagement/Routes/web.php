<?php

use Illuminate\Support\Facades\Route;
use Modules\ApplicationManagement\Controllers\AdminApplicationsController;
use Modules\ApplicationManagement\Controllers\ApplicationWizardController;

Route::middleware('auth')->group(function () {
    Route::get('/applications/wizard', [ApplicationWizardController::class, 'index'])->name('applications.wizard');
    Route::post('/applications/draft', [ApplicationWizardController::class, 'storeDraft'])->name('applications.draft');
    Route::post('/applications/{application}/submit', [ApplicationWizardController::class, 'submit'])->name('applications.submit');

    Route::get('/admin/applications', [AdminApplicationsController::class, 'index'])->name('admin.applications');
    Route::get('/admin/applications/{application}', [AdminApplicationsController::class, 'show'])->name('admin.applications.show');
});
