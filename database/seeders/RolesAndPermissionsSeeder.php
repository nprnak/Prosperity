<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates the four core application roles:
     *   - applicant      : individual investor filling a share purchase application
     *   - finance_staff  : records and verifies payment transactions
     *   - approver       : director / authorised signatory who approves applications
     *   - admin          : full access (Gate::before shortcut wired in AppServiceProvider)
     */
    public function run(): void
    {
        // Reset cached roles & permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = [
            'applicant',
            'finance_staff',
            'approver',
            'admin',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
