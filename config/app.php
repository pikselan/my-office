<?php

use App\Core\Config;

/**
 * Konfigurasi aplikasi.
 */
return [
    'name'     => Config::env('APP_NAME', 'MO (MyOffice)'),
    'env'      => Config::env('APP_ENV', 'production'),
    'debug'    => Config::boolEnv('APP_DEBUG', false),
    'url'      => rtrim((string) Config::env('APP_URL', 'http://localhost:8000'), '/'),
    'key'      => (string) Config::env('APP_KEY', ''),
    'timezone' => Config::env('APP_TIMEZONE', 'UTC'),

    'locale'   => [
        'default'   => Config::env('LOCALE_DEFAULT', 'id'),
        'fallback'  => Config::env('LOCALE_FALLBACK', 'en'),
        'available' => ['id', 'en'],
    ],
];
