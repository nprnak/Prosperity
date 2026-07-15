<?php

use Illuminate\Support\Facades\Route;
use Modules\CompanyManagement\Controllers\AdminCompaniesController;

Route::middleware(['auth', 'verified', 'can:company.manage'])->group(function () {
    Route::get('/admin/companies', [AdminCompaniesController::class, 'index'])->name('admin.companies');
    Route::post('/admin/companies', [AdminCompaniesController::class, 'store'])->name('admin.companies.store');
    Route::patch('/admin/companies/{company}', [AdminCompaniesController::class, 'update'])->name('admin.companies.update');
    Route::delete('/admin/companies/{company}', [AdminCompaniesController::class, 'destroy'])->name('admin.companies.destroy');

    Route::post('/admin/companies/{company}/offerings', [AdminCompaniesController::class, 'storeOffering'])->name('admin.offerings.store');
    Route::patch('/admin/offerings/{offering}', [AdminCompaniesController::class, 'updateOffering'])->name('admin.offerings.update');
    Route::delete('/admin/offerings/{offering}', [AdminCompaniesController::class, 'destroyOffering'])->name('admin.offerings.destroy');
});
