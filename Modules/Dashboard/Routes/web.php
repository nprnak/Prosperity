<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;
use Modules\Dashboard\Controllers\AdminDashboardController;

Route::get('/dashboard', function () {
    $user = auth()->user();

    if ($user?->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    }

    if ($user?->hasRole('finance_staff')) {
        // Finance reviews applications in the shared admin-style list.
        return redirect()->route('admin.applications');
    }

    if ($user?->hasRole('reviewer')) {
        return redirect()->route('reviewer.dashboard');
    }

    if ($user?->hasRole('verifier')) {
        return redirect()->route('verifier.dashboard');
    }

    if ($user?->hasRole('approver')) {
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
