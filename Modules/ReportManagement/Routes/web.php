<?php

use Illuminate\Support\Facades\Route;
use Modules\ReportManagement\Controllers\AdminReportsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/admin/reports', [AdminReportsController::class, 'index'])
        ->middleware('can:report.view')->name('admin.reports');
    Route::get('/admin/reports/export', [AdminReportsController::class, 'export'])
        ->middleware('can:report.view')->name('admin.reports.export');
});
