<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Database\Database;
use App\Services\AuditService;

/**
 * Konteks tenant dan perusahaan yang aktif.
 *
 * Aturan penting: nilai konteks hanya boleh berasal dari sesi yang sudah
 * divalidasi terhadap keanggotaan pengguna. Nilai `tenant_id` atau
 * `company_id` dari request tidak pernah dipercaya.
 */
final class TenantContext
{
    private const KEY_TENANT = 'mo.tenant_id';
    private const KEY_COMPANY = 'mo.company_id';

    /** @var array{tenant: array<string, mixed>|null, companies: list<array<string, mixed>>|null, permissions: list<string>|null} */
    private static array $cache = ['tenant' => null, 'companies' => null, 'permissions' => null];

    public static function id(): ?int
    {
        $id = Session::get(self::KEY_TENANT);
        return is_numeric($id) ? (int) $id : null;
    }

    public static function companyId(): ?int
    {
        $id = Session::get(self::KEY_COMPANY);
        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function tenant(): ?array
    {
        $id = self::id();

        if ($id === null) {
            return null;
        }

        if (self::$cache['tenant'] === null || (int) self::$cache['tenant']['id'] !== $id) {
            self::$cache['tenant'] = Database::selectOne(
                'SELECT id, name, slug, status, default_locale, timezone, date_format, owner_user_id, trial_ends_at'
                . ' FROM tenants WHERE id = ? AND deleted_at IS NULL',
                [$id]
            );
        }

        return self::$cache['tenant'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function company(): ?array
    {
        $companyId = self::companyId();
        $tenantId = self::id();

        if ($companyId === null || $tenantId === null) {
            return null;
        }

        return Database::selectOne(
            'SELECT id, tenant_id, name, legal_name, slug, status, logo_path, timezone, locale, date_format'
            . ' FROM companies WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$companyId, $tenantId]
        );
    }

    /**
     * Perusahaan yang dapat diakses pengguna pada tenant aktif.
     *
     * Bila `user_company_access` kosong, seluruh perusahaan aktif pada tenant
     * dapat diakses (untuk owner/admin). Bila terisi, hanya yang tercantum.
     *
     * @return list<array<string, mixed>>
     */
    public static function companies(): array
    {
        if (self::$cache['companies'] !== null) {
            return self::$cache['companies'];
        }

        $tenantId = self::id();
        $userId = Auth::id();

        if ($tenantId === null || $userId === null) {
            return [];
        }

        $restricted = Database::select(
            'SELECT c.id, c.name, c.legal_name, c.slug, c.status, c.logo_path, c.timezone'
            . ' FROM user_company_access uca'
            . ' INNER JOIN companies c ON c.id = uca.company_id AND c.deleted_at IS NULL'
            . ' WHERE uca.tenant_id = ? AND uca.user_id = ?'
            . ' ORDER BY c.name',
            [$tenantId, $userId]
        );

        if ($restricted !== []) {
            self::$cache['companies'] = $restricted;
            return $restricted;
        }

        $scopedRoles = Database::select(
            'SELECT DISTINCT ur.company_id FROM user_roles ur'
            . ' INNER JOIN companies c ON c.id = ur.company_id AND c.deleted_at IS NULL'
            . ' WHERE ur.tenant_id = ? AND ur.user_id = ? AND ur.company_id IS NOT NULL',
            [$tenantId, $userId]
        );

        if ($scopedRoles !== []) {
            $ids = array_map(static fn (array $row): int => (int) $row['company_id'], $scopedRoles);
            $placeholders = Database::placeholders(count($ids));

            $companies = Database::select(
                'SELECT id, name, legal_name, slug, status, logo_path, timezone FROM companies'
                . ' WHERE tenant_id = ? AND id IN (' . $placeholders . ') AND deleted_at IS NULL'
                . ' ORDER BY name',
                array_merge([$tenantId], $ids)
            );

            self::$cache['companies'] = $companies;
            return $companies;
        }

        $all = Database::select(
            'SELECT id, name, legal_name, slug, status, logo_path, timezone FROM companies'
            . ' WHERE tenant_id = ? AND deleted_at IS NULL AND status = ?'
            . ' ORDER BY name',
            [$tenantId, 'active']
        );

        self::$cache['companies'] = $all;

        return $all;
    }

    /**
     * Daftar tenant yang dapat dimasuki pengguna.
     *
     * @return list<array<string, mixed>>
     */
    public static function availableTenants(int $userId): array
    {
        return Database::select(
            'SELECT t.id, t.name, t.slug, t.status FROM tenant_memberships tm'
            . ' INNER JOIN tenants t ON t.id = tm.tenant_id AND t.deleted_at IS NULL'
            . ' WHERE tm.user_id = ? AND tm.status = ? AND t.status = ?'
            . ' ORDER BY t.name',
            [$userId, 'active', 'active']
        );
    }

    /**
     * Tetapkan tenant aktif setelah divalidasi terhadap keanggotaan.
     */
    public static function setTenant(int $tenantId, ?int $userId = null): bool
    {
        $userId ??= Auth::id();

        if ($userId === null) {
            return false;
        }

        $member = Database::selectOne(
            'SELECT id FROM tenant_memberships WHERE tenant_id = ? AND user_id = ? AND status = ?',
            [$tenantId, $userId, 'active']
        );

        if ($member === null) {
            // Platform admin dapat masuk hanya bila memiliki grant aktif.
            if (!self::hasSupportGrant($tenantId, $userId)) {
                return false;
            }
        }

        $tenant = Database::selectOne(
            'SELECT id, status FROM tenants WHERE id = ? AND deleted_at IS NULL',
            [$tenantId]
        );

        if ($tenant === null || $tenant['status'] !== 'active') {
            return false;
        }

        Session::set(self::KEY_TENANT, $tenantId);
        Session::forget(self::KEY_COMPANY);
        self::clearCache();

        // Pilih perusahaan pertama yang dapat diakses.
        $companies = self::companies();

        if ($companies !== []) {
            Session::set(self::KEY_COMPANY, (int) $companies[0]['id']);
        }

        return true;
    }

    /**
     * Ganti perusahaan aktif dengan validasi ulang.
     */
    public static function setCompany(int $companyId): bool
    {
        $tenantId = self::id();

        if ($tenantId === null) {
            return false;
        }

        foreach (self::companies() as $company) {
            if ((int) $company['id'] === $companyId) {
                Session::set(self::KEY_COMPANY, $companyId);
                self::clearCache();
                return true;
            }
        }

        Logger::warning('Percobaan mengakses perusahaan di luar cakupan', [
            'user_id' => Auth::id(),
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
        ]);

        return false;
    }

    public static function clear(): void
    {
        Session::forget(self::KEY_TENANT);
        Session::forget(self::KEY_COMPANY);
        self::clearCache();
    }

    public static function clearCache(): void
    {
        self::$cache = ['tenant' => null, 'companies' => null, 'permissions' => null];
    }

    /**
     * Tenant wajib tersedia; jika tidak, lemparkan 403.
     */
    public static function requireTenant(): int
    {
        $tenantId = self::id();

        if ($tenantId === null || self::tenant() === null) {
            throw HttpException::forbidden('Konteks perusahaan belum dipilih.');
        }

        return $tenantId;
    }

    /**
     * Perusahaan wajib tersedia; jika tidak, lemparkan 403.
     */
    public static function requireCompany(): int
    {
        self::requireTenant();

        $companyId = self::companyId();

        if ($companyId === null || self::company() === null) {
            throw HttpException::forbidden('Pilih perusahaan terlebih dahulu.');
        }

        return $companyId;
    }

    /**
     * Grant dukungan aktif untuk pengguna platform pada tenant tertentu.
     */
    public static function hasSupportGrant(int $tenantId, ?int $userId = null): bool
    {
        $userId ??= Auth::id();

        if ($userId === null) {
            return false;
        }

        $now = gmdate('Y-m-d H:i:s');

        $count = (int) Database::scalar(
            'SELECT COUNT(*) FROM support_access_grants'
            . ' WHERE tenant_id = ? AND granted_user_id = ? AND revoked_at IS NULL'
            . ' AND starts_at <= ? AND expires_at >= ?',
            [$tenantId, $userId, $now, $now],
            0
        );

        if ($count > 0) {
            AuditService::log($tenantId, 'platform.support_access_used', 'tenants', $tenantId, [], $userId);
        }

        return $count > 0;
    }
}
