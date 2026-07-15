<?php

namespace Modules\VoucherManagement\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\VoucherManagement\Models\Voucher;
use Modules\VoucherManagement\Policies\VoucherPolicy;

class VoucherManagementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Voucher::class, VoucherPolicy::class);
    }
}
