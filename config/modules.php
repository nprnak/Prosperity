<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Module Discovery
    |--------------------------------------------------------------------------
    |
    | Modules live in the top-level Modules/ directory. Each module declares
    | itself through a module.php manifest (name, enabled flag, route files,
    | migration/view paths, extra service providers). The ModuleServiceProvider
    | scans this path on boot and registers every enabled module automatically.
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

];
