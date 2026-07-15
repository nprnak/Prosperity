<?php

use Illuminate\Support\Facades\Route;
use Modules\ApplicantManagement\Controllers\ApplicantProfileReviewController;
use Modules\ApplicantManagement\Controllers\ApplicantProfileSubmissionController;

Route::middleware('auth')->group(function () {
    // Applicant submits their own profile for KYC review.
    Route::post('/profile/submit', [ApplicantProfileSubmissionController::class, 'store'])
        ->name('profile.submit');

    Route::middleware('can:profile.review')->group(function () {
        Route::get('/applicants/review', [ApplicantProfileReviewController::class, 'queue'])->name('applicants.review');
        Route::post('/applicants/{applicant}/profile/approve', [ApplicantProfileReviewController::class, 'approve'])->name('applicants.profile.approve');
        Route::post('/applicants/{applicant}/profile/reject', [ApplicantProfileReviewController::class, 'reject'])->name('applicants.profile.reject');
    });
});
