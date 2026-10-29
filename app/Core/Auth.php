<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Status autentikasi pada sesi berjalan.
 *
 * Kelas ini hanya menyimpan data yang benar-benar diperlukan; seluruh data
 * lain (peran, perusahaan) diambil dari database sesuai kebutuhan untuk
 * menghindari data basi pada sesi.
 */
final class Auth
{
    private const KEY_USER = 'mo.auth.user';

    /**
     * Simpan data pengguna pada sesi.
     *
     * @param array<string, mixed> $user
     */
    public static function startSession(array $user): void
    {
        Session::regenerate(true);

        Session::set(self::KEY_USER, [
            'id'                => (int) $user['id'],
            'name'              => (string) $user['name'],
            'email'             => (string) $user['email'],
            'locale'            => (string) ($user['locale'] ?? Config::string('app.locale.default', 'id')),
            'timezone'          => (string) ($user['timezone'] ?? 'Asia/Jakarta'),
            'is_platform_admin' => (bool) ($user['is_platform_admin'] ?? false),
            'logged_in_at'      => gmdate('Y-m-d H:i:s'),
        ]);

        Session::regenerateCsrfToken();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        $user = Session::get(self::KEY_USER);
        return is_array($user) ? $user : null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user !== null ? (int) $user['id'] : null;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function isPlatformAdmin(): bool
    {
        $user = self::user();
        return $user !== null && (bool) ($user['is_platform_admin'] ?? false);
    }

    /**
     * Perbarui sebagian data pada sesi (mis. setelah perubahan preferensi).
     *
     * @param array<string, mixed> $data
     */
    public static function refresh(array $data): void
    {
        $user = self::user();

        if ($user === null) {
            return;
        }

        Session::set(self::KEY_USER, array_merge($user, $data));
    }

    public static function logout(): void
    {
        Session::forget(self::KEY_USER);
        Session::regenerate(true);
    }
}
