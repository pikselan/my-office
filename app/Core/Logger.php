<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pencatat kejadian aplikasi ke berkas harian pada storage/logs.
 *
 * Aturan: tidak pernah menulis password, token mentah, isi dokumen, atau
 * seluruh body request. Kunci yang berpotensi sensitif disaring.
 */
final class Logger
{
    public const DEBUG = 'DEBUG';
    public const INFO = 'INFO';
    public const WARNING = 'WARNING';
    public const ERROR = 'ERROR';
    public const CRITICAL = 'CRITICAL';

    /** Kunci yang nilainya tidak boleh ditulis ke log. */
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'new_password',
        'token', 'csrf_token', 'api_key', 'app_key', 'secret', 'authorization',
        'cookie', 'set-cookie', 'card_number', 'cvv', 'db_password',
    ];

    public static function debug(string $message, array $context = []): void
    {
        self::write(self::DEBUG, $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write(self::INFO, $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write(self::WARNING, $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write(self::ERROR, $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::write(self::CRITICAL, $message, $context);
    }

    public static function write(string $level, string $message, array $context = []): void
    {
        $directory = Config::basePath('storage/logs');

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $file = $directory . '/mo-' . gmdate('Y-m-d') . '.log';
        $line = sprintf(
            "[%s] %s: %s %s%s",
            gmdate('Y-m-d H:i:s'),
            $level,
            $message,
            $context !== [] ? self::encodeContext($context) : '',
            PHP_EOL
        );

        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Bersihkan berkas log yang lebih tua dari jumlah hari tertentu.
     */
    public static function prune(int $retentionDays): int
    {
        $directory = Config::basePath('storage/logs');
        if (!is_dir($directory)) {
            return 0;
        }

        $limit = time() - ($retentionDays * 86400);
        $removed = 0;

        foreach (glob($directory . '/mo-*.log') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $limit) {
                @unlink($file);
                $removed++;
            }
        }

        return $removed;
    }

    private static function encodeContext(array $context): string
    {
        return (string) json_encode(
            self::scrub($context),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
    }

    /**
     * Ganti nilai kunci sensitif dengan penanda.
     */
    public static function scrub(array $context): array
    {
        foreach ($context as $key => $value) {
            $normalized = strtolower(str_replace('-', '_', (string) $key));

            if (in_array($normalized, self::SENSITIVE_KEYS, true)) {
                $context[$key] = '[disaring]';
                continue;
            }

            if (is_array($value)) {
                $context[$key] = self::scrub($value);
            }
        }

        return $context;
    }
}
