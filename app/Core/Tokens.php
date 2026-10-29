<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pembuatan token acak yang disimpan sebagai hash.
 *
 * Token mentah hanya dikirim kepada pengguna (tautan email) dan tidak pernah
 * disimpan; database menyimpan SHA-256 sehingga kebocoran database tidak
 * serta-merta memberikan akses.
 */
final class Tokens
{
    public static function generate(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return array{token: string, hash: string}
     */
    public static function make(int $bytes = 32): array
    {
        $token = self::generate($bytes);
        return ['token' => $token, 'hash' => self::hash($token)];
    }

    public static function equals(string $known, string $given): bool
    {
        return hash_equals($known, $given);
    }
}
