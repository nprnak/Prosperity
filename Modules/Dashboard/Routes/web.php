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

    if ($user?->hasRole('application_verifier')) {
        return redirect()->route('verifier.dashboard');
    }

    if ($user?->hasRole('application_reviewer')) {
        return redirect()->route('reviewer.dashboard');
    }

    if ($user?->hasRole('application_approver')) {
        return redirect()->route('approver.dashboard');
    }

    return Inertia::render('Dashboard', [
        'applications' => app(ShareApplicationRepository::class)->listForUser($user->id),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])
        ->middleware('can:dashboard.view-admin')->name('admin.dashboard');
});
