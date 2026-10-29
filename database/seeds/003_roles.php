<?php

declare(strict_types=1);

/**
 * Role sistem (tenant_id NULL) beserta pemetaan permission-nya.
 *
 * Matriks mengikuti docs/ACCESS_CONTROL.md.
 */

use App\Core\Database\SeedSupport;

return static function (PDO $pdo): void {
    $definitions = [
        'platform.super_admin' => [
            'name' => 'Platform Super Admin',
            'description' => 'Operator MO: mengelola tenant, paket, dan langganan.',
            'is_system' => 1,
            'sort_order' => 1,
            'permissions' => [
                'platform.tenant.view', 'platform.tenant.manage', 'platform.plan.manage',
                'platform.subscription.manage', 'platform.settings.manage', 'platform.audit.view',
                'platform.support.grant',
            ],
        ],
        'platform.support' => [
            'name' => 'Platform Support',
            'description' => 'Dukungan teknis dengan akses terbatas dan tercatat.',
            'is_system' => 1,
            'sort_order' => 2,
            'permissions' => [
                'platform.tenant.view', 'platform.audit.view',
            ],
        ],
        'tenant.owner' => [
            'name' => 'Owner',
            'description' => 'Pemilik akun SaaS; hak penuh pada tenant.',
            'is_system' => 1,
            'sort_order' => 10,
            'permissions' => 'all_tenant',
        ],
        'tenant.company_admin' => [
            'name' => 'Company Admin',
            'description' => 'Mengelola perusahaan yang diizinkan.',
            'is_system' => 1,
            'sort_order' => 11,
            'permissions' => [
                'tenant.view',
                'company.view', 'company.create', 'company.update', 'company.archive', 'company.settings.manage',
                'organization.view', 'organization.create', 'organization.update', 'organization.archive',
                'employee.view', 'employee.create', 'employee.update', 'employee.archive',
                'employee.import', 'employee.export', 'employee.sensitive.view', 'employee.note.manage',
                'employee.document.view', 'employee.document.upload', 'employee.document.delete',
                'user.manage', 'user.invite', 'role.manage',
                'onboarding.view', 'onboarding.manage',
                'announcement.view', 'announcement.create', 'announcement.update', 'announcement.publish', 'announcement.delete',
                'attendance.view', 'attendance.self.view', 'attendance.checkin', 'attendance.correct', 'attendance.manage',
                'schedule.manage', 'holiday.manage',
                'leave.view', 'leave.self.view', 'leave.request', 'leave.approve', 'leave.manage', 'leave.balance.manage',
                'recruitment.view', 'recruitment.manage', 'candidate.convert',
                'payroll.self.view',
                'finance.reimbursement.manage', 'finance.cashadvance.manage', 'finance.loan.manage',
                'report.view', 'report.export',
                'audit.view', 'notification.manage',
            ],
        ],
        'tenant.hr_admin' => [
            'name' => 'HR Admin',
            'description' => 'Mengelola karyawan, organisasi, dan proses HR.',
            'is_system' => 1,
            'sort_order' => 12,
            'permissions' => [
                'tenant.view',
                'company.view',
                'organization.view', 'organization.create', 'organization.update', 'organization.archive',
                'employee.view', 'employee.create', 'employee.update', 'employee.archive',
                'employee.import', 'employee.export', 'employee.sensitive.view', 'employee.note.manage',
                'employee.document.view', 'employee.document.upload', 'employee.document.delete',
                'onboarding.view', 'onboarding.manage',
                'announcement.view', 'announcement.create', 'announcement.update', 'announcement.publish', 'announcement.delete',
                'attendance.view', 'attendance.self.view', 'attendance.checkin', 'attendance.correct', 'attendance.manage',
                'schedule.manage', 'holiday.manage',
                'leave.view', 'leave.self.view', 'leave.request', 'leave.approve', 'leave.manage', 'leave.balance.manage',
                'recruitment.view', 'recruitment.manage', 'candidate.convert',
                'payroll.self.view',
                'report.view', 'report.export',
            ],
        ],
        'tenant.manager' => [
            'name' => 'Manager / Supervisor',
            'description' => 'Melihat timnya dan menyetujui permintaan anggota tim.',
            'is_system' => 1,
            'sort_order' => 13,
            'permissions' => [
                'tenant.view',
                'company.view',
                'organization.view',
                'employee.view', 'employee.note.manage',
                'onboarding.view',
                'announcement.view',
                'attendance.self.view', 'attendance.checkin', 'attendance.correct',
                'leave.self.view', 'leave.request', 'leave.approve',
                'recruitment.view',
                'payroll.self.view',
                'report.view',
            ],
        ],
        'tenant.finance' => [
            'name' => 'Finance / Payroll',
            'description' => 'Mengelola payroll dan transaksi keuangan karyawan.',
            'is_system' => 1,
            'sort_order' => 14,
            'permissions' => [
                'tenant.view',
                'company.view',
                'organization.view',
                'employee.view', 'employee.sensitive.view',
                'announcement.view',
                'attendance.self.view', 'attendance.checkin',
                'leave.self.view', 'leave.request',
                'payroll.view', 'payroll.self.view', 'payroll.process', 'payroll.approve', 'payroll.component.manage',
                'finance.reimbursement.manage', 'finance.cashadvance.manage', 'finance.loan.manage',
                'report.view', 'report.export',
            ],
        ],
        'tenant.employee' => [
            'name' => 'Employee',
            'description' => 'Karyawan: profil sendiri, cuti, jadwal, dan slip gaji sendiri.',
            'is_system' => 1,
            'sort_order' => 15,
            'permissions' => [
                'tenant.view',
                'company.view',
                'organization.view',
                'employee.document.view',
                'announcement.view',
                'attendance.self.view', 'attendance.checkin', 'attendance.correct',
                'leave.self.view', 'leave.request',
                'payroll.self.view',
                'notification.manage',
            ],
        ],
    ];

    // Seluruh permission non-platform diberikan kepada owner.
    $allTenantPermissions = $pdo
        ->query("SELECT code FROM permissions WHERE code NOT LIKE 'platform.%' ORDER BY code")
        ->fetchAll(PDO::FETCH_COLUMN);

    foreach ($definitions as $code => $definition) {
        $permissions = $definition['permissions'];
        unset($definition['permissions']);

        $roleId = SeedSupport::insertIfMissing($pdo, 'roles', ['tenant_id' => null, 'code' => $code], $definition + [
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $codes = $permissions === 'all_tenant' ? $allTenantPermissions : $permissions;

        foreach ($codes as $permissionCode) {
            $permissionId = SeedSupport::findId($pdo, 'permissions', ['code' => $permissionCode]);

            if ($permissionId === null) {
                continue;
            }

            SeedSupport::linkIfMissing($pdo, 'role_permissions', [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }
};
