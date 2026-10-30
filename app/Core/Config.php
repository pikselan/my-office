<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pembaca konfigurasi.
 *
 * Sumber nilai, berurutan:
 *   1. berkas .env pada akar proyek (rahasia, tidak di-commit)
 *   2. berkas config/*.php (struktur, di-commit)
 *   3. nilai bawaan pada pemanggilan get()
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    /** @var array<string, string> */
    private static array $env = [];

    private static string $basePath = '';

    public static function load(string $basePath): void
    {
        self::$basePath = rtrim($basePath, '/');
        self::loadEnvFile(self::$basePath . '/.env');

        foreach (glob(self::$basePath . '/config/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            /** @var mixed $values */
            $values = require $file;
            self::$items[$key] = is_array($values) ? $values : [];
        }
    }

    public static function basePath(string $path = ''): string
    {
        return self::$basePath . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Ambil nilai konfigurasi dengan notasi titik, contoh: `database.driver`.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);

        if ($file === null || $file === '') {
            return $default;
        }

        // Nilai .env memiliki prioritas bila kuncinya tidak berada pada
        // berkas config (contoh: PATH, APP_KEY pada lingkungan tanpa .env).
        if (!array_key_exists($file, self::$items)) {
            $value = self::raw($key);
            return $value !== null ? $value : $default;
        }

        $value = self::$items[$file];

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);
        return is_scalar($value) ? (string) $value : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);
        return is_bool($value) ? $value : self::toBool($value, $default);
    }

    public static function array(string $key, array $default = []): array
    {
        $value = self::get($key, $default);
        return is_array($value) ? $value : $default;
    }

    /**
     * Nilai mentah dari .env / environment server.
     */
    public static function env(string $key, mixed $default = null): mixed
    {
        $value = self::raw($key);
        return $value !== null ? self::cast($value) : $default;
    }

    public static function boolEnv(string $key, bool $default = false): bool
    {
        $value = self::raw($key);
        return $value === null ? $default : self::toBool($value, $default);
    }

    public static function has(string $key): bool
    {
        return self::raw($key) !== null;
    }

    /**
     * Menimpa nilai pada runtime (dipakai pengujian).
     */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $file = array_shift($segments) ?? '';

        if ($segments === []) {
            self::$items[$file] = $value;
            return;
        }

        $target = &self::$items[$file];
        if (!is_array($target)) {
            $target = [];
        }
        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }
            $target = &$target[$segment];
        }
        $target = $value;
    }

    /**
     * Hapus seluruh nilai yang dimuat; dipakai pada bootstrap ulang saat pengujian.
     */
    public static function flush(): void
    {
        self::$items = [];
        self::$env = [];
    }

    private static function raw(string $key): ?string
    {
        if (array_key_exists($key, self::$env)) {
            return self::$env[$key];
        }

        if (isset($_ENV[$key]) && is_scalar($_ENV[$key])) {
            return (string) $_ENV[$key];
        }

        if (isset($_SERVER[$key]) && is_scalar($_SERVER[$key])) {
            return (string) $_SERVER[$key];
        }

        $value = getenv($key);
        return $value === false ? null : (string) $value;
    }

    private static function loadEnvFile(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim(str_replace('export ', '', $key));

            if ($key === '') {
                continue;
            }

            $value = trim($value);

            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = substr($value, -1);
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            // Komentar sebaris hanya untuk nilai tanpa tanda kutip.
            if (!str_starts_with($value, '"') && str_contains($value, ' #')) {
                $value = trim(explode(' #', $value, 2)[0]);
            }

            self::$env[$key] = $value;
            $_ENV[$key] = $value;
        }
    }

    private static function cast(string $value): mixed
    {
        $lower = strtolower($value);

        return match ($lower) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }

    private static function toBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (!is_string($value)) {
            return $default;
        }

        return match (strtolower(trim($value))) {
            '1', 'true', 'on', 'yes', 'y' => true,
            '0', 'false', 'off', 'no', 'n', '' => false,
            default => $default,
        };
    }
}
