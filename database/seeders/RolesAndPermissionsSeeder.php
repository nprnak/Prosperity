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
     *   - reviewer       : first approval stage — reviews payment-verified applications
     *   - verifier       : second approval stage — verifies reviewed applications
     *   - approver       : director / authorised signatory who gives final approval
     *   - admin          : full access (all permissions + Gate::before shortcut)
     *
     * `voucher.download` allows downloading vouchers the user owns (enforced by
     * VoucherPolicy); `voucher.download-any` bypasses the ownership check.
     */
    public const PERMISSIONS = [
        'company.manage',
        'profile.review',
        'application.submit',
        'application.view-any',
        'application.review',
        'application.verify',
        'application.approve',
        'application.reject',
        'payment.record',
        'payment.verify',
        'payment.view-any',
        'payment-method.manage',
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
        'reviewer' => [
            'application.review',
        ],
        'verifier' => [
            'application.verify',
        ],
        'approver' => [
            'profile.review',
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
