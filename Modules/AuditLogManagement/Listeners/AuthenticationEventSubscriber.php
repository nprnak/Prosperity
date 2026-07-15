<?php

namespace Modules\AuditLogManagement\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Events\Dispatcher;

class AuthenticationEventSubscriber
{
    public function handleLogin(Login $event): void
    {
        $this->log('login', 'User logged in', $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user) {
            $this->log('logout', 'User logged out', $event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        activity('auth')
            ->event('login_failed')
            ->withProperties([
                'email' => $event->credentials['email'] ?? null,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('Login failed');
    }

    protected function log(string $event, string $description, Authenticatable $user): void
    {
        activity('auth')
            ->causedBy($user)
            ->event($event)
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log($description);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
        ];
    }
}
