<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database\Database;
use App\Core\Session;
use App\Repositories\UserRepository;

/**
 * Pembuatan tenant baru beserta data awalnya.
 *
 * Seluruh proses berada dalam satu transaksi agar tidak ada tenant setengah
 * jadi ketika terjadi kegagalan di tengah proses.
 */
final class TenantService
{
    /**
     * @param array<string, mixed> $input
     * @return array{user_id: int, tenant_id: int, company_id: int, subscription_id: int}
     */
    public static function provision(array $input): array
    {
        return Database::transaction(static function () use ($input): array {
            $now = gmdate('Y-m-d H:i:s');

            $planCode = (string) ($input['plan_code'] ?? 'starter');
            $plan = Database::selectOne('SELECT id, trial_days, employee_limit, company_limit FROM plans WHERE code = ?', [$planCode])
                ?? Database::selectOne('SELECT id, trial_days, employee_limit, company_limit FROM plans WHERE is_public = 1 ORDER BY sort_order LIMIT 1');

            if ($plan === null) {
                throw new \RuntimeException('Paket langganan belum tersedia. Jalankan seed paket terlebih dahulu.');
            }

            $trialDays = (int) ($plan['trial_days'] ?? 14);
            $trialEndsAt = gmdate('Y-m-d H:i:s', time() + ($trialDays * 86400));

            $userId = UserRepository::create([
                'name'              => trim((string) $input['owner_name']),
                'email'             => (string) $input['email'],
                'password_hash'     => password_hash((string) $input['password'], PASSWORD_DEFAULT),
                'locale'            => (string) ($input['locale'] ?? Config::string('app.locale.default', 'id')),
                'timezone'          => (string) ($input['timezone'] ?? 'Asia/Jakarta'),
                'status'            => 'active',
                'is_platform_admin' => 0,
            ]);

            $tenantSlug = self::uniqueTenantSlug((string) $input['tenant_name']);

            $tenantId = Database::insertRow('tenants', [
                'name'           => trim((string) $input['tenant_name']),
                'slug'           => $tenantSlug,
                'status'         => 'active',
                'default_locale' => (string) ($input['locale'] ?? Config::string('app.locale.default', 'id')),
                'timezone'       => (string) ($input['timezone'] ?? 'Asia/Jakarta'),
                'date_format'    => 'd/m/Y',
                'owner_user_id'  => $userId,
                'trial_ends_at'  => $trialEndsAt,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);

            Database::insertRow('tenant_memberships', [
                'tenant_id'  => $tenantId,
                'user_id'    => $userId,
                'status'     => 'active',
                'joined_at'  => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $ownerRoleId = (int) Database::scalar(
                'SELECT id FROM roles WHERE tenant_id IS NULL AND code = ?',
                ['tenant.owner'],
                0
            );

            if ($ownerRoleId > 0) {
                Database::insertRow('user_roles', [
                    'tenant_id'  => $tenantId,
                    'user_id'    => $userId,
                    'role_id'    => $ownerRoleId,
                    'company_id' => null,
                    'created_at' => $now,
                ]);
            }

            $companyName = trim((string) ($input['company_name'] !== '' ? $input['company_name'] : $input['tenant_name']));
            $companyId = Database::insertRow('companies', [
                'tenant_id'    => $tenantId,
                'name'         => $companyName,
                'legal_name'   => null,
                'slug'         => self::uniqueCompanySlug($tenantId, $companyName),
                'locale'       => (string) ($input['locale'] ?? Config::string('app.locale.default', 'id')),
                'timezone'     => (string) ($input['timezone'] ?? 'Asia/Jakarta'),
                'date_format'  => 'd/m/Y',
                'work_days_json' => json_encode(['mon', 'tue', 'wed', 'thu', 'fri']),
                'status'       => 'active',
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);

            $subscriptionId = Database::insertRow('subscriptions', [
                'tenant_id'            => $tenantId,
                'plan_id'              => (int) $plan['id'],
                'status'               => 'trialing',
                'billing_cycle'        => (string) ($input['billing_cycle'] ?? 'monthly'),
                'starts_at'            => $now,
                'trial_ends_at'        => $trialEndsAt,
                'current_period_start' => $now,
                'current_period_end'   => $trialEndsAt,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);

            Database::insertRow('subscription_events', [
                'tenant_id'       => $tenantId,
                'subscription_id' => $subscriptionId,
                'event_type'      => 'trial_started',
                'description'     => 'Masa percobaan dimulai selama ' . $trialDays . ' hari.',
                'actor_user_id'   => $userId,
                'created_at'      => $now,
            ]);

            self::createDefaultSettings($tenantId, $companyId, $now);
            self::createDefaultLeavePolicies($tenantId, $companyId, $now);
            self::createDefaultSchedule($tenantId, $companyId, $now);

            AuditService::log($tenantId, 'tenant.provisioned', 'tenants', $tenantId, [
                'company_id' => $companyId,
                'plan_code'  => $planCode,
                'trial_days' => $trialDays,
            ], $userId);

            return [
                'user_id'         => $userId,
                'tenant_id'       => $tenantId,
                'company_id'      => $companyId,
                'subscription_id' => $subscriptionId,
            ];
        });
    }

    /**
     * Pengaturan awal perusahaan mengikuti referensi visibilitas.
     */
    private static function createDefaultSettings(int $tenantId, int $companyId, string $now): void
    {
        $settings = [
            'visibility.birthday'    => 'all',
            'visibility.anniversary' => 'all',
            'visibility.leave'       => 'hr',
            'visibility.whatsapp'    => 'all',
            'visibility.email'       => 'all',
            'visibility.directory'   => 'all',
            'work.days'              => json_encode(['mon', 'tue', 'wed', 'thu', 'fri']),
            'leave.approval_levels'  => '1',
        ];

        foreach ($settings as $key => $value) {
            Database::insertRow('company_settings', [
                'tenant_id'     => $tenantId,
                'company_id'    => $companyId,
                'setting_key'   => $key,
                'setting_value' => $value,
                'updated_at'    => $now,
            ]);
        }

        Database::insertRow('tenant_settings', [
            'tenant_id'     => $tenantId,
            'setting_key'   => 'onboarding.completed_steps',
            'setting_value' => json_encode([]),
            'updated_at'    => $now,
        ]);
    }

    private static function createDefaultLeavePolicies(int $tenantId, int $companyId, string $now): void
    {
        $types = Database::select('SELECT id, default_quota_days FROM leave_types WHERE tenant_id IS NULL AND is_active = 1');

        foreach ($types as $type) {
            Database::insertRow('leave_policies', [
                'tenant_id'       => $tenantId,
                'company_id'      => $companyId,
                'leave_type_id'   => (int) $type['id'],
                'accrual_type'    => 'annual',
                'accrual_days'    => (float) $type['default_quota_days'],
                'carry_over_days' => 0,
                'max_carry_over_days' => 0,
                'min_working_days_before' => 1,
                'allow_negative'  => 0,
                'effective_from'  => gmdate('Y-m-d'),
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }

    private static function createDefaultSchedule(int $tenantId, int $companyId, string $now): void
    {
        Database::insertRow('work_schedules', [
            'tenant_id'      => $tenantId,
            'company_id'     => $companyId,
            'name'           => 'Jam Kerja Kantor',
            'schedule_type'  => 'fixed',
            'work_days_json' => json_encode(['mon', 'tue', 'wed', 'thu', 'fri']),
            'start_time'     => '09:00',
            'end_time'       => '18:00',
            'grace_minutes'  => 15,
            'daily_hours'    => 8.00,
            'is_default'     => 1,
            'status'         => 'active',
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
    }

    public static function uniqueTenantSlug(string $name): string
    {
        $base = self::slug($name);
        $slug = $base;
        $counter = 1;

        while ((int) Database::scalar('SELECT COUNT(*) FROM tenants WHERE slug = ?', [$slug], 0) > 0) {
            $slug = $base . '-' . (++$counter);
        }

        return $slug;
    }

    public static function uniqueCompanySlug(int $tenantId, string $name): string
    {
        $base = self::slug($name);
        $slug = $base;
        $counter = 1;

        while ((int) Database::scalar('SELECT COUNT(*) FROM companies WHERE tenant_id = ? AND slug = ?', [$tenantId, $slug], 0) > 0) {
            $slug = $base . '-' . (++$counter);
        }

        return $slug;
    }

    public static function slug(string $value): string
    {
        $value = function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value));
        $value = (string) preg_replace('/[^a-z0-9]+/u', '-', $value);
        $value = trim($value, '-');
        return $value !== '' ? $value : 'perusahaan';
    }

    /**
     * Daftar perusahaan pada tenant.
     *
     * @return list<array<string, mixed>>
     */
    public static function companies(int $tenantId, bool $includeArchived = false): array
    {
        $sql = 'SELECT id, name, legal_name, slug, status, logo_path, timezone, city'
            . ' FROM companies WHERE tenant_id = ? AND deleted_at IS NULL';

        if (!$includeArchived) {
            $sql .= " AND status = 'active'";
        }

        return Database::select($sql . ' ORDER BY name', [$tenantId]);
    }

    /**
     * Penggunaan tenant untuk keperluan batas paket.
     *
     * @return array{employees: int, companies: int}
     */
    public static function usage(int $tenantId): array
    {
        $employees = (int) Database::scalar(
            "SELECT COUNT(*) FROM employees WHERE tenant_id = ? AND deleted_at IS NULL AND employment_status IN ('active','probation')",
            [$tenantId],
            0
        );

        $companies = (int) Database::scalar(
            "SELECT COUNT(*) FROM companies WHERE tenant_id = ? AND deleted_at IS NULL AND status = 'active'",
            [$tenantId],
            0
        );

        return ['employees' => $employees, 'companies' => $companies];
    }

    /**
     * Simpan pilihan tenant pada sesi (dipakai setelah login).
     */
    public static function rememberTenant(int $tenantId): void
    {
        Session::set('mo.tenant_id', $tenantId);
    }
}
