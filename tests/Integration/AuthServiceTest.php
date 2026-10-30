<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\Database\Database;
use App\Core\Mailer;
use App\Services\AuthService;
use Tests\Support\TestCase;

final class AuthServiceTest extends TestCase
{
    private string $mailLog;

    public function testStrukturAwal(): void
    {
        // Dipakai bersama test lain; dijalankan pertama agar log bersih.
        $this->mailLog = Config::basePath('storage/logs/mail.log');
        $this->assertNotEmpty($this->mailLog);
    }

    public function testRegistrasiOwnerMembuatTenantPerusahaanDanLangganan(): void
    {
        $email = 'owner' . bin2hex(random_bytes(4)) . '@contoh.test';

        $result = AuthService::registerOwner([
            'owner_name'   => 'Sari Wulandari',
            'email'        => $email,
            'password'     => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
            'tenant_name'  => 'PT Uji Coba Indonesia',
            'company_name' => 'PT Uji Coba Indonesia',
            'plan_code'    => 'starter',
        ]);

        $this->assertTrue($result['ok'], 'Registrasi gagal: ' . json_encode($result['errors'] ?? []));
        $this->assertArrayHasKey('data', $result);

        $data = $result['data'];
        $this->assertGreaterThan(0, $data['tenant_id']);
        $this->assertGreaterThan(0, $data['company_id']);

        $tenant = Database::selectOne('SELECT * FROM tenants WHERE id = ?', [$data['tenant_id']]);
        $this->assertNotNull($tenant);
        $this->assertSame('pt-uji-coba-indonesia', $tenant['slug']);
        $this->assertSame('active', $tenant['status']);

        $company = Database::selectOne('SELECT * FROM companies WHERE id = ?', [$data['company_id']]);
        $this->assertNotNull($company);
        $this->assertSame($data['tenant_id'], (int) $company['tenant_id']);

        $subscription = Database::selectOne('SELECT * FROM subscriptions WHERE id = ?', [$data['subscription_id']]);
        $this->assertNotNull($subscription);
        $this->assertSame('trialing', $subscription['status']);
        $this->assertNotNull($subscription['trial_ends_at']);

        // Owner memperoleh role tenant.owner pada tenant tersebut.
        $roleCount = (int) Database::scalar(
            'SELECT COUNT(*) FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id'
            . ' WHERE ur.user_id = ? AND ur.tenant_id = ? AND r.code = ?',
            [$data['user_id'], $data['tenant_id'], 'tenant.owner'],
            0
        );
        $this->assertSame(1, $roleCount);

        // Data awal perusahaan tersedia.
        $this->assertGreaterThan(0, (int) Database::scalar(
            'SELECT COUNT(*) FROM leave_policies WHERE company_id = ?',
            [$data['company_id']], 0
        ));
        $this->assertGreaterThan(0, (int) Database::scalar(
            'SELECT COUNT(*) FROM work_schedules WHERE company_id = ?',
            [$data['company_id']], 0
        ));
        $this->assertGreaterThan(0, (int) Database::scalar(
            'SELECT COUNT(*) FROM company_settings WHERE company_id = ?',
            [$data['company_id']], 0
        ));
    }

    public function testRegistrasiMenolakEmailGanda(): void
    {
        $email = 'ganda' . bin2hex(random_bytes(4)) . '@contoh.test';

        $first = AuthService::registerOwner([
            'owner_name' => 'Pertama',
            'email' => $email,
            'password' => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
            'tenant_name' => 'Tenant Pertama',
            'company_name' => 'Perusahaan Pertama',
        ]);
        $this->assertTrue($first['ok']);

        $second = AuthService::registerOwner([
            'owner_name' => 'Kedua',
            'email' => $email,
            'password' => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
            'tenant_name' => 'Tenant Kedua',
            'company_name' => 'Perusahaan Kedua',
        ]);

        $this->assertFalse($second['ok']);
        $this->assertArrayHasKey('email', $second['errors']);

        // Permintaan ganda tidak membuat tenant kedua.
        $this->assertSame(1, (int) Database::scalar('SELECT COUNT(*) FROM tenants WHERE slug = ?', ['tenant-pertama'], 0));
    }

    public function testRegistrasiMenolakPasswordLemah(): void
    {
        $result = AuthService::registerOwner([
            'owner_name' => 'Uji',
            'email' => 'lemah' . bin2hex(random_bytes(4)) . '@contoh.test',
            'password' => '123',
            'password_confirmation' => '123',
            'tenant_name' => 'Tenant Lemah',
            'company_name' => 'Perusahaan Lemah',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('password', $result['errors']);
    }

    public function testLoginBerhasilDanGagal(): void
    {
        $email = 'login' . bin2hex(random_bytes(4)) . '@contoh.test';

        AuthService::registerOwner([
            'owner_name' => 'Budi Santoso',
            'email' => $email,
            'password' => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
            'tenant_name' => 'Tenant Login',
            'company_name' => 'Perusahaan Login',
        ]);

        $ip = '10.0.0.' . random_int(1, 250);

        $success = AuthService::attempt($email, 'Rahasia123!', $ip, 'PHPUnit');
        $this->assertTrue($success['ok']);
        $this->assertSame($email, $success['user']['email']);

        $failure = AuthService::attempt($email, 'salah-password', $ip, 'PHPUnit');
        $this->assertFalse($failure['ok']);
        $this->assertSame('invalid', $failure['error']);

        $unknown = AuthService::attempt('tidak-ada' . bin2hex(random_bytes(3)) . '@contoh.test', 'Rahasia123!', '10.1.1.1', 'PHPUnit');
        $this->assertFalse($unknown['ok']);
        $this->assertSame('invalid', $unknown['error']);
    }

    public function testPercobaanLoginBerulangMengunciAkun(): void
    {
        $email = 'lock' . bin2hex(random_bytes(4)) . '@contoh.test';

        AuthService::registerOwner([
            'owner_name' => 'Uji Kunci',
            'email' => $email,
            'password' => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
            'tenant_name' => 'Tenant Kunci',
            'company_name' => 'Perusahaan Kunci',
        ]);

        $ip = '10.9.9.' . random_int(1, 250);
        $max = Config::int('security.login.max_attempts', 5);

        for ($i = 0; $i < $max; $i++) {
            AuthService::attempt($email, 'salah-' . $i, $ip, 'PHPUnit');
        }

        // Percobaan berikutnya ditolak oleh throttle meski kata sandi benar.
        $blocked = AuthService::attempt($email, 'Rahasia123!', $ip, 'PHPUnit');
        $this->assertFalse($blocked['ok']);
        $this->assertSame('locked', $blocked['error']);

        $user = Database::selectOne('SELECT locked_until, failed_login_count FROM users WHERE email = ?', [$email]);
        $this->assertNotNull($user['locked_until']);
        $this->assertGreaterThan(0, (int) $user['failed_login_count']);
    }

    public function testAlurResetKataSandiBerjalan(): void
    {
        $email = 'reset' . bin2hex(random_bytes(4)) . '@contoh.test';

        AuthService::registerOwner([
            'owner_name' => 'Uji Reset',
            'email' => $email,
            'password' => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
            'tenant_name' => 'Tenant Reset',
            'company_name' => 'Perusahaan Reset',
        ]);

        $logPath = Config::basePath('storage/logs/mail.log');
        $before = is_file($logPath) ? (int) filesize($logPath) : 0;

        $this->assertTrue(AuthService::sendPasswordReset($email, '10.2.2.2'));

        $contents = is_file($logPath) ? (string) file_get_contents($logPath) : '';
        $tail = substr($contents, $before);
        $this->assertStringContainsString('reset-password', $tail);

        preg_match('/reset-password\/([a-f0-9]{64})/', $tail, $matches);
        $this->assertNotEmpty($matches[1] ?? '', 'Token reset tidak ditemukan pada log surel');

        $token = $matches[1];
        $this->assertTrue(AuthService::resetPassword($token, 'KataSandiBaru123!'));

        // Token sekali pakai.
        $this->assertFalse(AuthService::resetPassword($token, 'KataSandiLain123!'));

        $success = AuthService::attempt($email, 'KataSandiBaru123!', '10.3.3.3', 'PHPUnit');
        $this->assertTrue($success['ok']);
    }

    public function testResetKataSandiTidakMembocorkanKeberadaanEmail(): void
    {
        $this->assertTrue(AuthService::sendPasswordReset('tidak-terdaftar' . bin2hex(random_bytes(4)) . '@contoh.test', '10.4.4.4'));

        $recorded = (int) Database::scalar(
            'SELECT COUNT(*) FROM password_reset_tokens WHERE ip_address = ?',
            ['10.4.4.4'], 0
        );
        $this->assertSame(0, $recorded, 'Token dibuat untuk email yang tidak terdaftar');
    }

    public function testAlurVerifikasiEmailBerjalan(): void
    {
        $email = 'verify' . bin2hex(random_bytes(4)) . '@contoh.test';

        $result = AuthService::registerOwner([
            'owner_name' => 'Uji Verifikasi',
            'email' => $email,
            'password' => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
            'tenant_name' => 'Tenant Verifikasi',
            'company_name' => 'Perusahaan Verifikasi',
        ]);

        $userId = $result['data']['user_id'];
        $this->assertNull(Database::selectOne('SELECT email_verified_at FROM users WHERE id = ?', [$userId])['email_verified_at'] ?? null);

        $logPath = Config::basePath('storage/logs/mail.log');
        $before = is_file($logPath) ? (int) filesize($logPath) : 0;

        $this->assertTrue(AuthService::sendVerificationLink($userId));

        $tail = substr((string) file_get_contents($logPath), $before);
        preg_match('/verify-email\/([a-f0-9]{64})/', $tail, $matches);
        $this->assertNotEmpty($matches[1] ?? '');

        $this->assertFalse(AuthService::verifyEmail('token-palsu'));
        $this->assertTrue(AuthService::verifyEmail($matches[1]));
        $this->assertFalse(AuthService::verifyEmail($matches[1]), 'Token seharusnya sekali pakai');

        $user = Database::selectOne('SELECT email_verified_at FROM users WHERE id = ?', [$userId]);
        $this->assertNotNull($user['email_verified_at']);
    }

    public function testUbahKataSandiMemerlukanKataSandiLama(): void
    {
        $email = 'ganti' . bin2hex(random_bytes(4)) . '@contoh.test';

        $result = AuthService::registerOwner([
            'owner_name' => 'Uji Ganti',
            'email' => $email,
            'password' => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
            'tenant_name' => 'Tenant Ganti',
            'company_name' => 'Perusahaan Ganti',
        ]);

        $userId = $result['data']['user_id'];

        $this->assertFalse(AuthService::changePassword($userId, 'salah', 'KataSandiBaru123!'));
        $this->assertTrue(AuthService::changePassword($userId, 'Rahasia123!', 'KataSandiBaru123!'));
        $this->assertFalse(AuthService::changePassword($userId, 'Rahasia123!', 'KataSandiBaru123!'));
    }
}
