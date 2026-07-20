<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\CompanyManagement\Database\Seeders\CompanySeeder;
use Modules\PaymentManagement\Database\Seeders\PaymentMethodSeeder;
use Modules\SettingsManagement\Database\Seeders\SettingsSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            GeographySeeder::class,
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            CompanySeeder::class,
            PaymentMethodSeeder::class,
            SettingsSeeder::class,
        ]);
    }
}
