<?php

declare(strict_types=1);

/**
 * Paket langganan bawaan beserta fiturnya.
 *
 * Nilai harga bersifat konfigurasi awal dan dapat diubah oleh platform admin
 * tanpa mengubah kode.
 */

use App\Core\Database\SeedSupport;

return static function (PDO $pdo): void {
    $plans = [
        [
            'code' => 'starter',
            'name' => 'Starter',
            'description' => 'Untuk usaha kecil yang mulai menata data karyawan.',
            'monthly_price' => 99000,
            'annual_price' => 990000,
            'employee_limit' => 25,
            'company_limit' => 1,
            'storage_limit_mb' => 500,
            'trial_days' => 14,
            'sort_order' => 1,
            'features' => [
                'module.organization' => true,
                'feature.dashboard_basic' => true,
                'feature.visibility_settings' => true,
                'feature.reports_basic' => true,
            ],
        ],
        [
            'code' => 'business',
            'name' => 'Business',
            'description' => 'Untuk perusahaan berkembang dengan proses cuti dan absensi.',
            'monthly_price' => 249000,
            'annual_price' => 2490000,
            'employee_limit' => 100,
            'company_limit' => 3,
            'storage_limit_mb' => 2000,
            'trial_days' => 14,
            'sort_order' => 2,
            'features' => [
                'module.organization' => true,
                'module.leave' => true,
                'module.attendance' => true,
                'module.onboarding' => true,
                'module.announcement' => true,
                'feature.dashboard_basic' => true,
                'feature.visibility_settings' => true,
                'feature.reports_basic' => true,
                'feature.import_export' => true,
                'feature.custom_roles' => true,
            ],
        ],
        [
            'code' => 'growth',
            'name' => 'Growth',
            'description' => 'Untuk grup usaha dengan kebutuhan rekrutmen dan analitik.',
            'monthly_price' => 499000,
            'annual_price' => 4990000,
            'employee_limit' => 300,
            'company_limit' => 10,
            'storage_limit_mb' => 10000,
            'trial_days' => 14,
            'sort_order' => 3,
            'features' => [
                'module.organization' => true,
                'module.leave' => true,
                'module.attendance' => true,
                'module.onboarding' => true,
                'module.announcement' => true,
                'module.recruitment' => true,
                'module.payroll' => true,
                'module.finance' => true,
                'module.reports.advanced' => true,
                'feature.dashboard_basic' => true,
                'feature.visibility_settings' => true,
                'feature.reports_basic' => true,
                'feature.import_export' => true,
                'feature.custom_roles' => true,
                'feature.workflow_advanced' => true,
            ],
        ],
        [
            'code' => 'enterprise',
            'name' => 'Enterprise',
            'description' => 'Harga dan batas disesuaikan dengan penawaran.',
            'monthly_price' => 0,
            'annual_price' => 0,
            'employee_limit' => 0,
            'company_limit' => 0,
            'storage_limit_mb' => 0,
            'trial_days' => 30,
            'sort_order' => 4,
            'is_public' => 0,
            'features' => [
                'module.organization' => true,
                'module.leave' => true,
                'module.attendance' => true,
                'module.onboarding' => true,
                'module.announcement' => true,
                'module.recruitment' => true,
                'module.payroll' => true,
                'module.finance' => true,
                'module.reports.advanced' => true,
                'feature.dashboard_basic' => true,
                'feature.visibility_settings' => true,
                'feature.reports_basic' => true,
                'feature.import_export' => true,
                'feature.custom_roles' => true,
                'feature.workflow_advanced' => true,
                'feature.api_access' => true,
                'feature.custom_integration' => true,
            ],
        ],
    ];

    foreach ($plans as $plan) {
        $features = $plan['features'];
        unset($plan['features']);

        $planId = SeedSupport::insertIfMissing($pdo, 'plans', ['code' => $plan['code']], $plan);

        // Paket yang sudah ada ikut diperbarui agar perubahan harga bawaan
        // tetap konsisten, kecuali batas yang mungkin disesuaikan admin.
        SeedSupport::update($pdo, 'plans', $planId, [
            'name' => $plan['name'],
            'description' => $plan['description'],
            'monthly_price' => $plan['monthly_price'],
            'annual_price' => $plan['annual_price'],
            'sort_order' => $plan['sort_order'],
            'is_public' => $plan['is_public'] ?? 1,
        ]);

        foreach ($features as $featureCode => $enabled) {
            SeedSupport::linkIfMissing($pdo, 'plan_features', [
                'plan_id' => $planId,
                'feature_code' => $featureCode,
                'is_enabled' => $enabled ? 1 : 0,
            ]);
        }
    }
};
