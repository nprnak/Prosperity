<?php

namespace App\Providers;

use App\Models\PaymentTransaction;
use App\Models\ShareApplication;
use App\Observers\PaymentTransactionObserver;
use App\Observers\ShareApplicationObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

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

        // Admin role bypasses all Gates.
        Gate::before(fn ($user, $ability) => $user->hasRole('admin') ? true : null);
    }
}
