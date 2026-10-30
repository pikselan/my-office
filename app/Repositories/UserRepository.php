<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Akses data pengguna.
 *
 * Pengguna bersifat global (satu akun dapat menjadi anggota beberapa tenant),
 * sehingga query pengguna tidak memakai filter tenant.
 */
final class UserRepository
{
    private const COLUMNS = 'id, name, email, password_hash, locale, timezone, status, is_platform_admin,'
        . ' email_verified_at, last_login_at, failed_login_count, locked_until, created_at';

    /**
     * @return array<string, mixed>|null
     */
    public static function findByEmail(string $email): ?array
    {
        return Database::selectOne(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1',
            [mb_strtolower(trim($email), 'UTF-8')]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        return Database::selectOne(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1',
            [$id]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $data['email'] = mb_strtolower(trim((string) $data['email']), 'UTF-8');
        $data['created_at'] = now();
        $data['updated_at'] = now();

        return Database::insertRow('users', $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int $id, array $data): void
    {
        $data['updated_at'] = now();
        Database::updateRow('users', $data, 'id = ?', [$id]);
    }

    public static function markEmailVerified(int $id): void
    {
        self::update($id, ['email_verified_at' => now()]);
    }

    public static function recordLogin(int $id): void
    {
        self::update($id, [
            'last_login_at' => now(),
            'failed_login_count' => 0,
            'locked_until' => null,
        ]);
    }

    /**
     * Catat kegagalan dan kunci akun bila ambang terlampaui.
     *
     * @return array{failed: int, locked: bool, locked_until: ?string}
     */
    public static function registerFailedLogin(int $id, int $maxAttempts, int $lockMinutes): array
    {
        Database::statement(
            'UPDATE users SET failed_login_count = failed_login_count + 1, updated_at = ? WHERE id = ?',
            [now(), $id]
        );

        $row = Database::selectOne('SELECT failed_login_count FROM users WHERE id = ?', [$id]);
        $failed = (int) ($row['failed_login_count'] ?? 0);

        if ($failed < $maxAttempts) {
            return ['failed' => $failed, 'locked' => false, 'locked_until' => null];
        }

        $until = gmdate('Y-m-d H:i:s', time() + ($lockMinutes * 60));
        Database::statement('UPDATE users SET locked_until = ?, updated_at = ? WHERE id = ?', [$until, now(), $id]);

        return ['failed' => $failed, 'locked' => true, 'locked_until' => $until];
    }

    /**
     * Catat percobaan login untuk deteksi throttling berbasis IP/email.
     */
    public static function recordAttempt(string $email, string $ip, string $userAgent, bool $successful): void
    {
        Database::insertRow('login_attempts', [
            'email'       => mb_strtolower(trim($email), 'UTF-8'),
            'ip_address'  => $ip,
            'user_agent'  => mb_substr($userAgent, 0, 255, 'UTF-8'),
            'successful'  => $successful ? 1 : 0,
            'created_at'  => now(),
        ]);
    }

    /**
     * Jumlah percobaan gagal dalam rentang menit tertentu.
     */
    public static function recentFailures(string $email, string $ip, int $minutes, string $since): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM login_attempts WHERE successful = 0 AND created_at >= ? AND (email = ? OR ip_address = ?)',
            [$since, mb_strtolower(trim($email), 'UTF-8'), $ip],
            0
        );
    }

    public static function isLocked(array $user): bool
    {
        if (($user['status'] ?? 'active') !== 'active') {
            return true;
        }

        $lockedUntil = $user['locked_until'] ?? null;

        if ($lockedUntil === null) {
            return false;
        }

        return strtotime((string) $lockedUntil) > time();
    }

    /**
     * Daftar tenant tempat pengguna menjadi anggota.
     *
     * @return list<array<string, mixed>>
     */
    public static function memberships(int $userId): array
    {
        return Database::select(
            'SELECT t.id, t.name, t.slug, t.status, t.default_locale, t.timezone, tm.status AS membership_status'
            . ' FROM tenant_memberships tm'
            . ' INNER JOIN tenants t ON t.id = tm.tenant_id AND t.deleted_at IS NULL'
            . ' WHERE tm.user_id = ? AND tm.status = ?'
            . ' ORDER BY t.name',
            [$userId, 'active']
        );
    }

    /**
     * Simpan baris sesi untuk manajemen sesi (daftar perangkat, pencabutan).
     */
    public static function createSessionRecord(int $userId, string $sessionId, string $ip, string $userAgent, int $lifetime): int
    {
        return Database::insertRow('user_sessions', [
            'user_id'          => $userId,
            'session_id_hash'  => hash('sha256', $sessionId),
            'ip_address'       => $ip,
            'user_agent'       => mb_substr($userAgent, 0, 255, 'UTF-8'),
            'last_activity_at' => now(),
            'expires_at'       => gmdate('Y-m-d H:i:s', time() + $lifetime),
            'created_at'       => now(),
        ]);
    }

    public static function touchSession(string $sessionId): void
    {
        Database::statement(
            'UPDATE user_sessions SET last_activity_at = ? WHERE session_id_hash = ?',
            [now(), hash('sha256', $sessionId)]
        );
    }

    public static function destroySession(string $sessionId): void
    {
        Database::statement('DELETE FROM user_sessions WHERE session_id_hash = ?', [hash('sha256', $sessionId)]);
    }

    public static function destroyAllSessions(int $userId): void
    {
        Database::statement('DELETE FROM user_sessions WHERE user_id = ?', [$userId]);
    }
}
