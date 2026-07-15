<?php

use Illuminate\Support\Facades\Route;
use Modules\ReportManagement\Controllers\AdminReportsController;

Route::middleware('auth')->group(function () {
    Route::get('/admin/reports', [AdminReportsController::class, 'index'])
        ->middleware('can:report.view')->name('admin.reports');
});
