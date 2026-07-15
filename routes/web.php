<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Domain routes live in each module's Routes/web.php, discovered by
// App\Providers\ModuleServiceProvider via the module.php manifests.

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::post('/notifications/mark-read', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])
    ->middleware(['auth', 'verified'])->name('notifications.mark-read');

require __DIR__.'/auth.php';
