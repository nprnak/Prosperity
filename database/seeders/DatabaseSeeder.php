<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            \Modules\CompanyManagement\Database\Seeders\CompanySeeder::class,
            \Modules\PaymentManagement\Database\Seeders\PaymentMethodSeeder::class,
            \Modules\SettingsManagement\Database\Seeders\SettingsSeeder::class,
        ]);
    }
}
