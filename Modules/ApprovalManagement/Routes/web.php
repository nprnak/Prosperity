<?php

use Illuminate\Support\Facades\Route;
use Modules\ApprovalManagement\Controllers\ApproverController;

Route::middleware('auth')->group(function () {
    Route::get('/approver/dashboard', [ApproverController::class, 'dashboard'])->name('approver.dashboard');
    Route::post('/approver/applications/{application}/approve', [ApproverController::class, 'approve'])->name('approver.applications.approve');
    Route::post('/approver/applications/{application}/reject', [ApproverController::class, 'reject'])->name('approver.applications.reject');
});
