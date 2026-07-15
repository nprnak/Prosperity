<?php

use Illuminate\Support\Facades\Route;
use Modules\ApprovalManagement\Controllers\ApproverController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/approver/dashboard', [ApproverController::class, 'dashboard'])
        ->middleware('can:application.approve')->name('approver.dashboard');
    Route::post('/approver/applications/{application}/approve', [ApproverController::class, 'approve'])
        ->middleware('can:application.approve')->name('approver.applications.approve');
    Route::post('/approver/applications/{application}/reject', [ApproverController::class, 'reject'])
        ->middleware('can:application.reject')->name('approver.applications.reject');
});
