<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database\Database;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\Session;
use App\Core\Tokens;
use App\Core\Translator;
use App\Core\Validator;
use App\Repositories\UserRepository;

/**
 * Logika autentikasi: masuk, daftar, verifikasi email, dan reset kata sandi.
 *
 * Semua keputusan keamanan (throttle, penguncian, token) berada di sini agar
 * konsisten antara alur web dan API.
 */
final class AuthService
{
    /**
     * Percobaan masuk.
     *
     * @return array{ok: bool, user?: array<string, mixed>, error?: string, locked_until?: ?string}
     */
    public static function attempt(string $email, string $password, string $ip, string $userAgent): array
    {
        $email = mb_strtolower(trim($email), 'UTF-8');
        $maxAttempts = Config::int('security.login.max_attempts', 5);
        $lockMinutes = Config::int('security.login.lock_minutes', 15);

        $since = gmdate('Y-m-d H:i:s', time() - ($lockMinutes * 60));

        if (UserRepository::recentFailures($email, $ip, $lockMinutes, $since) >= $maxAttempts) {
            UserRepository::recordAttempt($email, $ip, $userAgent, false);
            return ['ok' => false, 'error' => 'locked'];
        }

        $user = UserRepository::findByEmail($email);

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            UserRepository::recordAttempt($email, $ip, $userAgent, false);

            if ($user !== null) {
                UserRepository::registerFailedLogin((int) $user['id'], $maxAttempts, $lockMinutes);
                AuditService::log(null, 'auth.login_failed', 'users', (int) $user['id'], ['email' => $email]);
            }

            // Respons dibuat generik agar keberadaan akun tidak terbaca.
            return ['ok' => false, 'error' => 'invalid'];
        }

        if (UserRepository::isLocked($user)) {
            return ['ok' => false, 'error' => 'locked', 'locked_until' => $user['locked_until'] ?? null];
        }

        UserRepository::recordAttempt($email, $ip, $userAgent, true);
        UserRepository::recordLogin((int) $user['id']);

        // Rehash bila algoritma bawaan berubah.
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            UserRepository::update((int) $user['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        }

        AuditService::log(null, 'auth.login', 'users', (int) $user['id']);

        return ['ok' => true, 'user' => $user];
    }

    /**
     * Masukkan pengguna ke sesi dan catat sesinya.
     *
     * @param array<string, mixed> $user
     */
    public static function establishSession(array $user, string $ip, string $userAgent): void
    {
        Auth::startSession($user);

        UserRepository::createSessionRecord(
            (int) $user['id'],
            Session::id(),
            $ip,
            $userAgent,
            Config::int('session.lifetime', 28800)
        );
    }

    /**
     * Registrasi owner baru beserta tenant dan perusahaan pertamanya.
     *
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors?: array<string, list<string>>, data?: array<string, int>}
     */
    public static function registerOwner(array $input): array
    {
        $errors = Validator::validate($input, [
            'owner_name'  => 'required|min:2|max:150',
            'email'       => 'required|email|max:190|unique:users,email',
            'password'    => 'required|min_chars:' . Config::int('security.password.min_length', 8) . '|max_chars:200|confirmed',
            'tenant_name' => 'required|min:2|max:150',
            'company_name' => 'required|min:2|max:190',
        ], [
            'owner_name'   => 'Nama lengkap',
            'email'        => 'Email',
            'password'     => 'Kata sandi',
            'tenant_name'  => 'Nama tenant',
            'company_name' => 'Nama perusahaan',
        ]);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        try {
            $result = TenantService::provision($input);
        } catch (\Throwable $e) {
            Logger::error('Registrasi gagal', ['error' => $e->getMessage()]);
            return ['ok' => false, 'errors' => ['_general' => [Translator::get('message.error')]]];
        }

        self::sendVerificationLink($result['user_id']);

        return ['ok' => true, 'data' => $result];
    }

    /**
     * Kirim tautan verifikasi email.
     */
    public static function sendVerificationLink(int $userId): bool
    {
        $user = UserRepository::find($userId);

        if ($user === null || $user['email_verified_at'] !== null) {
            return false;
        }

        $token = Tokens::make();
        $expiresAt = gmdate('Y-m-d H:i:s', time() + (Config::int('security.tokens.verification_ttl_hours', 24) * 3600));

        Database::statement('DELETE FROM email_verification_tokens WHERE user_id = ? AND used_at IS NULL', [$userId]);

        Database::insertRow('email_verification_tokens', [
            'user_id'    => $userId,
            'token_hash' => $token['hash'],
            'expires_at' => $expiresAt,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $link = self::absoluteUrl('/verify-email/' . $token['token']);

        Mailer::send(
            (string) $user['email'],
            'Verifikasi email MO',
            "Halo {$user['name']},\n\nSilakan buka tautan berikut untuk memverifikasi email Anda:\n{$link}\n\n"
            . 'Tautan berlaku selama ' . Config::int('security.tokens.verification_ttl_hours', 24) . " jam.\n\n"
            . "Abaikan surel ini bila Anda tidak mendaftar di MO."
        );

        return true;
    }

    public static function verifyEmail(string $token): bool
    {
        $hash = Tokens::hash($token);

        $row = Database::selectOne(
            'SELECT id, user_id, expires_at FROM email_verification_tokens WHERE token_hash = ? AND used_at IS NULL LIMIT 1',
            [$hash]
        );

        if ($row === null) {
            return false;
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            return false;
        }

        UserRepository::markEmailVerified((int) $row['user_id']);

        Database::statement('UPDATE email_verification_tokens SET used_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), (int) $row['id']]);

        AuditService::log(null, 'auth.email_verified', 'users', (int) $row['user_id']);

        return true;
    }

    /**
     * Kirim tautan reset kata sandi.
     *
     * Selalu mengembalikan true agar keberadaan email tidak terbaca dari respons.
     */
    public static function sendPasswordReset(string $email, string $ip): bool
    {
        $user = UserRepository::findByEmail($email);

        if ($user === null) {
            return true;
        }

        $token = Tokens::make();
        $expiresAt = gmdate('Y-m-d H:i:s', time() + (Config::int('security.tokens.reset_ttl_minutes', 60) * 60));

        Database::statement('DELETE FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL', [(int) $user['id']]);

        Database::insertRow('password_reset_tokens', [
            'user_id'    => (int) $user['id'],
            'token_hash' => $token['hash'],
            'ip_address' => $ip,
            'expires_at' => $expiresAt,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $link = self::absoluteUrl('/reset-password/' . $token['token']);

        Mailer::send(
            (string) $user['email'],
            'Reset kata sandi MO',
            "Halo {$user['name']},\n\nGunakan tautan berikut untuk mengatur ulang kata sandi Anda:\n{$link}\n\n"
            . 'Tautan berlaku selama ' . Config::int('security.tokens.reset_ttl_minutes', 60) . " menit.\n\n"
            . 'Abaikan surel ini bila Anda tidak meminta reset kata sandi.'
        );

        AuditService::log(null, 'auth.password_reset_requested', 'users', (int) $user['id']);

        return true;
    }

    public static function resetPassword(string $token, string $password): bool
    {
        $hash = Tokens::hash($token);

        $row = Database::selectOne(
            'SELECT id, user_id, expires_at FROM password_reset_tokens WHERE token_hash = ? AND used_at IS NULL LIMIT 1',
            [$hash]
        );

        if ($row === null || strtotime((string) $row['expires_at']) < time()) {
            return false;
        }

        $minLength = Config::int('security.password.min_length', 8);

        if (strlen($password) < $minLength) {
            return false;
        }

        UserRepository::update((int) $row['user_id'], [
            'password_hash'     => password_hash($password, PASSWORD_DEFAULT),
            'failed_login_count' => 0,
            'locked_until'      => null,
        ]);

        Database::statement('UPDATE password_reset_tokens SET used_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), (int) $row['id']]);

        // Seluruh sesi aktif dicabut setelah reset kata sandi.
        UserRepository::destroyAllSessions((int) $row['user_id']);

        AuditService::log(null, 'auth.password_reset_completed', 'users', (int) $row['user_id']);

        return true;
    }

    /**
     * Ubah kata sandi dengan pemeriksaan kata sandi lama.
     */
    public static function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $user = UserRepository::find($userId);

        if ($user === null || !password_verify($currentPassword, (string) $user['password_hash'])) {
            return false;
        }

        if (strlen($newPassword) < Config::int('security.password.min_length', 8)) {
            return false;
        }

        UserRepository::update($userId, ['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)]);

        AuditService::log(null, 'auth.password_changed', 'users', $userId);

        return true;
    }

    public static function logout(): void
    {
        $id = Auth::id();

        if ($id !== null) {
            UserRepository::destroySession(Session::id());
            AuditService::log(null, 'auth.logout', 'users', $id);
        }

        Auth::logout();
    }

    /**
     * URL absolut untuk tautan pada surel.
     */
    private static function absoluteUrl(string $path): string
    {
        return rtrim(Config::string('app.url', 'http://localhost:8000'), '/') . '/' . ltrim($path, '/');
    }
}
