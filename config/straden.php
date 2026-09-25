<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Initial administrator
    |--------------------------------------------------------------------------
    |
    | Optional. When set, `php artisan straden:bootstrap` (run automatically by
    | the Docker entrypoint) creates this admin on first boot if no users exist.
    | Leave empty to create the admin through the /setup wizard instead.
    |
    */

    'admin' => [
        'name' => env('STRADEN_ADMIN_NAME', 'Admin'),
        'email' => env('STRADEN_ADMIN_EMAIL'),
        'password' => env('STRADEN_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Links
    |--------------------------------------------------------------------------
    */

    'links' => [
        'repository' => 'https://github.com/kwasii1/straden',
        'documentation' => 'https://straden.baidoo.dev',
    ],

];
