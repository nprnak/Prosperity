<?php

use Illuminate\Support\Facades\Route;
use Modules\AllotmentManagement\Controllers\AdminAllotmentsController;
use Modules\AllotmentManagement\Controllers\ShareAllotmentController;

Route::middleware('auth')->group(function () {
    Route::middleware('can:allotment.manage')->group(function () {
        Route::get('/allotments/register', [ShareAllotmentController::class, 'index'])->name('allotments.register');
        Route::post('/allotments/{application}', [ShareAllotmentController::class, 'store'])->name('allotments.store');
        Route::get('/allotments/export', [ShareAllotmentController::class, 'export'])->name('allotments.export');
    });

    Route::get('/admin/allotments', [AdminAllotmentsController::class, 'index'])
        ->middleware('can:allotment.view-any')->name('admin.allotments');
});
