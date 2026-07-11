<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\ApplicationWizardController;
use App\Http\Controllers\ApproverController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShareAllotmentController;
use App\Http\Controllers\VoucherController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    $user = auth()->user();

    if ($user?->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    }

    if ($user?->hasRole('finance_staff')) {
        return redirect()->route('finance.dashboard');
    }

    if ($user?->hasRole('approver')) {
        return redirect()->route('approver.dashboard');
    }

    return redirect()->route('applications.wizard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/applications/wizard', [ApplicationWizardController::class, 'index'])->name('applications.wizard');
    Route::post('/applications/draft', [ApplicationWizardController::class, 'storeDraft'])->name('applications.draft');
    Route::post('/applications/{application}/submit', [ApplicationWizardController::class, 'submit'])->name('applications.submit');

    Route::get('/finance/dashboard', [FinanceController::class, 'dashboard'])->name('finance.dashboard');
    Route::post('/finance/applications/{application}/payments', [FinanceController::class, 'storePayment'])->name('finance.payments.store');
    Route::post('/finance/payments/{payment}/verify', [FinanceController::class, 'verifyPayment'])->name('finance.payments.verify');

    Route::get('/approver/dashboard', [ApproverController::class, 'dashboard'])->name('approver.dashboard');
    Route::post('/approver/applications/{application}/approve', [ApproverController::class, 'approve'])->name('approver.applications.approve');
    Route::post('/approver/applications/{application}/reject', [ApproverController::class, 'reject'])->name('approver.applications.reject');

    Route::get('/allotments/register', [ShareAllotmentController::class, 'index'])->name('allotments.register');
    Route::post('/allotments/{application}', [ShareAllotmentController::class, 'store'])->name('allotments.store');
    Route::get('/allotments/export', [ShareAllotmentController::class, 'export'])->name('allotments.export');

    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/vouchers/{voucher}/download', [VoucherController::class, 'download'])->name('vouchers.download');
});

require __DIR__.'/auth.php';
