<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stub Path
    |--------------------------------------------------------------------------
    |
    | This value is the path to the stubs the package should use when executing
    | commands. To use your own stubs, vendor:publish the package stubs and set
    | this to: resource_path('stubs/vendor/laravel-automatic-migrations')
    |
    */

    'stub_path' => base_path('vendor/bastinald/laravel-automatic-migrations/resources/stubs'),

    'model_paths' => [
        'App\\Models' => app_path('Models'),
    ],
];
