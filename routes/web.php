<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Domain routes live in each module's Routes/web.php, discovered by
// App\Providers\ModuleServiceProvider via the module.php manifests.

Route::get('/', function () {
    // Redirect root to the login page to make login the main entry point
    return redirect()->route('login');
});

Route::post('/notifications/mark-read', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])
    ->middleware(['auth', 'verified'])->name('notifications.mark-read');

Route::middleware(['auth', 'verified'])->prefix('nepali-date')->name('nepali-date.')->group(function () {
    Route::get('/days-in-month', [\App\Http\Controllers\NepaliDateController::class, 'daysInMonth'])->name('days-in-month');
    Route::get('/to-english', [\App\Http\Controllers\NepaliDateController::class, 'toEnglish'])->name('to-english');
    Route::get('/to-nepali', [\App\Http\Controllers\NepaliDateController::class, 'toNepali'])->name('to-nepali');
});

use App\Http\Controllers\Auth\TwoFactorResendController;

Route::post('/verification/otp', [TwoFactorResendController::class, 'resend'])->name('two-factor.resend');

require __DIR__.'/auth.php';
