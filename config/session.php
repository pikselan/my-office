<?php

use App\Core\Config;

return [
    'name'     => Config::env('SESSION_NAME', 'MOSESSID'),
    'lifetime' => (int) Config::env('SESSION_LIFETIME', 28800),
    'idle'     => (int) Config::env('SESSION_IDLE', 7200),
    'secure'   => Config::boolEnv('SESSION_SECURE', true),
    'path'     => '/',
    'domain'   => '',
    'samesite' => 'Lax',
];
