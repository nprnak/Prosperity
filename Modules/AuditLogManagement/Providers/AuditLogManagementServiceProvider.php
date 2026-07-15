<?php

namespace Modules\AuditLogManagement\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\AuditLogManagement\Listeners\AuthenticationEventSubscriber;

class AuditLogManagementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::subscribe(AuthenticationEventSubscriber::class);
    }
}
