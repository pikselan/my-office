<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pembungkus sesi PHP.
 *
 * Menangani: nama cookie, umur absolut & idle, regenerasi id (mencegah session
 * fixation), serta flash message. Pada CLI (pengujian) sesi tetap tersedia
 * sebagai array tanpa cookie.
 */
final class Session
{
    private const FLASH_NEW = '_mo_flash_new';
    private const FLASH_OLD = '_mo_flash_old';
    private const LAST_ACTIVITY = '_mo_last_activity';
    private const CREATED_AT = '_mo_created_at';
    private const CSRF_KEY = '_mo_csrf_token';

    private static bool $started = false;
    private static bool $inCli = false;

    /**
     * @param array<string, mixed> $config
     */
    public static function start(array $config = []): void
    {
        if (self::$started) {
            return;
        }

        $config = array_merge([
            'name'     => Config::string('session.name', 'MOSESSID'),
            'lifetime' => Config::int('session.lifetime', 28800),
            'idle'     => Config::int('session.idle', 7200),
            'secure'   => Config::bool('session.secure', false),
            'path'     => Config::string('session.path', '/'),
            'domain'   => Config::string('session.domain', ''),
            'samesite' => Config::string('session.samesite', 'Lax'),
        ], $config);

        self::$inCli = PHP_SAPI === 'cli';

        if (self::$inCli) {
            $_SESSION = [];
            self::$started = true;
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', (string) $config['samesite']);
        ini_set('session.gc_maxlifetime', (string) (int) $config['lifetime']);

        session_name((string) $config['name']);

        session_set_cookie_params([
            'lifetime' => (int) $config['lifetime'],
            'path'     => (string) $config['path'],
            'domain'   => (string) $config['domain'],
            'secure'   => (bool) $config['secure'],
            'httponly' => true,
            'samesite' => (string) $config['samesite'],
        ]);

        session_start();

        self::enforceTimeouts((int) $config['lifetime'], (int) $config['idle']);
        self::rotateFlash();

        self::$started = true;
    }

    public static function started(): bool
    {
        return self::$started;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);
        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return $_SESSION ?? [];
    }

    /**
     * Simpan pesan singkat yang hanya terbaca pada request berikutnya.
     */
    public static function flash(string $key, mixed $value): void
    {
        $flashes = self::get(self::FLASH_NEW, []);
        if (!is_array($flashes)) {
            $flashes = [];
        }
        $flashes[$key] = $value;
        self::set(self::FLASH_NEW, $flashes);
    }

    public static function flashNow(string $key, mixed $value): void
    {
        $flashes = self::get(self::FLASH_OLD, []);
        if (!is_array($flashes)) {
            $flashes = [];
        }
        $flashes[$key] = $value;
        self::set(self::FLASH_OLD, $flashes);
    }

    public static function hasFlash(string $key): bool
    {
        $flashes = self::get(self::FLASH_OLD, []);
        return is_array($flashes) && array_key_exists($key, $flashes);
    }

    /**
     * Baca pesan singkat; mendukung lebih dari satu pesan per kunci.
     */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $flashes = self::get(self::FLASH_OLD, []);
        if (!is_array($flashes) || !array_key_exists($key, $flashes)) {
            return $default;
        }
        return $flashes[$key];
    }

    /**
     * Simpan seluruh masukan formulir untuk diisi kembali saat validasi gagal.
     *
     * @param array<string, mixed> $input
     */
    public static function flashInput(array $input): void
    {
        self::flash('_old_input', $input);
    }

    /**
     * @param array<string, mixed> $errors
     */
    public static function flashErrors(array $errors): void
    {
        self::flash('_errors', $errors);
    }

    /**
     * @return array<string, mixed>
     */
    public static function oldInput(): array
    {
        $input = self::getFlash('_old_input', []);
        return is_array($input) ? $input : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function errors(): array
    {
        $errors = self::getFlash('_errors', []);
        return is_array($errors) ? $errors : [];
    }

    /**
     * Regenerasi id sesi (dipanggil setelah login dan perubahan hak).
     */
    public static function regenerate(bool $destroyOld = false): void
    {
        if (self::$inCli) {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($destroyOld);
        }
        self::set(self::CREATED_AT, time());
    }

    public static function id(): string
    {
        return self::$inCli ? 'cli-session' : (string) session_id();
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (!self::$inCli && session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies') !== false && !headers_sent()) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    [
                        'expires'  => time() - 42000,
                        'path'     => $params['path'],
                        'domain'   => $params['domain'],
                        'secure'   => (bool) $params['secure'],
                        'httponly' => true,
                        'samesite' => $params['samesite'] ?: 'Lax',
                    ]
                );
            }
            session_destroy();
        }

        self::$started = false;
    }

    public static function csrfToken(): string
    {
        $token = self::get(self::CSRF_KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            self::set(self::CSRF_KEY, $token);
        }

        return $token;
    }

    public static function regenerateCsrfToken(): string
    {
        self::forget(self::CSRF_KEY);
        return self::csrfToken();
    }

    private static function rotateFlash(): void
    {
        $_SESSION[self::FLASH_OLD] = $_SESSION[self::FLASH_NEW] ?? [];
        $_SESSION[self::FLASH_NEW] = [];

        if (!isset($_SESSION[self::CREATED_AT])) {
            $_SESSION[self::CREATED_AT] = time();
        }

        $_SESSION[self::LAST_ACTIVITY] = time();
    }

    private static function enforceTimeouts(int $lifetime, int $idle): void
    {
        $now = time();
        $created = (int) ($_SESSION[self::CREATED_AT] ?? $now);
        $lastActivity = (int) ($_SESSION[self::LAST_ACTIVITY] ?? $now);

        $expired = ($lifetime > 0 && ($now - $created) > $lifetime)
            || ($idle > 0 && ($now - $lastActivity) > $idle);

        if ($expired) {
            self::destroy();
            session_start();
            $_SESSION[self::FLASH_OLD] = [];
            $_SESSION[self::FLASH_NEW] = [];
        }

        $_SESSION[self::CREATED_AT] = $_SESSION[self::CREATED_AT] ?? $now;
        $_SESSION[self::LAST_ACTIVITY] = $now;
    }
}
