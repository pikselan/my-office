<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Perender template PHP.
 *
 * Penamaan:
 *   'layouts/app'                -> resources/views/layouts/app.php
 *   'components/button'          -> resources/views/components/button.php
 *   'Employees::pages/index'     -> modules/Employees/views/pages/index.php
 *
 * View tidak mengambil data sendiri; seluruh data dikirim dari controller.
 */
final class View
{
    /** @var array<string, mixed> */
    private static array $shared = [];

    /**
     * Data yang tersedia pada seluruh view.
     *
     * @param array<string, mixed> $data
     */
    public static function share(array $data): void
    {
        self::$shared = array_merge(self::$shared, $data);
    }

    public static function exists(string $view): bool
    {
        return self::resolve($view) !== null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], ?string $layout = null): string
    {
        $path = self::resolve($view);

        if ($path === null) {
            throw new \RuntimeException('View tidak ditemukan: ' . $view);
        }

        $content = self::renderFile($path, $data);

        if ($layout === null) {
            return $content;
        }

        $layoutPath = self::resolve($layout);

        if ($layoutPath === null) {
            throw new \RuntimeException('Layout tidak ditemukan: ' . $layout);
        }

        return self::renderFile($layoutPath, array_merge($data, ['content' => $content]));
    }

    public static function resolve(string $view): ?string
    {
        $view = str_replace('\\', '/', $view);
        $base = Config::basePath();

        if (str_contains($view, '::')) {
            [$module, $relative] = explode('::', $view, 2);
            $path = $base . '/modules/' . $module . '/views/' . ltrim($relative, '/') . '.php';
            return is_file($path) ? $path : null;
        }

        $path = $base . '/resources/views/' . ltrim($view, '/') . '.php';
        return is_file($path) ? $path : null;
    }

    /**
     * Escaping kontekstual untuk HTML.
     */
    public static function escape(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_array($value) || is_object($value)) {
            return htmlspecialchars((string) json_encode($value, JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function renderFile(string $path, array $data): string
    {
        $data = array_merge(self::$shared, $data);

        extract($data, EXTR_SKIP);

        ob_start();
        try {
            require $path;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    /**
     * Bersihkan data bersama (dipakai pengujian).
     */
    public static function clearShared(): void
    {
        self::$shared = [];
    }
}
