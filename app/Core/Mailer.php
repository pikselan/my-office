<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pengirim surel sederhana.
 *
 * Driver `log` menulis surel ke storage/logs/mail.log sehingga alur
 * verifikasi dan reset kata sandi dapat diuji tanpa SMTP.
 *
 * Driver `smtp` belum diimplementasikan; bila dipilih, pengiriman jatuh ke
 * log dan dicatat sebagai peringatan. Ini disengaja: lebih baik jujur
 * daripada mengklaim dukungan yang belum diuji.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $body, array $headers = []): bool
    {
        $driver = Config::string('mail.driver', 'log');

        if ($driver === 'smtp') {
            Logger::warning('Driver SMTP belum diimplementasikan; surel dicatat ke log.', ['to' => $to]);
        }

        return self::log($to, $subject, $body, $headers);
    }

    /**
     * Tulis surel ke berkas log.
     *
     * @param array<string, string> $headers
     */
    public static function log(string $to, string $subject, string $body, array $headers = []): bool
    {
        $directory = Config::basePath('storage/logs');

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $message = sprintf(
            "%s\nKepada: %s\nSubjek: %s\n%s\n%s\n%s",
            str_repeat('-', 60),
            $to,
            $subject,
            $headers === [] ? '' : json_encode($headers, JSON_UNESCAPED_UNICODE),
            $body,
            str_repeat('-', 60) . PHP_EOL
        );

        return file_put_contents($directory . '/mail.log', $message . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
    }

    /**
     * Bangun isi surel dari template teks sederhana.
     *
     * @param array<string, string> $replace
     */
    public static function render(string $template, array $replace): string
    {
        $path = Config::basePath('resources/views/emails/' . $template . '.php');

        if (!is_file($path)) {
            return '';
        }

        extract($replace, EXTR_SKIP);
        ob_start();
        require $path;
        return (string) ob_get_clean();
    }
}
