<?php

use Illuminate\Support\Facades\Route;
use Modules\CompanyManagement\Controllers\AdminCompaniesController;

// Logo is visible to any verified user (it appears on application forms).
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/companies/{company}/logo', [AdminCompaniesController::class, 'logo'])->name('companies.logo');
});

Route::middleware(['auth', 'verified', 'can:company.manage'])->group(function () {
    Route::get('/admin/companies', [AdminCompaniesController::class, 'index'])->name('admin.companies');
    Route::post('/admin/companies', [AdminCompaniesController::class, 'store'])->name('admin.companies.store');
    // POST (not PATCH) so multipart logo uploads work without method spoofing.
    Route::post('/admin/companies/{company}', [AdminCompaniesController::class, 'update'])->name('admin.companies.update');
    Route::delete('/admin/companies/{company}', [AdminCompaniesController::class, 'destroy'])->name('admin.companies.destroy');

    Route::post('/admin/companies/{company}/offerings', [AdminCompaniesController::class, 'storeOffering'])->name('admin.offerings.store');
    Route::patch('/admin/offerings/{offering}', [AdminCompaniesController::class, 'updateOffering'])->name('admin.offerings.update');
    Route::delete('/admin/offerings/{offering}', [AdminCompaniesController::class, 'destroyOffering'])->name('admin.offerings.destroy');
});
