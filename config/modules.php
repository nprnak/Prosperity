<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Module Path
    |--------------------------------------------------------------------------
    |
    | Modules live in the top-level Modules/ directory. Each module may still
    | ship a module.php manifest for per-module extras (service providers,
    | path overrides), but the registry below is the single source of truth
    | for which modules load and how their routes are mounted.
    |
    */

    'path' => base_path('Modules'),

    /*
    |--------------------------------------------------------------------------
    | Master Switch
    |--------------------------------------------------------------------------
    |
    | Set to false to skip module loading entirely (useful for debugging).
    |
    */

    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Module Registry
    |--------------------------------------------------------------------------
    |
    | Central registry of enabled modules. Only modules listed here are
    | loaded. For each module:
    |
    |   enabled     toggle the module without deleting it (default true)
    |   prefix      URL prefix applied to the module's Routes/api.php file;
    |               the provider mounts it under /api, so 'v1/users' becomes
    |               /api/v1/users/... Module route files must not repeat it.
    |   middleware  middleware group(s) for the module's api routes
    |               (web routes always use the "web" group).
    |
    */

    'modules' => [

        'UserManagement' => [
            'prefix' => 'v1/users',
            'middleware' => ['api'],
        ],

        'SettingsManagement' => [
            'prefix' => 'v1/settings',
            'middleware' => ['api'],
        ],

        'CompanyManagement' => [
            'prefix' => 'v1/companies',
            'middleware' => ['api'],
        ],

        'ApplicantManagement' => [
            'prefix' => 'v1/applicants',
            'middleware' => ['api'],
        ],

        'ApplicationManagement' => [
            'prefix' => 'v1/applications',
            'middleware' => ['api'],
        ],

        'ApprovalManagement' => [
            'prefix' => 'v1/approvals',
            'middleware' => ['api'],
        ],

        'PaymentManagement' => [
            'prefix' => 'v1/payments',
            'middleware' => ['api'],
        ],

        'AllotmentManagement' => [
            'prefix' => 'v1/allotments',
            'middleware' => ['api'],
        ],

        'VoucherManagement' => [
            'prefix' => 'v1/vouchers',
            'middleware' => ['api'],
        ],

        'ReportManagement' => [
            'prefix' => 'v1/reports',
            'middleware' => ['api'],
        ],

        'AuditLogManagement' => [
            'prefix' => 'v1/audit-logs',
            'middleware' => ['api'],
        ],

        'Dashboard' => [
            'prefix' => 'v1/dashboard',
            'middleware' => ['api'],
        ],

    ],

];
