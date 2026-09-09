<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;
use Modules\Dashboard\Controllers\AdminDashboardController;

Route::get('/dashboard', function () {
    $user = auth()->user();

    if ($user?->hasRole('super_admin')) {
        return redirect()->route('admin.dashboard');
    }

    if ($user?->hasRole('finance_staff')) {
        // Finance reviews applications in the shared admin-style list.
        return redirect()->route('admin.applications');
    }

    // Stage staff land on the queue for the chain they work in. A user holding
    // roles in both chains sees the KYC queue first.
    if ($user?->hasAnyRole(['profile_verifier', 'profile_reviewer', 'profile_approver'])) {
        return redirect()->route('applicants.review');
    }

    // The three application stages share one queue, the way the KYC one does.
    if ($user?->hasAnyRole(['application_verifier', 'application_reviewer', 'application_approver'])) {
        return redirect()->route('applications.review');
    }

    return Inertia::render('Dashboard', [
        'applications' => app(ShareApplicationRepository::class)->listForUser($user->id),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])
        ->middleware('can:dashboard.view-admin')->name('admin.dashboard');
});
