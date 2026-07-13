<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminCredentialsController;
use App\Http\Controllers\AdminPanelController;
use App\Http\Controllers\AdminUsersController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AdminReportsController;
use App\Http\Controllers\AdminLogsController;
use App\Http\Controllers\AdminApplicationsController;
use App\Http\Controllers\AdminPaymentsController;
use App\Http\Controllers\AdminAllotmentsController;
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
    Route::get('/settings', [ProfileController::class, 'settings'])->name('settings.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/applicant', [ProfileController::class, 'updateApplicantProfile'])->name('profile.applicant.update');
    Route::get('/profile/documents/{type}', [ProfileController::class, 'document'])->name('profile.documents.show');
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
    Route::get('/admin/panel', [AdminPanelController::class, 'index'])->name('admin.panel');
    Route::get('/admin/users', [AdminUsersController::class, 'index'])->name('admin.users');
    Route::post('/admin/users', [AdminUsersController::class, 'store'])->name('admin.users.store');
    Route::patch('/admin/users/{user}', [AdminUsersController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [AdminUsersController::class, 'destroy'])->name('admin.users.destroy');
    Route::get('/admin/applications', [AdminApplicationsController::class, 'index'])->name('admin.applications');
    Route::get('/admin/applications/{application}', [AdminApplicationsController::class, 'show'])->name('admin.applications.show');
    Route::get('/admin/payments', [AdminPaymentsController::class, 'index'])->name('admin.payments');
    Route::get('/admin/allotments', [AdminAllotmentsController::class, 'index'])->name('admin.allotments');
    Route::get('/admin/reports', [AdminReportsController::class, 'index'])->name('admin.reports');
    Route::get('/admin/settings', [AdminSettingsController::class, 'index'])->name('admin.settings');
    Route::get('/admin/credentials', [AdminCredentialsController::class, 'index'])->name('admin.credentials');
    Route::patch('/admin/credentials/profile', [AdminCredentialsController::class, 'updateProfile'])->name('admin.credentials.profile.update');
    Route::patch('/admin/credentials/password', [AdminCredentialsController::class, 'updatePassword'])->name('admin.credentials.password.update');
    Route::get('/admin/logs', [AdminLogsController::class, 'index'])->name('admin.logs');
    Route::get('/vouchers/{voucher}/download', [VoucherController::class, 'download'])->name('vouchers.download');
});

require __DIR__.'/auth.php';
