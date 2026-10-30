<?php

declare(strict_types=1);

namespace App\Core;


/**
 * Wadah aplikasi: memuat konfigurasi, menangani request, dan mengirim response.
 */
final class App
{
    private static ?self $instance = null;

    private string $basePath;
    private Router $router;
    private float $startedAt;
    private ?Request $currentRequest = null;

    private function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->router = new Router();
        $this->startedAt = microtime(true);
    }

    public static function boot(string $basePath): self
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }

        $app = new self($basePath);
        self::$instance = $app;

        Autoload::register($app->basePath);
        Config::load($app->basePath);

        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
        }

        date_default_timezone_set(Config::string('app.timezone', 'UTC'));

        ErrorHandler::register(Config::bool('app.debug', false));

        // Sesi tidak diperlukan pada CLI (migrasi, pengujian, cron).
        if (PHP_SAPI !== 'cli') {
            Session::start();
            self::bootstrapLocale();
        }

        return $app;
    }

    /**
     * Tentukan bahasa antarmuka untuk request ini.
     *
     * Urutan prioritas: pilihan di sesi (menu bahasa) → preferensi pengguna
     * yang masuk → nilai bawaan konfigurasi.
     */
    private static function bootstrapLocale(): void
    {
        $locale = Session::get('mo.locale');

        if (!is_string($locale) || $locale === '') {
            $user = Session::get(Auth::sessionKey());
            if (is_array($user) && isset($user['locale']) && is_string($user['locale'])) {
                $locale = $user['locale'];
            }
        }

        Translator::setLocale(is_string($locale) && $locale !== '' ? $locale : Config::string('app.locale.default', 'id'));
    }

    public static function instance(): self
    {
        if (!self::$instance instanceof self) {
            throw new \RuntimeException('Aplikasi belum di-boot. Panggil App::boot() terlebih dahulu.');
        }

        return self::$instance;
    }

    public static function isBooted(): bool
    {
        return self::$instance instanceof self;
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function currentRequest(): ?Request
    {
        return $this->currentRequest;
    }

    public function elapsedMs(): float
    {
        return round((microtime(true) - $this->startedAt) * 1000, 2);
    }

    /**
     * Tangani request dan kembalikan response tanpa mengirimkannya.
     *
     * @param bool $rethrow Lepaskan exception ke pemanggil (dipakai pengujian).
     */
    public function handle(Request $request, bool $rethrow = false): Response
    {
        $this->currentRequest = $request;

        try {
            return $this->router->dispatch($request);
        } catch (HttpException $e) {
            if ($rethrow) {
                throw $e;
            }
            return $this->errorResponse($e->status(), $e->getMessage());
        } catch (\Throwable $e) {
            if ($rethrow) {
                throw $e;
            }
            ErrorHandler::handle($e);
            return new Response('', 500);
        }
    }

    /**
     * Jalankan aplikasi: tangani request lalu kirim response.
     */
    public function run(?Request $request = null, bool $rethrow = false): void
    {
        $request ??= Request::createFromGlobals();
        $response = $this->handle($request, $rethrow);

        foreach (Config::array('security.headers') as $name => $value) {
            if (!headers_sent()) {
                header($name . ': ' . $value, true);
            }
        }

        $response->send();
    }

    private function errorResponse(int $status, string $message): Response
    {
        $request = $this->currentRequest;

        if ($request !== null && $request->wantsJson()) {
            return Response::json([
                'success' => false,
                'message' => $message,
                'request_id' => Request::id(),
            ], $status);
        }

        // Render halaman error sederhana; akan diganti view pada batch berikutnya.
        $safe = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return Response::html(
            '<!doctype html><html lang="id"><head><meta charset="utf-8">'
            . '<title>' . $status . '</title></head><body style="font-family:sans-serif;padding:40px">'
            . '<h1 style="color:#6D28D9">' . $status . '</h1><p>' . $safe . '</p>'
            . '<a href="/">Kembali ke beranda</a></body></html>',
            $status
        );
    }
}
