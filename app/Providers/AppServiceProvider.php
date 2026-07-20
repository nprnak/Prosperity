<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\PaymentTransactionObserver;
use App\Observers\ShareApplicationObserver;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\PaymentManagement\Models\PaymentTransaction;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        ShareApplication::observe(ShareApplicationObserver::class);
        PaymentTransaction::observe(PaymentTransactionObserver::class);
        User::observe(UserObserver::class);

        // Grants every permission. Note this is a *permission* shortcut only:
        // WorkflowService still bars a super admin from taking more than one
        // stage of the same record.
        Gate::before(fn ($user, $ability) => $user->hasRole('super_admin') ? true : null);
    }
}
