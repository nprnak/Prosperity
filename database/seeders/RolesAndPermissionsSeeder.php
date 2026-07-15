<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Granular permissions grouped by role.
     *
     * Roles:
     *   - applicant      : individual investor filling a share purchase application
     *   - finance_staff  : records and verifies payment transactions
     *   - approver       : director / authorised signatory who approves applications
     *   - admin          : full access (all permissions + Gate::before shortcut)
     *
     * `voucher.download` allows downloading vouchers the user owns (enforced by
     * VoucherPolicy); `voucher.download-any` bypasses the ownership check.
     */
    public const PERMISSIONS = [
        'application.submit',
        'application.view-any',
        'application.approve',
        'application.reject',
        'payment.record',
        'payment.verify',
        'payment.view-any',
        'allotment.manage',
        'allotment.view-any',
        'voucher.download',
        'voucher.download-any',
        'user.manage',
        'settings.manage',
        'report.view',
        'audit.view',
        'dashboard.view-admin',
    ];

    public const ROLES = [
        'applicant' => [
            'application.submit',
            'voucher.download',
        ],
        'finance_staff' => [
            'payment.record',
            'payment.verify',
        ],
        'approver' => [
            'application.approve',
            'application.reject',
            'allotment.manage',
            'voucher.download',
            'voucher.download-any',
        ],
        'admin' => self::PERMISSIONS,
    ];

    public function run(): void
    {
        // Reset cached roles & permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        foreach (self::ROLES as $role => $permissions) {
            Role::firstOrCreate(['name' => $role])->syncPermissions($permissions);
        }
    }
}
