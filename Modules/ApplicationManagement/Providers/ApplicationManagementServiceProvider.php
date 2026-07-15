<?php

namespace Modules\ApplicationManagement\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApplicationManagement\Policies\ShareApplicationPolicy;

class ApplicationManagementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(ShareApplication::class, ShareApplicationPolicy::class);
    }
}
