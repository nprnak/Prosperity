<?php

use Illuminate\Support\Facades\Route;
use Modules\ApplicantManagement\Controllers\ApplicantFocalPersonController;
use Modules\ApplicantManagement\Controllers\ApplicantProfileReviewController;
use Modules\ApplicantManagement\Controllers\ApplicantProfileSubmissionController;
use Modules\ApplicantManagement\Controllers\StaffApplicantController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Applicant submits their own profile for KYC review.
    Route::post('/profile/submit', [ApplicantProfileSubmissionController::class, 'store'])
        ->name('profile.submit');

    // Attribution, not review — kept outside the KYC-stage group below so the
    // gating reads honestly: only focal-person.manage opens this, not the
    // reviewer permissions that open the detail page it is rendered on.
    Route::patch('/applicants/{applicant}/focal-person', [ApplicantFocalPersonController::class, 'update'])
        ->middleware('can:focal-person.manage')->name('applicants.focal-person.update');

    // Any KYC stage role reaches the queue; WorkflowService decides which
    // records that person may actually act on.
    Route::middleware('permission:profile.verify|profile.review|profile.approve')->group(function () {
        Route::get('/applicants/review', [ApplicantProfileReviewController::class, 'queue'])->name('applicants.review');

        // The detail page and its documents are further gated by ProfilePolicy::view.
        Route::get('/applicants/{applicant}/profile', [ApplicantProfileReviewController::class, 'show'])->name('applicants.profile.show');
        Route::get('/applicants/{applicant}/profile/documents/{type}', [ApplicantProfileReviewController::class, 'document'])->name('applicants.profile.documents.show');

        Route::post('/applicants/{applicant}/profile/act', [ApplicantProfileReviewController::class, 'act'])->name('applicants.profile.act');
    });

    // Correcting an already-approved profile is the approver's to do, not any
    // stage's: it edits a record the chain has finished with, so it sits
    // outside the group above and is gated on profile.approve alone.
    Route::patch('/applicants/{applicant}/profile', [ApplicantProfileReviewController::class, 'amend'])
        ->middleware('can:profile.approve')->name('applicants.profile.amend');

    // A KYC Verifier entering a walk-in applicant's paper KYC form. Gated on
    // profile.verify alone — this is the verify stage's own work, done by
    // transcription instead of reviewing something already submitted.
    Route::middleware('permission:profile.verify')->group(function () {
        Route::get('/applicants/add', [StaffApplicantController::class, 'create'])->name('applicants.add.create');
        Route::post('/applicants/add', [StaffApplicantController::class, 'store'])->name('applicants.add.store');
        Route::get('/applicants/{applicant}/kyc', [StaffApplicantController::class, 'editKyc'])->name('applicants.add.kyc.edit');
        Route::patch('/applicants/{applicant}/kyc', [StaffApplicantController::class, 'updateKyc'])->name('applicants.add.kyc.update');
        Route::post('/applicants/{applicant}/kyc/submit', [StaffApplicantController::class, 'submitAndVerify'])->name('applicants.add.kyc.submit');
        Route::get('/applicants/{applicant}/kyc/documents/{type}', [StaffApplicantController::class, 'document'])->name('applicants.add.kyc.documents.show');
    });
});
