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

use App\Http\Controllers\Auth\TwoFactorResendController;

Route::post('/verification/otp', [TwoFactorResendController::class, 'resend'])->name('verification.otp');

require __DIR__.'/auth.php';
