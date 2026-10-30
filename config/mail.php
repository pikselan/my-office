<?php

use App\Core\Config;

return [
    'driver'     => Config::env('MAIL_DRIVER', 'log'),
    'from_name'  => Config::env('MAIL_FROM_NAME', 'MO (MyOffice)'),
    'from_email' => Config::env('MAIL_FROM_ADDRESS', 'no-reply@localhost'),
    'smtp'       => [
        'host'       => Config::env('MAIL_SMTP_HOST', ''),
        'port'       => (int) Config::env('MAIL_SMTP_PORT', 587),
        'username'   => Config::env('MAIL_SMTP_USERNAME', ''),
        'password'   => (string) Config::env('MAIL_SMTP_PASSWORD', ''),
        'encryption' => Config::env('MAIL_SMTP_ENCRYPTION', 'tls'),
    ],
];
