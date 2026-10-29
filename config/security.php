<?php

use App\Core\Config;

return [
    'login' => [
        'max_attempts' => (int) Config::env('LOGIN_MAX_ATTEMPTS', 5),
        'lock_minutes' => (int) Config::env('LOGIN_LOCK_MINUTES', 15),
    ],
    'password' => [
        'min_length' => 8,
    ],
    'tokens' => [
        'reset_ttl_minutes'    => 60,
        'verification_ttl_hours' => 24,
        'invitation_ttl_days'  => 7,
    ],
    'headers' => [
        'X-Frame-Options'           => 'SAMEORIGIN',
        'X-Content-Type-Options'    => 'nosniff',
        'Referrer-Policy'           => 'strict-origin-when-cross-origin',
        'Permissions-Policy'        => 'geolocation=(self), microphone=(), camera=()',
    ],
    'log_retention_days' => (int) Config::env('LOG_RETENTION_DAYS', 365),
];
