<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

/**
 * Kumpulan helper global.
 *
 * Helper dibatasi pada hal yang dipakai berulang di view dan controller:
 * escaping, URL, sesi, dan format tampilan.
 */

if (!function_exists('e')) {
    /**
     * Escaping untuk output HTML. Wajib dipakai pada setiap data dinamis.
     */
    function e(mixed $value): string
    {
        return \App\Core\View::escape($value);
    }
}

if (!function_exists('app')) {
    function app(): App
    {
        return App::instance();
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('request')) {
    function request(): ?Request
    {
        return App::isBooted() ? App::instance()->currentRequest() : null;
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return App::instance()->basePath($path);
    }
}

if (!function_exists('url')) {
    /**
     * Path relatif terhadap akar situs.
     *
     * Sengaja tidak absolut: aplikasi dapat berada di balik proxy dengan
     * skema berbeda, dan URL absolut justru memicu blokir mixed content.
     */
    function url(string $path = ''): string
    {
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('absolute_url')) {
    /**
     * URL absolut berdasarkan host pada request saat ini (atau APP_URL bila
     * tidak ada request, misalnya pada CLI). Dipakai untuk redirect dan surel.
     */
    function absolute_url(string $path = ''): string
    {
        $path = '/' . ltrim($path, '/');
        $request = request();

        if ($request !== null) {
            return rtrim($request->fullUrlHost(), '/') . $path;
        }

        return rtrim(Config::string('app.url', ''), '/') . $path;
    }
}

if (!function_exists('asset')) {
    /**
     * URL berkas statis dengan penanda versi agar cache browser dapat diperbarui.
     */
    function asset(string $path): string
    {
        $clean = '/' . ltrim($path, '/');
        $file = App::isBooted() ? App::instance()->basePath('public' . $clean) : '';

        $version = '';
        if ($file !== '' && is_file($file)) {
            $version = '?v=' . substr((string) md5_file($file), 0, 8);
        }

        return $clean . $version;
    }
}

if (!function_exists('route')) {
    /**
     * Bangun URI dari nama route.
     *
     * @param array<string, mixed> $params
     */
    function route(string $name, array $params = []): string
    {
        return App::instance()->router()->url($name, $params);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $status = 302): Response
    {
        $target = str_starts_with($path, 'http') ? $path : absolute_url($path);
        return Response::redirect($target, $status);
    }
}

if (!function_exists('back')) {
    function back(): Response
    {
        $referer = request()?->header('Referer');
        return redirect($referer !== null && $referer !== '' ? $referer : '/');
    }
}

if (!function_exists('view')) {
    /**
     * @param array<string, mixed> $data
     */
    function view(string $template, array $data = [], ?string $layout = null): string
    {
        return View::render($template, $data, $layout);
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        $input = Session::oldInput();
        return $input[$key] ?? $default;
    }
}

if (!function_exists('errors')) {
    /**
     * Seluruh pesan validasi, atau pesan untuk satu bidang.
     *
     * @return array<string, mixed>|string|null
     */
    function errors(?string $key = null): array|string|null
    {
        $bag = Session::errors();

        if ($key === null) {
            return $bag;
        }

        $value = $bag[$key] ?? null;

        if (is_array($value)) {
            return (string) ($value[0] ?? '');
        }

        return $value !== null ? (string) $value : null;
    }
}

if (!function_exists('has_error')) {
    function has_error(string $key): bool
    {
        return array_key_exists($key, Session::errors());
    }
}

if (!function_exists('session_flash')) {
    function session_flash(string $key, mixed $value): void
    {
        Session::flash($key, $value);
    }
}

if (!function_exists('abort')) {
    function abort(int $status = 404, string $message = ''): never
    {
        throw new HttpException($status, $message);
    }
}

if (!function_exists('logger')) {
    function logger(): string
    {
        return Logger::class;
    }
}

if (!function_exists('now')) {
    /**
     * Waktu saat ini dalam UTC.
     */
    function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}

if (!function_exists('now_datetime')) {
    function now_datetime(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

if (!function_exists('str_slug')) {
    function str_slug(string $value, string $separator = '-'): string
    {
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', $separator, $value) ?? '';
        $value = trim((string) $value, $separator);
        return $value !== '' ? $value : 'item';
    }
}

if (!function_exists('str_limit')) {
    function str_limit(string $value, int $limit = 100, string $end = '…'): string
    {
        if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') <= $limit) {
            return $value;
        }
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit, 'UTF-8') . $end;
        }
        return substr($value, 0, $limit) . $end;
    }
}

if (!function_exists('money')) {
    /**
     * Format mata uang sederhana untuk Bahasa Indonesia.
     */
    function money(float|int|string $amount, string $currency = 'IDR'): string
    {
        $value = number_format((float) $amount, 0, ',', '.');
        return match (strtoupper($currency)) {
            'IDR' => 'Rp' . $value,
            default => $currency . ' ' . $value,
        };
    }
}

if (!function_exists('format_date')) {
    /**
     * Format tanggal dari string UTC ke zona waktu tertentu.
     */
    function format_date(?string $value, string $format = 'd/m/Y', ?string $timezone = null): string
    {
        if ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return '-';
        }

        try {
            $date = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
            if ($timezone !== null && $timezone !== '') {
                $date = $date->setTimezone(new \DateTimeZone($timezone));
            }
            return $date->format($format);
        } catch (\Throwable) {
            return '-';
        }
    }
}

if (!function_exists('dd')) {
    /**
     * Dump & die, hanya aktif pada mode debug.
     */
    function dd(mixed ...$values): void
    {
        if (!Config::bool('app.debug', false) && PHP_SAPI !== 'cli') {
            http_response_code(500);
            echo 'Debug dinonaktifkan.';
            return;
        }

        foreach ($values as $value) {
            if (PHP_SAPI === 'cli') {
                var_export($value);
                echo PHP_EOL;
            } else {
                echo '<pre style="background:#F9FAFB;border:1px solid #E5E7EB;padding:12px;border-radius:8px">';
                echo htmlspecialchars((string) var_export($value, true), ENT_QUOTES, 'UTF-8');
                echo '</pre>';
            }
        }
        exit;
    }
}
