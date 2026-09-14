<?php

namespace Modules\JarManagement\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\JarManagement\Models\JarDelivery;
use Modules\JarManagement\Models\JarVehicleLot;
use Modules\JarManagement\Policies\JarDeliveryPolicy;
use Modules\JarManagement\Policies\JarVehicleLotPolicy;

class JarManagementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(JarVehicleLot::class, JarVehicleLotPolicy::class);
        Gate::policy(JarDelivery::class, JarDeliveryPolicy::class);
    }
}
