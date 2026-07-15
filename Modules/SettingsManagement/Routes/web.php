<?php

use Illuminate\Support\Facades\Route;
use Modules\SettingsManagement\Controllers\AdminSettingsController;

Route::middleware('auth')->group(function () {
    Route::get('/admin/settings', [AdminSettingsController::class, 'index'])
        ->middleware('can:settings.manage')->name('admin.settings');
});
