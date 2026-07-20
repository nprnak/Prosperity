<?php

namespace Modules\ApplicantManagement\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicantManagement\Policies\ProfilePolicy;

class ApplicantManagementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Profile::class, ProfilePolicy::class);
    }
}
