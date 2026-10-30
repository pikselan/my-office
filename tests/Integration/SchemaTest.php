<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Database;
use App\Core\Database\Migrator;
use App\Core\Database\SqliteDialect;
use Tests\Support\TestCase;

final class SchemaTest extends TestCase
{
    private const EXPECTED_TABLES = [
        'users', 'tenants', 'plans', 'plan_features', 'subscriptions', 'subscription_events',
        'payments', 'platform_settings', 'tenant_memberships', 'roles', 'permissions',
        'role_permissions', 'user_roles', 'user_company_access', 'user_sessions',
        'password_reset_tokens', 'email_verification_tokens', 'invitations', 'login_attempts',
        'support_access_grants', 'companies', 'company_settings', 'tenant_settings',
        'work_locations', 'employees', 'employee_details', 'employee_user_links',
        'org_units', 'departments', 'positions', 'employee_assignments',
        'employee_documents', 'employee_notes', 'onboarding_templates', 'onboarding_tasks',
        'onboarding_processes', 'onboarding_task_assignments', 'announcements',
        'announcement_targets', 'work_schedules', 'schedule_assignments', 'holidays',
        'attendance_records', 'attendance_corrections', 'leave_types', 'leave_policies',
        'leave_balances', 'leave_requests', 'leave_approvals', 'job_postings', 'candidates',
        'candidate_stage_history', 'recruitment_interviews', 'payroll_periods',
        'payroll_components', 'payroll_records', 'payroll_record_items', 'reimbursements',
        'cash_advances', 'employee_loans', 'loan_installments', 'audit_logs',
        'notifications', 'system_jobs', 'schema_migrations',
    ];

    public function testSeluruhTabelTerbentukDariMigrasi(): void
    {
        $tables = $this->tables();

        foreach (self::EXPECTED_TABLES as $table) {
            $this->assertContains($table, $tables, 'Tabel belum terbentuk: ' . $table);
        }
    }

    public function testTidakAdaPelanggaranForeignKey(): void
    {
        $violations = Database::pdo()->query('PRAGMA foreign_key_check')->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertCount(0, $violations, 'Skema memiliki foreign key yang tidak valid');
    }

    public function testSetiapTabelMemilikiPrimaryKey(): void
    {
        foreach ($this->tables() as $table) {
            $columns = Database::select('PRAGMA table_info("' . $table . '")');
            $hasPrimaryKey = false;

            foreach ($columns as $column) {
                if ((int) ($column['pk'] ?? 0) > 0) {
                    $hasPrimaryKey = true;
                    break;
                }
            }

            $this->assertTrue($hasPrimaryKey, 'Tabel tanpa primary key: ' . $table);
        }
    }

    public function testTabelTenantScopedMemilikiKolomTenantId(): void
    {
        $scoped = ['companies', 'employees', 'org_units', 'departments', 'positions',
            'employee_assignments', 'leave_requests', 'attendance_records', 'announcements'];

        foreach ($scoped as $table) {
            $columns = $this->columns($table);
            $this->assertContains('tenant_id', $columns, 'Tabel tanpa tenant_id: ' . $table);
        }
    }

    public function testMigrasiTidakDijalankanUlang(): void
    {
        $migrator = new Migrator(dirname(__DIR__, 2) . '/database/migrations', Database::pdo());

        $this->assertSame([], $migrator->pending(), 'Masih ada migrasi tertunda');
        $this->assertCount(count($migrator->files()), $migrator->applied());

        $result = $migrator->run();
        $this->assertSame([], $result['applied'], 'Migrasi yang sudah tercatat dijalankan ulang');
    }

    public function testTerjemahanDialekMenghasilkanSqlYangDapatDijalankan(): void
    {
        // Menjalankan seluruh DDL yang sudah diterjemahkan pada koneksi baru
        // membuktikan hasil terjemahan valid bagi SQLite.
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/006_organization.sql');
        $this->assertNotEmpty($sql);

        $translated = SqliteDialect::translate((string) $sql);

        $this->assertStringContainsString('CREATE TABLE "org_units"', $translated);
        $this->assertStringContainsString('AUTOINCREMENT', $translated);
        $this->assertStringContainsString('CREATE UNIQUE INDEX', $translated);
        $this->assertNotContains('ENGINE=InnoDB', $translated);
        $this->assertNotContains('UNSIGNED', $translated);

        $memory = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $memory->exec('PRAGMA foreign_keys = ON');

        foreach (['005_employee_core.sql', '004_company.sql', '003_tenancy.sql', '002_platform.sql', '001_identity.sql'] as $file) {
            $memory->exec(SqliteDialect::translate((string) file_get_contents(dirname(__DIR__, 2) . '/database/migrations/' . $file)));
        }

        $memory->exec($translated);

        $count = (int) $memory->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table'")->fetchColumn();
        $this->assertGreaterThan(5, $count);
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        return Database::column("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
    }

    /**
     * @return list<string>
     */
    private function columns(string $table): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['name'],
            Database::select('PRAGMA table_info("' . $table . '")')
        );
    }
}
