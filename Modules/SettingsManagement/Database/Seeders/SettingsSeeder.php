<?php

namespace Modules\SettingsManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\SettingsManagement\Models\Setting;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'organization' => [
                'org_name' => 'Prosperity',
                'org_address' => 'Kathmandu, Nepal',
                'contact_email' => 'contact@prosperity.com',
                'support_phone' => '',
            ],
            'mail' => [
                'mail_host' => '',
                'mail_port' => '587',
                'mail_username' => '',
                'mail_password' => '',
                'mail_encryption' => 'tls',
                'mail_from_address' => 'noreply@prosperity.com',
                'mail_from_name' => 'Prosperity',
            ],
            'application' => [
                'currency_code' => 'NPR',
                'currency_symbol' => 'रु',
                'max_upload_size_kb' => '2048',
                'max_applications_per_user' => '5',
            ],
        ];

        foreach ($defaults as $group => $settings) {
            foreach ($settings as $key => $value) {
                Setting::firstOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
            }
        }
    }
}
