<?php

use Illuminate\Support\Facades\Route;
use Modules\UserManagement\Controllers\AdminCredentialsController;
use Modules\UserManagement\Controllers\AdminPanelController;
use Modules\UserManagement\Controllers\AdminUsersController;
use Modules\UserManagement\Controllers\ProfileController;

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/settings', [ProfileController::class, 'settings'])->name('settings.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/applicant', [ProfileController::class, 'updateApplicantProfile'])->name('profile.applicant.update');
    Route::get('/profile/documents/{type}', [ProfileController::class, 'document'])->name('profile.documents.show');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/admin/panel', [AdminPanelController::class, 'index'])->name('admin.panel');
    Route::get('/admin/roles/hub', [AdminPanelController::class, 'roleHub'])->name('admin.roles.hub');
    Route::get('/admin/users', [AdminUsersController::class, 'index'])->name('admin.users');
    Route::post('/admin/users', [AdminUsersController::class, 'store'])->name('admin.users.store');
    Route::patch('/admin/users/{user}', [AdminUsersController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [AdminUsersController::class, 'destroy'])->name('admin.users.destroy');
    Route::get('/admin/credentials', [AdminCredentialsController::class, 'index'])->name('admin.credentials');
    Route::patch('/admin/credentials/profile', [AdminCredentialsController::class, 'updateProfile'])->name('admin.credentials.profile.update');
    Route::patch('/admin/credentials/password', [AdminCredentialsController::class, 'updatePassword'])->name('admin.credentials.password.update');
});
