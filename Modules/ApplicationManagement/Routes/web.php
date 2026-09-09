<?php

use Illuminate\Support\Facades\Route;
use Modules\ApplicationManagement\Controllers\AdminApplicationsController;
use Modules\ApplicationManagement\Controllers\ApplicationWizardController;
use Modules\ApplicationManagement\Controllers\StaffApplicationController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('can:application.submit')->group(function () {
        Route::get('/applications/wizard', [ApplicationWizardController::class, 'index'])->name('applications.wizard');
        Route::post('/applications/draft', [ApplicationWizardController::class, 'storeDraft'])->name('applications.draft');
        // Ownership of {application} is enforced by ShareApplicationPolicy::submit.
        Route::post('/applications/{application}/submit', [ApplicationWizardController::class, 'submit'])->name('applications.submit');
    });

    // An Application Verifier filing a paper application for an already
    // KYC-approved applicant. Gated on application.verify alone — this is
    // the verify stage's own work, done by transcription instead of
    // reviewing something already submitted.
    Route::middleware('permission:application.verify')->group(function () {
        Route::get('/applications/add', [StaffApplicationController::class, 'pickApplicant'])->name('applications.add.pick');
        Route::get('/applications/add/{applicant}', [StaffApplicationController::class, 'create'])->name('applications.add.create');
        Route::post('/applications/add/{applicant}/draft', [StaffApplicationController::class, 'storeDraft'])->name('applications.add.draft');
        Route::post('/applications/add/{applicant}/{application}/submit', [StaffApplicationController::class, 'submitAndVerify'])
            ->whereNumber('application')->name('applications.add.submit');
    });

    // Ownership of {application} is enforced by ShareApplicationPolicy::view.
    Route::get('/applications/{application}', [ApplicationWizardController::class, 'show'])
        ->whereNumber('application')->name('applications.show');
    Route::get('/applications/{application}/vouchers/{voucher}/image', [ApplicationWizardController::class, 'voucherImage'])
        ->whereNumber('application')->whereNumber('voucher')->name('applications.voucher-image');
    // Serves the applicant's own photo/signature to whoever the "view" gate
    // already lets onto this page — owner or staff previewer alike — unlike
    // profile.documents.show, which only ever serves the logged-in user's
    // own upload.
    Route::get('/applications/{application}/photo', [ApplicationWizardController::class, 'photo'])
        ->whereNumber('application')->name('applications.photo');
    Route::get('/applications/{application}/signature', [ApplicationWizardController::class, 'signature'])
        ->whereNumber('application')->name('applications.signature');

    // The bulk list is finance/admin territory — a review-chain stage never
    // needs to browse every application, only the one their own dashboard
    // sent them to, so it stays behind the blanket view-any permission alone.
    Route::middleware('can:application.view-any')->group(function () {
        Route::get('/admin/applications', [AdminApplicationsController::class, 'index'])->name('admin.applications');
    });

    // The single-application detail page (and the documents it embeds) is
    // also where each review stage reads the form before signing off, so it
    // accepts any stage's own permission — not just the blanket view-any
    // finance/admin hold — without handing a verifier the applications list.
    Route::middleware('permission:application.view-any|application.verify|application.review|application.approve')->group(function () {
        Route::get('/admin/applications/{application}', [AdminApplicationsController::class, 'show'])->name('admin.applications.show');
        // Citizenship scans for the admin-only "print document" action. Limited
        // to citizenship: viewing an application is not licence to see every
        // KYC document, which stays behind the profile-review permissions.
        Route::get('/admin/applications/{application}/citizenship/{side}', [AdminApplicationsController::class, 'citizenship'])
            ->whereNumber('application')->whereIn('side', ['front', 'back'])
            ->name('admin.applications.citizenship');
        // The reviewing stages need to see the applicant's photo and
        // signature too, alongside citizenship — not only the applicant's
        // own copy of the form (Applications/Show.vue).
        Route::get('/admin/applications/{application}/photo', [AdminApplicationsController::class, 'photo'])
            ->whereNumber('application')->name('admin.applications.photo');
        Route::get('/admin/applications/{application}/signature', [AdminApplicationsController::class, 'signature'])
            ->whereNumber('application')->name('admin.applications.signature');
    });
});
