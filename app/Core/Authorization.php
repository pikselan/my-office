<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Database\Database;

/**
 * Pemeriksaan izin terpusat (RBAC).
 *
 * Permission dihitung dari role pengguna pada tenant aktif. Role platform
 * (baris user_roles dengan tenant_id NULL) TIDAK pernah memberi izin pada
 * konteks tenant — ini mencegah kebocoran hak lintas cakupan.
 */
final class Authorization
{
    /** @var array<string, list<string>> */
    private static array $cache = [];

    /**
     * Kumpulan kode permission pengguna pada tenant aktif.
     *
     * @return list<string>
     */
    public static function permissions(?int $tenantId = null, ?int $userId = null): array
    {
        $tenantId ??= TenantContext::id();
        $userId ??= Auth::id();

        if ($tenantId === null || $userId === null) {
            return [];
        }

        $key = $tenantId . ':' . $userId;

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $rows = Database::select(
            'SELECT DISTINCT p.code FROM user_roles ur'
            . ' INNER JOIN roles r ON r.id = ur.role_id'
            . ' INNER JOIN role_permissions rp ON rp.role_id = r.id'
            . ' INNER JOIN permissions p ON p.id = rp.permission_id'
            . ' WHERE ur.tenant_id = ? AND ur.user_id = ?',
            [$tenantId, $userId]
        );

        $codes = array_map(static fn (array $row): string => (string) $row['code'], $rows);

        self::$cache[$key] = $codes;

        return $codes;
    }

    /**
     * Kumpulan kode role pengguna pada tenant aktif.
     *
     * @return list<string>
     */
    public static function roleCodes(?int $tenantId = null, ?int $userId = null): array
    {
        $tenantId ??= TenantContext::id();
        $userId ??= Auth::id();

        if ($tenantId === null || $userId === null) {
            return [];
        }

        $rows = Database::select(
            'SELECT DISTINCT r.code FROM user_roles ur'
            . ' INNER JOIN roles r ON r.id = ur.role_id'
            . ' WHERE ur.tenant_id = ? AND ur.user_id = ?'
            . ' ORDER BY r.code',
            [$tenantId, $userId]
        );

        return array_map(static fn (array $row): string => (string) $row['code'], $rows);
    }

    /**
     * Periksa izin, dengan batasan perusahaan bila peran dibatasi.
     */
    public static function can(string $permission, ?int $companyId = null): bool
    {
        $userId = Auth::id();

        if ($userId === null) {
            return false;
        }

        $tenantId = TenantContext::id();

        if ($tenantId === null) {
            return false;
        }

        if (in_array($permission, self::permissions($tenantId, $userId), true)) {
            return self::withinCompanyScope($companyId);
        }

        return false;
    }

    public static function cannot(string $permission, ?int $companyId = null): bool
    {
        return !self::can($permission, $companyId);
    }

    /**
     * Lemparkan 403 bila izin tidak ada.
     */
    public static function require(string $permission, ?int $companyId = null): void
    {
        if (!self::can($permission, $companyId)) {
            throw HttpException::forbidden();
        }
    }

    /**
     * Periksa salah satu dari beberapa izin.
     *
     * @param list<string> $permissions
     */
    public static function canAny(array $permissions, ?int $companyId = null): bool
    {
        foreach ($permissions as $permission) {
            if (self::can($permission, $companyId)) {
                return true;
            }
        }

        return false;
    }

    public static function isPlatformAdmin(): bool
    {
        $userId = Auth::id();

        if ($userId === null) {
            return false;
        }

        $count = (int) Database::scalar(
            'SELECT COUNT(*) FROM user_roles ur'
            . ' INNER JOIN roles r ON r.id = ur.role_id'
            . ' INNER JOIN role_permissions rp ON rp.role_id = r.id'
            . ' INNER JOIN permissions p ON p.id = rp.permission_id'
            . ' WHERE ur.user_id = ? AND ur.tenant_id IS NULL AND p.code = ?',
            [$userId, 'platform.tenant.view'],
            0
        );

        return $count > 0;
    }

    /**
     * Batasi peran yang dibatasi pada perusahaan tertentu.
     */
    private static function withinCompanyScope(?int $companyId): bool
    {
        $companyId ??= TenantContext::companyId();

        if ($companyId === null) {
            return true;
        }

        $userId = Auth::id();
        $tenantId = TenantContext::id();

        if ($userId === null || $tenantId === null) {
            return false;
        }

        // Peran yang tidak dibatasi perusahaan tetap berlaku.
        $unrestricted = (int) Database::scalar(
            'SELECT COUNT(*) FROM user_roles WHERE tenant_id = ? AND user_id = ? AND company_id IS NULL',
            [$tenantId, $userId],
            0
        );

        if ($unrestricted > 0) {
            return true;
        }

        $scoped = (int) Database::scalar(
            'SELECT COUNT(*) FROM user_roles WHERE tenant_id = ? AND user_id = ? AND company_id = ?',
            [$tenantId, $userId, $companyId],
            0
        );

        return $scoped > 0;
    }

    /**
     * Bersihkan cache permission (dipakai setelah perubahan role).
     */
    public static function flush(?int $userId = null): void
    {
        if ($userId === null) {
            self::$cache = [];
            return;
        }

        foreach (array_keys(self::$cache) as $key) {
            if (str_ends_with($key, ':' . $userId)) {
                unset(self::$cache[$key]);
            }
        }
    }
}
