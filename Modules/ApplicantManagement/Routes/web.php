<?php

use Illuminate\Support\Facades\Route;
use Modules\ApplicantManagement\Controllers\ApplicantFocalPersonController;
use Modules\ApplicantManagement\Controllers\ApplicantListController;
use Modules\ApplicantManagement\Controllers\ApplicantProfileReviewController;
use Modules\ApplicantManagement\Controllers\ApplicantProfileSubmissionController;
use Modules\ApplicantManagement\Controllers\StaffApplicantController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Applicant submits their own profile for KYC review.
    Route::post('/profile/submit', [ApplicantProfileSubmissionController::class, 'store'])
        ->name('profile.submit');

    // The full roster of approved applicants, open to either review chain —
    // an Application Verifier searching for who to file on behalf of needs
    // this same list, not just KYC staff.
    Route::middleware('permission:profile.verify|profile.review|profile.approve|application.verify|application.review|application.approve|application.view-any')
        ->get('/applicants/list', [ApplicantListController::class, 'index'])->name('applicants.index');

    // Attribution, not review — kept outside the KYC-stage group below so the
    // gating reads honestly: only focal-person.manage opens this, not the
    // reviewer permissions that open the detail page it is rendered on.
    Route::patch('/applicants/{applicant}/focal-person', [ApplicantFocalPersonController::class, 'update'])
        ->middleware('can:focal-person.manage')->name('applicants.focal-person.update');

    // Any KYC stage role reaches the queue and may act; WorkflowService
    // decides which records that person may actually act on. The application
    // chain has no business here — acting on a KYC profile isn't theirs.
    Route::middleware('permission:profile.verify|profile.review|profile.approve')->group(function () {
        Route::get('/applicants/review', [ApplicantProfileReviewController::class, 'queue'])->name('applicants.review');

        Route::post('/applicants/{applicant}/profile/act', [ApplicantProfileReviewController::class, 'act'])->name('applicants.profile.act');
    });

    // Viewing one profile (and its documents), though, is shared with the
    // application chain too — an Application Verifier/Reviewer/Approver
    // reasonably needs to see the KYC behind a share application, and the
    // Applicant List links here for both chains. Further gated by
    // ProfilePolicy::view, which allows the same permission set.
    Route::middleware('permission:profile.verify|profile.review|profile.approve|application.verify|application.review|application.approve')->group(function () {
        Route::get('/applicants/{applicant}/profile', [ApplicantProfileReviewController::class, 'show'])->name('applicants.profile.show');
        Route::get('/applicants/{applicant}/profile/documents/{type}', [ApplicantProfileReviewController::class, 'document'])->name('applicants.profile.documents.show');
        // One applicant's own application history, reached from their row on
        // the Applicant List.
        Route::get('/applicants/{applicant}/applications', [ApplicantListController::class, 'applications'])->name('applicants.applications');
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
