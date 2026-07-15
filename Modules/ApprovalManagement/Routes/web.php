<?php

use Illuminate\Support\Facades\Route;
use Modules\ApprovalManagement\Controllers\ApproverController;
use Modules\ApprovalManagement\Controllers\ReviewerController;
use Modules\ApprovalManagement\Controllers\VerifierController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('can:application.review')->group(function () {
        Route::get('/reviewer/dashboard', [ReviewerController::class, 'dashboard'])->name('reviewer.dashboard');
        Route::post('/reviewer/applications/{application}/review', [ReviewerController::class, 'review'])->name('reviewer.applications.review');
        Route::post('/reviewer/applications/{application}/reject', [ReviewerController::class, 'reject'])->name('reviewer.applications.reject');
    });

    Route::middleware('can:application.verify')->group(function () {
        Route::get('/verifier/dashboard', [VerifierController::class, 'dashboard'])->name('verifier.dashboard');
        Route::post('/verifier/applications/{application}/verify', [VerifierController::class, 'verify'])->name('verifier.applications.verify');
        Route::post('/verifier/applications/{application}/reject', [VerifierController::class, 'reject'])->name('verifier.applications.reject');
    });

    Route::get('/approver/dashboard', [ApproverController::class, 'dashboard'])
        ->middleware('can:application.approve')->name('approver.dashboard');
    Route::post('/approver/applications/{application}/approve', [ApproverController::class, 'approve'])
        ->middleware('can:application.approve')->name('approver.applications.approve');
    Route::post('/approver/applications/{application}/reject', [ApproverController::class, 'reject'])
        ->middleware('can:application.reject')->name('approver.applications.reject');
});
