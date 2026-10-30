<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Perlindungan CSRF.
 *
 * Token disimpan pada sesi dan dibandingkan dengan nilai dari formulir atau
 * header `X-CSRF-Token` memakai hash_equals (tahan terhadap timing attack).
 */
final class Csrf
{
    public const FIELD = '_token';
    public const HEADER = 'X-CSRF-Token';

    public static function token(): string
    {
        return Session::csrfToken();
    }

    /**
     * Bidang tersembunyi untuk formulir.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . self::escape(self::token()) . '">';
    }

    /**
     * Meta tag untuk dibaca JavaScript.
     */
    public static function meta(): string
    {
        return '<meta name="csrf-token" content="' . self::escape(self::token()) . '">';
    }

    public static function verify(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        return hash_equals(self::token(), $token);
    }

    /**
     * Ambil token dari request (bidang formulir atau header).
     */
    public static function tokenFromRequest(Request $request): ?string
    {
        $token = $request->input(self::FIELD);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        return $request->header(self::HEADER);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
