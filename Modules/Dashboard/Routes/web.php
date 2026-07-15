<?php

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Controllers\AdminDashboardController;

Route::get('/dashboard', function () {
    $user = auth()->user();

    if ($user?->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    }

    if ($user?->hasRole('finance_staff')) {
        return redirect()->route('finance.dashboard');
    }

    if ($user?->hasRole('approver')) {
        return redirect()->route('approver.dashboard');
    }

    return redirect()->route('applications.wizard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
});
