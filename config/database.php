<?php

use App\Core\Config;

/**
 * Konfigurasi database.
 *
 * Produksi memakai driver mysql; sandbox/pengujian memakai sqlite karena
 * MySQL Server tidak tersedia di lingkungan kerja (lihat docs/ENVIRONMENT.md).
 */
return [
    'driver'   => Config::env('DB_DRIVER', 'mysql'),
    'host'     => Config::env('DB_HOST', '127.0.0.1'),
    'port'     => (int) Config::env('DB_PORT', 3306),
    'database' => Config::env('DB_DATABASE', 'mo_hris'),
    'username' => Config::env('DB_USERNAME', 'root'),
    'password' => (string) Config::env('DB_PASSWORD', ''),
    'charset'  => Config::env('DB_CHARSET', 'utf8mb4'),
    'collation' => Config::env('DB_COLLATION', 'utf8mb4_unicode_ci'),

    // Khusus sqlite (sandbox & pengujian).
    'path'     => Config::env('DB_PATH', 'storage/database/mo.sqlite'),

    'options'  => [
        'emulate_prepares' => false,
        'stringify_fetches' => false,
        'timeout' => 10,
    ],
];
