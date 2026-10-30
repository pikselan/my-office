<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Auth;
use App\Core\Database\Database;
use App\Core\Session;
use App\Core\TenantContext;
use App\Repositories\UserRepository;
use App\Services\AuthService;

/**
 * Pembuat data untuk pengujian.
 */
final class TestFactory
{
    /**
     * Buat tenant baru lengkap dengan owner, perusahaan, dan langganan.
     *
     * @param array<string, mixed> $overrides
     * @return array{user_id: int, tenant_id: int, company_id: int, subscription_id: int, email: string, password: string}
     */
    public static function tenant(array $overrides = []): array
    {
        $suffix = bin2hex(random_bytes(4));
        $password = 'Rahasia123!';

        $input = array_merge([
            'owner_name'           => 'Owner ' . $suffix,
            'email'                => 'owner' . $suffix . '@contoh.test',
            'password'             => $password,
            'password_confirmation' => $password,
            'tenant_name'          => 'Tenant ' . $suffix,
            'company_name'         => 'Perusahaan ' . $suffix,
            'plan_code'            => 'business',
        ], $overrides);

        $result = AuthService::registerOwner($input);

        if (!$result['ok']) {
            throw new \RuntimeException('Pembuatan tenant uji gagal: ' . json_encode($result['errors'] ?? []));
        }

        return $result['data'] + ['email' => (string) $input['email'], 'password' => $password];
    }

    /**
     * Buat pengguna tambahan pada tenant tertentu.
     */
    public static function user(int $tenantId, string $roleCode = 'tenant.employee', ?int $companyId = null): int
    {
        $suffix = bin2hex(random_bytes(4));

        $userId = UserRepository::create([
            'name'         => 'Pengguna ' . $suffix,
            'email'        => 'pengguna' . $suffix . '@contoh.test',
            'password_hash' => password_hash('Rahasia123!', PASSWORD_DEFAULT),
            'status'       => 'active',
        ]);

        Database::insertRow('tenant_memberships', [
            'tenant_id'  => $tenantId,
            'user_id'    => $userId,
            'status'     => 'active',
            'joined_at'  => gmdate('Y-m-d H:i:s'),
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        self::assignRole($tenantId, $userId, $roleCode, $companyId);

        return $userId;
    }

    public static function assignRole(int $tenantId, int $userId, string $roleCode, ?int $companyId = null): void
    {
        $roleId = (int) Database::scalar(
            'SELECT id FROM roles WHERE code = ? AND (tenant_id IS NULL OR tenant_id = ?) ORDER BY tenant_id IS NULL DESC LIMIT 1',
            [$roleCode, $tenantId],
            0
        );

        if ($roleId === 0) {
            throw new \RuntimeException('Role tidak ditemukan: ' . $roleCode);
        }

        Database::insertRow('user_roles', [
            'tenant_id'  => $tenantId,
            'user_id'    => $userId,
            'role_id'    => $roleId,
            'company_id' => $companyId,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        \App\Core\Authorization::flush($userId);
    }

    /**
     * Batasi pengguna pada perusahaan tertentu.
     */
    public static function restrictToCompany(int $tenantId, int $userId, int $companyId): void
    {
        Database::insertRow('user_company_access', [
            'tenant_id'  => $tenantId,
            'user_id'    => $userId,
            'company_id' => $companyId,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        TenantContext::clearCache();
    }

    /**
     * Masuk sebagai pengguna tertentu (dan pilih tenant bila diberikan).
     */
    public static function actingAs(int $userId, ?int $tenantId = null): void
    {
        $user = UserRepository::find($userId);

        if ($user === null) {
            throw new \RuntimeException('Pengguna uji tidak ditemukan: ' . $userId);
        }

        Auth::startSession($user);

        if ($tenantId !== null) {
            TenantContext::setTenant($tenantId, $userId);
        }
    }

    /**
     * Keluar dan bersihkan konteks.
     */
    public static function reset(): void
    {
        Auth::logout();
        TenantContext::clear();
        \App\Core\Authorization::flush();
        Session::set('mo.tenant_id', null);
        Session::forget('mo.tenant_id');
        Session::forget('mo.company_id');
    }

    /**
     * Perusahaan tambahan pada tenant.
     */
    public static function company(int $tenantId, string $name): int
    {
        return Database::insertRow('companies', [
            'tenant_id'  => $tenantId,
            'name'       => $name,
            'slug'       => \App\Services\TenantService::uniqueCompanySlug($tenantId, $name),
            'status'     => 'active',
            'locale'     => 'id',
            'timezone'   => 'Asia/Jakarta',
            'date_format' => 'd/m/Y',
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
