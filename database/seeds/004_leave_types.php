<?php

declare(strict_types=1);

/**
 * Jenis cuti bawaan sistem (tenant_id NULL).
 *
 * Tenant dapat membuat jenis cuti sendiri; jenis bawaan dipakai sebagai
 * acuan awal saat perusahaan dibuat.
 */

use App\Core\Database\SeedSupport;

return static function (PDO $pdo): void {
    $types = [
        ['annual', 'Cuti Tahunan', 12.0, 1, 0, 1],
        ['sick', 'Cuti Sakit', 12.0, 1, 1, 2],
        ['unpaid', 'Cuti Tanpa Gaji', 0.0, 0, 0, 5],
        ['maternity', 'Cuti Melahirkan', 90.0, 1, 1, 3],
        ['paternity', 'Cuti Ayah', 3.0, 1, 0, 4],
        ['emergency', 'Cuti Mendadak', 3.0, 1, 0, 6],
        ['marriage', 'Cuti Menikah', 3.0, 1, 0, 7],
    ];

    foreach ($types as [$code, $name, $quota, $isPaid, $requiresAttachment, $sortOrder]) {
        SeedSupport::insertIfMissing($pdo, 'leave_types', ['tenant_id' => null, 'company_id' => null, 'code' => $code], [
            'name' => $name,
            'default_quota_days' => $quota,
            'is_paid' => $isPaid,
            'requires_attachment' => $requiresAttachment,
            'is_active' => 1,
            'sort_order' => $sortOrder,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
};
