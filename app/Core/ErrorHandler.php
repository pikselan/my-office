<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Penanganan error dan exception terpusat.
 *
 * - Mode development : detail error ditampilkan untuk memudahkan perbaikan.
 * - Mode production  : halaman generik + pencatatan ke log dengan request id.
 */
final class ErrorHandler
{
    private static bool $debug = false;
    private static bool $registered = false;

    public static function register(bool $debug): void
    {
        if (self::$registered) {
            return;
        }

        self::$debug = $debug;
        self::$registered = true;

        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', Config::basePath('storage/logs/php-error.log'));

        set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (\Throwable $e): void {
            self::handle($e);
        });

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::handle(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
            }
        });
    }

    public static function handle(\Throwable $e): void
    {
        $status = $e instanceof HttpException ? $e->status() : 500;
        $requestId = Request::id();

        if ($status >= 500) {
            Logger::error($e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_id' => $requestId,
                'trace' => self::$debug ? $e->getTraceAsString() : null,
            ]);
        }

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, sprintf("[%s] %s: %s (%s:%d)%s", date('c'), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), PHP_EOL));
            return;
        }

        http_response_code($status);

        if (self::wantsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            echo (string) json_encode([
                'success' => false,
                'message' => self::safeMessage($e, $status),
                'request_id' => $requestId,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo self::renderPage($e, $status, $requestId);
    }

    private static function safeMessage(\Throwable $e, int $status): string
    {
        if (self::$debug) {
            return $e->getMessage();
        }

        return match ($status) {
            403 => 'Anda tidak memiliki izin untuk mengakses halaman ini.',
            404 => 'Halaman atau data yang diminta tidak ditemukan.',
            422 => 'Data yang dikirim tidak valid.',
            429 => 'Terlalu banyak percobaan. Silakan coba beberapa saat lagi.',
            default => 'Terjadi kesalahan pada server. Tim kami telah menerima laporannya.',
        };
    }

    private static function renderPage(\Throwable $e, int $status, string $requestId): string
    {
        $title = match ($status) {
            403 => 'Akses ditolak',
            404 => 'Tidak ditemukan',
            422 => 'Data tidak valid',
            429 => 'Terlalu banyak percobaan',
            default => 'Kesalahan server',
        };

        $message = self::safeMessage($e, $status);
        $escapedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $code = (string) $status;
        $id = htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8');

        $debugBlock = '';
        if (self::$debug) {
            $trace = htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8');
            $file = htmlspecialchars($e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8');
            $debugBlock = "<pre class=\"mo-debug\">{$file}\n\n{$trace}</pre>";
        }

        return <<<HTML
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$code} — {$escapedTitle}</title>
<style>
  body{margin:0;font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:#F7F8FA;color:#111827}
  .box{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .card{background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:32px;max-width:640px;width:100%;box-shadow:0 1px 2px rgba(16,24,40,.05)}
  .code{font-size:44px;font-weight:700;color:#6D28D9;margin:0}
  h1{font-size:20px;margin:8px 0 12px}
  p{color:#6B7280;line-height:1.6;margin:0 0 16px}
  .id{font-size:12px;color:#9CA3AF}
  a{display:inline-block;background:#6D28D9;color:#fff;text-decoration:none;padding:10px 16px;border-radius:8px;font-size:14px}
  .mo-debug{margin-top:16px;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;padding:12px;font-size:12px;overflow:auto;max-height:320px}
</style>
</head>
<body>
  <div class="box">
    <div class="card">
      <p class="code">{$code}</p>
      <h1>{$escapedTitle}</h1>
      <p>{$escapedMessage}</p>
      <a href="/">Kembali ke beranda</a>
      <p class="id" style="margin-top:16px">Request ID: {$id}</p>
      {$debugBlock}
    </div>
  </div>
</body>
</html>
HTML;
    }

    private static function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requested = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return str_contains($accept, 'application/json') || strtolower((string) $requested) === 'xmlhttprequest';
    }
}
