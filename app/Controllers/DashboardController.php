<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database\Database;
use App\Core\TenantContext;
use App\Core\View;
use App\Services\AuditService;

/**
 * Beranda: ringkasan angka nyata dari basis data milik tenant aktif.
 */
final class DashboardController
{
    public function index(): string
    {
        $tenantId = TenantContext::requireTenant();
        $companyId = TenantContext::companyId();
        $company = TenantContext::company();

        return View::render('pages/dashboard', [
            'title' => t('dashboard.title'),
            'company' => $company,
            'stats' => self::stats($tenantId, $companyId),
            'birthdays' => self::upcomingBirthdays($tenantId, $companyId),
            'activities' => AuditService::forTenant($tenantId, 8),
        ], 'layouts/app');
    }

    /**
     * @return list<array<string, string>>
     */
    private static function stats(int $tenantId, ?int $companyId): array
    {
        if ($companyId === null) {
            return [
                ['label' => 'dashboard.stat.no_company', 'value' => 0, 'hint' => 'dashboard.subtitle'],
            ];
        }

        $total = (int) Database::scalar(
            'SELECT COUNT(*) FROM employees WHERE tenant_id = ? AND primary_company_id = ? AND deleted_at IS NULL AND employment_status = \'active\'',
            [$tenantId, $companyId]
        );

        $since = gmdate('Y-m-d', time() - (30 * 86400));
        $newHires = (int) Database::scalar(
            'SELECT COUNT(*) FROM employees WHERE tenant_id = ? AND primary_company_id = ? AND deleted_at IS NULL'
            . ' AND hire_date >= ?',
            [$tenantId, $companyId, $since]
        );

        $probation = (int) Database::scalar(
            'SELECT COUNT(*) FROM employees WHERE tenant_id = ? AND primary_company_id = ? AND deleted_at IS NULL'
            . ' AND employment_type = \'probation\' AND employment_status = \'active\'',
            [$tenantId, $companyId]
        );

        $pendingOnboarding = (int) Database::scalar(
            'SELECT COUNT(*) FROM onboarding_task_assignments WHERE tenant_id = ? AND status = \'pending\'',
            [$tenantId]
        );

        $today = gmdate('Y-m-d');
        $announcements = (int) Database::scalar(
            'SELECT COUNT(*) FROM announcements WHERE tenant_id = ? AND status = \'published\' AND (expires_at IS NULL OR expires_at >= ?)',
            [$tenantId, $today]
        );

        return [
            ['label' => 'dashboard.stat.employees', 'value' => $total, 'hint' => 'dashboard.stat.employees_hint'],
            ['label' => 'dashboard.stat.new_hires', 'value' => $newHires, 'hint' => 'dashboard.stat.new_hires_hint'],
            ['label' => 'dashboard.stat.probation', 'value' => $probation, 'hint' => 'dashboard.stat.probation_hint'],
            ['label' => 'dashboard.stat.onboarding', 'value' => $pendingOnboarding, 'hint' => 'dashboard.stat.onboarding_hint'],
            ['label' => 'dashboard.stat.announcements', 'value' => $announcements, 'hint' => 'dashboard.stat.announcements_hint'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function upcomingBirthdays(int $tenantId, ?int $companyId): array
    {
        if ($companyId === null) {
            return [];
        }

        // Jendela 21 hari dihitung di PHP, lalu disaring di basis data dengan
        // SUBSTR(birth_date, 6, 2) yang tersedia di MySQL maupun SQLite.
        $months = [];
        for ($offset = 0; $offset <= 21; $offset++) {
            $stamp = strtotime('+' . $offset . ' day', (int) gmmktime(0, 0, 0));
            $months[gmdate('m', $stamp)] = true;
        }
        $placeholders = implode(',', array_fill(0, count($months), '?'));
        $params = array_merge([$tenantId, $companyId], array_keys($months));

        $rows = Database::select(
            'SELECT id, full_name, birth_date FROM employees'
            . ' WHERE tenant_id = ? AND primary_company_id = ? AND deleted_at IS NULL'
            . ' AND birth_date IS NOT NULL AND employment_status = \'active\''
            . ' AND SUBSTR(birth_date, 6, 2) IN (' . $placeholders . ')',
            $params
        ) ?? [];

        $window = [];
        for ($offset = 0; $offset <= 21; $offset++) {
            $window[gmdate('m-d', strtotime('+' . $offset . ' day', (int) gmmktime(0, 0, 0)))] = $offset;
        }

        $result = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $key = substr((string) $row['birth_date'], 5, 5);

            if (!isset($window[$key])) {
                continue;
            }

            $result[] = [
                'offset' => (int) $window[$key],
                'name' => (string) $row['full_name'],
                'date' => gmdate('d M', (int) gmmktime(0, 0, 0, (int) substr((string) $row['birth_date'], 5, 2), (int) substr((string) $row['birth_date'], 8, 2))),
            ];
        }

        usort($result, static fn (array $a, array $b): int => $a['offset'] <=> $b['offset']);

        return array_slice($result, 0, 6);
    }
}
