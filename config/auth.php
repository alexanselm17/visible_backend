<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option controls the default authentication "guard" and password
    | reset options for your application. You may change these defaults
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => 'web',  // ✅ Set "web" as the default guard for session-based login
        'passwords' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Define different guards for handling user authentication.
    |
    | "web" is used for session-based authentication.
    | "api" is used for token-based authentication (Sanctum).
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',  // ✅ Uses session-based login (for Laravel UI)
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'sanctum',  // ✅ Uses Laravel Sanctum for API authentication
            'provider' => 'users',
            'hash' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | Defines how users are retrieved from the database.
    | Uses the "eloquent" driver with the User model.
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | Configuration for password resets.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_resets',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Defines how long a password confirmation is valid before expiring.
    |
    */

    'password_timeout' => 10800,

];
