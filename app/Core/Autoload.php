<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Autoload PSR-4 sederhana tanpa Composer.
 *
 * Pemetaan awal:
 *   App\...        -> app/...
 *   Modules\...    -> modules/...
 *   Tests\...      -> tests/...
 */
final class Autoload
{
    /** @var array<string, string> */
    private static array $prefixes = [];

    private static bool $registered = false;

    public static function register(string $basePath): void
    {
        if (self::$registered && self::$prefixes !== []) {
            return;
        }
        self::$registered = true;
        $base = rtrim($basePath, '/');
        self::$prefixes = [
            'App\\'     => $base . '/app',
            'Modules\\' => $base . '/modules',
            'Tests\\'   => $base . '/tests',
        ];

        spl_autoload_register(static function (string $class): void {
            self::load($class);
        });
    }

    public static function addPrefix(string $prefix, string $directory): void
    {
        self::$prefixes[$prefix] = rtrim($directory, '/');
    }

    public static function load(string $class): void
    {
        foreach (self::$prefixes as $prefix => $directory) {
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                continue;
            }

            $relative = substr($class, strlen($prefix));
            $path = $directory . '/' . str_replace('\\', '/', $relative) . '.php';

            if (is_file($path)) {
                require $path;
                return;
            }
        }
    }
}
