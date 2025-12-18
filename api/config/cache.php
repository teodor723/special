<?php

use Illuminate\Support\Str;

return [
    'default' => env('CACHE_STORE', 'database'),

    'stores' => [
        'database' => [
            'driver' => 'database',
            'connection' => null,
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => null,
            'lock_table' => null,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => 'default',
        ],
    ],

    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_cache_'),
];

