<?php

use Illuminate\Support\Facades\Route;
use Modules\SettingsManagement\Controllers\AdminSettingsController;

Route::middleware(['auth', 'verified', 'can:settings.manage'])->group(function () {
    Route::get('/admin/settings', [AdminSettingsController::class, 'index'])->name('admin.settings');
    Route::put('/admin/settings', [AdminSettingsController::class, 'update'])->name('admin.settings.update');
});
