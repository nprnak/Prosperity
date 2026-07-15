<?php

namespace Modules\SettingsManagement\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Modules\SettingsManagement\Models\Setting;
use Throwable;

class SettingsManagementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->applyMailSettings();
    }

    /**
     * Overrides the mail config with the admin-managed SMTP settings.
     * Skipped silently when the database or settings table is not ready
     * (fresh install, migrations still pending).
     */
    protected function applyMailSettings(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $settings = Setting::allCached();
        } catch (Throwable) {
            return;
        }

        if (filled($settings['mail_host'] ?? null)) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $settings['mail_host'],
                'mail.mailers.smtp.port' => (int) ($settings['mail_port'] ?? 587),
                'mail.mailers.smtp.username' => $settings['mail_username'] ?? null,
                'mail.mailers.smtp.password' => $settings['mail_password'] ?? null,
                'mail.mailers.smtp.encryption' => ($settings['mail_encryption'] ?? 'tls') === 'none'
                    ? null
                    : ($settings['mail_encryption'] ?? 'tls'),
            ]);
        }

        if (filled($settings['mail_from_address'] ?? null)) {
            config(['mail.from.address' => $settings['mail_from_address']]);
        }

        if (filled($settings['mail_from_name'] ?? null)) {
            config(['mail.from.name' => $settings['mail_from_name']]);
        }
    }
}
