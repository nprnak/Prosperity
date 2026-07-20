<?php

use Illuminate\Support\Facades\Route;
use Modules\ApplicantManagement\Controllers\ApplicantProfileReviewController;
use Modules\ApplicantManagement\Controllers\ApplicantProfileSubmissionController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Applicant submits their own profile for KYC review.
    Route::post('/profile/submit', [ApplicantProfileSubmissionController::class, 'store'])
        ->name('profile.submit');

    // Any KYC stage role reaches the queue; WorkflowService decides which
    // records that person may actually act on.
    Route::middleware('permission:profile.verify|profile.review|profile.approve')->group(function () {
        Route::get('/applicants/review', [ApplicantProfileReviewController::class, 'queue'])->name('applicants.review');
        Route::post('/applicants/{applicant}/profile/act', [ApplicantProfileReviewController::class, 'act'])->name('applicants.profile.act');
    });
});
