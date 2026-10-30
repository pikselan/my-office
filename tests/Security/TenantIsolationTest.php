<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Core\Authorization;
use App\Core\Database\Database;
use App\Core\TenantContext;
use Tests\Support\TestCase;
use Tests\Support\TestFactory;

/**
 * Pengujian isolasi antar-tenant.
 *
 * Skenario mengikuti BAGIAN 7.3 dokumen master.
 */
final class TenantIsolationTest extends TestCase
{
    public function testPenggunaTidakDapatMemilihTenantYangBukanAnggotanya(): void
    {
        $tenantA = TestFactory::tenant();
        $tenantB = TestFactory::tenant();

        TestFactory::actingAs($tenantA['user_id'], $tenantA['tenant_id']);
        $this->assertSame($tenantA['tenant_id'], TenantContext::id());

        $this->assertFalse(TenantContext::setTenant($tenantB['tenant_id'], $tenantA['user_id']));
        $this->assertSame($tenantA['tenant_id'], TenantContext::id());

        TestFactory::reset();
    }

    public function testPermissionTidakMenyebarKeTenantLain(): void
    {
        $tenantA = TestFactory::tenant();
        $tenantB = TestFactory::tenant();

        TestFactory::actingAs($tenantA['user_id'], $tenantA['tenant_id']);

        $permissionsA = Authorization::permissions($tenantA['tenant_id'], $tenantA['user_id']);
        $permissionsB = Authorization::permissions($tenantB['tenant_id'], $tenantA['user_id']);

        $this->assertNotEmpty($permissionsA, 'Owner tenant A seharusnya memiliki permission');
        $this->assertContains('company.view', $permissionsA);
        $this->assertSame([], $permissionsB, 'Permission tidak boleh berlaku pada tenant lain');

        TestFactory::reset();
    }

    public function testPerusahaanTidakDapatDiaksesLintasTenant(): void
    {
        $tenantA = TestFactory::tenant();
        $tenantB = TestFactory::tenant();

        TestFactory::actingAs($tenantA['user_id'], $tenantA['tenant_id']);

        $companies = TenantContext::companies();
        $this->assertNotEmpty($companies);

        $foreignCompanyId = (int) Database::scalar(
            'SELECT id FROM companies WHERE tenant_id = ? LIMIT 1',
            [$tenantB['tenant_id']]
        );

        $this->assertGreaterThan(0, $foreignCompanyId);
        $this->assertFalse(TenantContext::setCompany($foreignCompanyId));
        $this->assertSame((int) $companies[0]['id'], TenantContext::companyId());

        TestFactory::reset();
    }

    public function testRolePlatformTidakMemberiAksesPadaTenant(): void
    {
        $tenant = TestFactory::tenant();

        $supportId = TestFactory::user($tenant['tenant_id'], 'tenant.employee');

        // Jadikan pengguna ini pemegang role platform (global).
        $platformRoleId = (int) Database::scalar(
            'SELECT id FROM roles WHERE tenant_id IS NULL AND code = ?',
            ['platform.support']
        );
        $this->assertGreaterThan(0, $platformRoleId);

        Database::insertRow('user_roles', [
            'tenant_id'  => null,
            'user_id'    => $supportId,
            'role_id'    => $platformRoleId,
            'company_id' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        Authorization::flush($supportId);

        TestFactory::actingAs($supportId, $tenant['tenant_id']);

        $this->assertTrue(Authorization::isPlatformAdmin(), 'Role platform seharusnya terdeteksi');

        // Meski memegang role platform, izin pada tenant tetap mengikuti role tenant.
        $permissions = Authorization::permissions($tenant['tenant_id'], $supportId);
        $this->assertNotContains('platform.tenant.manage', $permissions);
        $this->assertContains('leave.request', $permissions);

        TestFactory::reset();
    }

    public function testPembatasanPerusahaanMengurangiDaftarPerusahaan(): void
    {
        $tenant = TestFactory::tenant();
        $secondCompany = TestFactory::company($tenant['tenant_id'], 'Perusahaan Kedua');

        $managerId = TestFactory::user($tenant['tenant_id'], 'tenant.manager');
        TestFactory::restrictToCompany($tenant['tenant_id'], $managerId, $secondCompany);

        TestFactory::actingAs($managerId, $tenant['tenant_id']);

        $companies = TenantContext::companies();
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $companies);

        $this->assertCount(1, $ids);
        $this->assertContains($secondCompany, $ids);
        $this->assertNotContains($tenant['company_id'], $ids);

        TestFactory::reset();
    }

    public function testManipulasiTenantIdPadaSesiDitolak(): void
    {
        $tenant = TestFactory::tenant();
        $outsider = TestFactory::user($tenant['tenant_id'], 'tenant.employee');

        // Pengguna sengaja menempatkan tenant_id tenant lain pada sesi.
        $other = TestFactory::tenant();

        TestFactory::actingAs($outsider);
        \App\Core\Session::set('mo.tenant_id', $other['tenant_id']);

        // Konteks harus ditolak saat divalidasi.
        $this->assertFalse(TenantContext::setTenant($other['tenant_id'], $outsider));

        TestFactory::reset();
    }

    public function testKaryawanMemilikiIzinTerbatas(): void
    {
        $tenant = TestFactory::tenant();
        $employeeId = TestFactory::user($tenant['tenant_id'], 'tenant.employee');

        TestFactory::actingAs($employeeId, $tenant['tenant_id']);

        $permissions = Authorization::permissions();

        $this->assertContains('leave.request', $permissions);
        $this->assertContains('attendance.self.view', $permissions);
        $this->assertNotContains('employee.create', $permissions);
        $this->assertNotContains('role.manage', $permissions);
        $this->assertNotContains('payroll.view', $permissions);

        TestFactory::reset();
    }

    public function testOwnerMemilikiHakPenuhPadaTenant(): void
    {
        $tenant = TestFactory::tenant();

        TestFactory::actingAs($tenant['user_id'], $tenant['tenant_id']);

        $permissions = Authorization::permissions();

        foreach (['tenant.update', 'company.create', 'employee.create', 'role.manage',
            'leave.approve', 'report.export', 'audit.view'] as $permission) {
            $this->assertContains($permission, $permissions, 'Owner kekurangan izin: ' . $permission);
        }

        foreach (['platform.tenant.view', 'platform.plan.manage'] as $permission) {
            $this->assertNotContains($permission, $permissions, 'Owner tidak boleh memiliki izin platform');
        }

        TestFactory::reset();
    }
}
