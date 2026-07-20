<?php

use Modules\VoucherManagement\Providers\VoucherManagementServiceProvider;

return [

    'name' => 'VoucherManagement',

    'version' => '1.0.0',

    'enabled' => true,

    // Route files are discovered automatically at Routes/web.php and
    // Routes/api.php; override the paths here if needed.

    // Extra service providers to register for this module.
    'providers' => [
        VoucherManagementServiceProvider::class,
    ],

];
