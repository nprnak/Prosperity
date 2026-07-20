<?php

use Illuminate\Support\Facades\Route;
use Modules\ApprovalManagement\Controllers\ApproverController;
use Modules\ApprovalManagement\Controllers\ReviewerController;
use Modules\ApprovalManagement\Controllers\VerifierController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Application chain: verifier → reviewer → approver. Each stage has one
    // endpoint; the action (approve / reject / return / send back) travels in
    // the request body and WorkflowService decides whether it is allowed.
    Route::middleware('can:application.verify')->group(function () {
        Route::get('/verifier/dashboard', [VerifierController::class, 'dashboard'])->name('verifier.dashboard');
        Route::post('/verifier/applications/{application}/act', [VerifierController::class, 'act'])->name('verifier.applications.act');
    });

    Route::middleware('can:application.review')->group(function () {
        Route::get('/reviewer/dashboard', [ReviewerController::class, 'dashboard'])->name('reviewer.dashboard');
        Route::post('/reviewer/applications/{application}/act', [ReviewerController::class, 'act'])->name('reviewer.applications.act');
    });

    Route::middleware('can:application.approve')->group(function () {
        Route::get('/approver/dashboard', [ApproverController::class, 'dashboard'])->name('approver.dashboard');
        Route::post('/approver/applications/{application}/act', [ApproverController::class, 'act'])->name('approver.applications.act');
    });
});
