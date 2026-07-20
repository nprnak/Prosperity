<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Granular permissions grouped by role.
     *
     * Two independent three-stage review chains, each verifier → reviewer →
     * approver. A user may hold several of these roles, but WorkflowService
     * still requires three distinct people per record: whoever acts at one
     * stage is barred from the others for that cycle. super_admin is not
     * exempt from that rule.
     *
     * Roles:
     *   - applicant            : individual investor filling a share application
     *   - finance_staff        : records and verifies payment transactions
     *   - profile_verifier     : KYC stage 1
     *   - profile_reviewer     : KYC stage 2
     *   - profile_approver     : KYC stage 3 — makes the profile approved and complete
     *   - application_verifier : application stage 1
     *   - application_reviewer : application stage 2
     *   - application_approver : application stage 3 — final sign-off, issues the voucher
     *   - super_admin          : every permission (plus the Gate::before shortcut)
     *
     * `voucher.download` allows downloading vouchers the user owns (enforced by
     * VoucherPolicy); `voucher.download-any` bypasses the ownership check.
     */
    public const PERMISSIONS = [
        'company.manage',
        'profile.verify',
        'profile.review',
        'profile.approve',
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
            'application.view-any',
            'payment.record',
            'payment.verify',
            'payment.view-any',
        ],
        'profile_verifier' => [
            'profile.verify',
        ],
        'profile_reviewer' => [
            'profile.review',
        ],
        'profile_approver' => [
            'profile.approve',
        ],
        'application_verifier' => [
            'application.view-any',
            'application.verify',
        ],
        'application_reviewer' => [
            'application.view-any',
            'application.review',
        ],
        'application_approver' => [
            'application.view-any',
            'application.approve',
            'application.reject',
            'allotment.manage',
            'voucher.download',
            'voucher.download-any',
        ],
        'super_admin' => self::PERMISSIONS,

    ];

    public function run(): void
    {
        // Reset cached roles & permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        foreach (self::ROLES as $role => $permissions) {
            Role::firstOrCreate(['name' => $role])->syncPermissions($permissions);
        }
    }
}
