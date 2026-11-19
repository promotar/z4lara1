<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | This platform ships legacy password hashes created outside PHP, so some
    | rows start with the "$2b$" prefix instead of PHP's "$2y$". Both are
    | bcrypt and PHP verifies them correctly, but the strict algorithm check
    | below (verify = true) rejects "$2b$" because password_get_info() reports
    | it as "unknown". Keeping the check disabled avoids a 500 error on login
    | while `password_verify()` still performs the real credential check.
    |
    | Supported drivers: "bcrypt", "argon", "argon2id"
    |
    */

    'driver' => env('HASH_DRIVER', 'bcrypt'),

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('HASH_VERIFY', false),
        'limit' => env('BCRYPT_LIMIT', null),
    ],

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => env('HASH_VERIFY', false),
    ],

    /*
    | Hashes are upgraded to the native PHP format on the first successful
    | login, so old "$2b$" rows are migrated to "$2y$" automatically.
    */

    'rehash_on_login' => true,

];
