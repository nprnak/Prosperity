<?php

use Illuminate\Support\Facades\Route;
use Modules\AuditLogManagement\Controllers\AdminLogsController;

Route::middleware('auth')->group(function () {
    Route::get('/admin/logs', [AdminLogsController::class, 'index'])
        ->middleware('can:audit.view')->name('admin.logs');
});
