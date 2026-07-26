<?php

use Illuminate\Support\Facades\Route;
use Modules\ReportManagement\Controllers\AdminReportsController;
use Modules\ReportManagement\Controllers\AdminReportViewController;
use Modules\ReportManagement\Reports\ReportRegistry;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/admin/reports', [AdminReportsController::class, 'index'])
        ->middleware('can:report.view')->name('admin.reports');
    Route::get('/admin/reports/export', [AdminReportsController::class, 'export'])
        ->middleware('can:report.view')->name('admin.reports.export');

    // The prescribed report formats. Constrained to the registry's slugs so an
    // unknown report 404s at routing, and so neither route above is shadowed.
    Route::middleware('can:report.view')->group(function () {
        Route::get('/admin/reports/{report}', [AdminReportViewController::class, 'show'])
            ->whereIn('report', array_keys(ReportRegistry::REPORTS))
            ->name('admin.reports.show');
        Route::get('/admin/reports/{report}/export', [AdminReportViewController::class, 'export'])
            ->whereIn('report', array_keys(ReportRegistry::REPORTS))
            ->name('admin.reports.view.export');
    });
});
