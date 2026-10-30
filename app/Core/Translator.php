<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Translasi terpusat.
 *
 * Seluruh label antarmuka wajib melalui kelas ini. Kunci yang belum tersedia
 * dicatat agar dapat dilengkapi (`bin/mo i18n:check`), dan selalu jatuh ke
 * bahasa cadangan sebelum akhirnya menampilkan kuncinya sendiri.
 */
final class Translator
{
    /** @var array<string, array<string, string>> */
    private static array $loaded = [];

    /** @var array<string, int> */
    private static array $missing = [];

    private static ?string $locale = null;

    public static function locale(): string
    {
        if (self::$locale === null) {
            self::$locale = Config::string('app.locale.default', 'id');
        }

        return self::$locale;
    }

    public static function fallback(): string
    {
        return Config::string('app.locale.fallback', 'en');
    }

    /**
     * @return list<string>
     */
    public static function available(): array
    {
        $available = Config::array('app.locale.available');
        return $available === [] ? ['id', 'en'] : array_values($available);
    }

    public static function setLocale(?string $locale): void
    {
        if ($locale === null || $locale === '') {
            return;
        }

        if (!in_array($locale, self::available(), true)) {
            return;
        }

        self::$locale = $locale;
    }

    /**
     * @param array<string, mixed> $replace
     */
    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale ??= self::locale();

        $value = self::lookup($key, $locale);

        if ($value === null && $locale !== self::fallback()) {
            $value = self::lookup($key, self::fallback());
        }

        if ($value === null) {
            self::$missing[$key] = (self::$missing[$key] ?? 0) + 1;
            $value = $key;
        }

        return self::replace($value, $replace);
    }

    /**
     * @param array<string, mixed> $replace
     */
    public static function replace(string $value, array $replace): string
    {
        if ($replace === []) {
            return $value;
        }

        foreach ($replace as $search => $replacement) {
            $value = str_replace(':' . $search, (string) $replacement, $value);
            $value = str_replace('{' . $search . '}', (string) $replacement, $value);
        }

        return $value;
    }

    public static function has(string $key, ?string $locale = null): bool
    {
        return self::lookup($key, $locale ?? self::locale()) !== null;
    }

    /**
     * @return array<string, int>
     */
    public static function missing(): array
    {
        return self::$missing;
    }

    public static function resetMissing(): void
    {
        self::$missing = [];
    }

    /**
     * Muat berkas bahasa; hasil disimpan per locale.
     *
     * @return array<string, string>
     */
    public static function load(string $locale): array
    {
        if (isset(self::$loaded[$locale])) {
            return self::$loaded[$locale];
        }

        $path = Config::basePath('lang/' . $locale . '.php');

        if (!is_file($path)) {
            self::$loaded[$locale] = [];
            return [];
        }

        /** @var mixed $messages */
        $messages = require $path;

        self::$loaded[$locale] = is_array($messages) ? $messages : [];

        return self::$loaded[$locale];
    }

    private static function lookup(string $key, string $locale): ?string
    {
        $messages = self::load($locale);
        return isset($messages[$key]) && is_string($messages[$key]) ? $messages[$key] : null;
    }
}
