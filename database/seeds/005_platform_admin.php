<?php

declare(strict_types=1);

/**
 * Akun operator platform.
 *
 * Kredensial TIDAK ditulis di sini. Seed membaca:
 *
 *   PLATFORM_ADMIN_EMAIL
 *   PLATFORM_ADMIN_PASSWORD
 *
 * Pada lingkungan development, bila keduanya tidak diisi, akun bawaan
 * dibuat agar sandbox dapat dipakai. Pada production seed ini dilewati
 * bila variabel tersebut tidak diisi.
 */

use App\Core\Config;
use App\Core\Database\SeedSupport;

return static function (PDO $pdo): void {
    $email = strtolower(trim((string) Config::env('PLATFORM_ADMIN_EMAIL', '')));
    $password = (string) Config::env('PLATFORM_ADMIN_PASSWORD', '');
    $isProduction = Config::string('app.env') === 'production';

    if ($email === '' || $password === '') {
        if ($isProduction) {
            return;
        }
        $email = 'admin@mo.test';
        $password = 'DevAdmin123!';
    }

    $roleId = SeedSupport::findId($pdo, 'roles', ['tenant_id' => null, 'code' => 'platform.super_admin']);

    if ($roleId === null) {
        return;
    }

    $userId = SeedSupport::insertIfMissing($pdo, 'users', ['email' => $email], [
        'name' => 'Platform Admin',
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'status' => 'active',
        'is_platform_admin' => 1,
        'email_verified_at' => gmdate('Y-m-d H:i:s'),
        'created_at' => gmdate('Y-m-d H:i:s'),
        'updated_at' => gmdate('Y-m-d H:i:s'),
    ]);

    // Pastikan penanda platform admin tetap benar walau akun sudah ada.
    SeedSupport::update($pdo, 'users', $userId, ['is_platform_admin' => 1]);

    // Role platform tidak terikat tenant: tenant_id bernilai NULL.
    $statement = $pdo->prepare('SELECT COUNT(*) FROM user_roles WHERE user_id = ? AND role_id = ? AND tenant_id IS NULL');
    $statement->execute([$userId, $roleId]);

    if ((int) $statement->fetchColumn() === 0) {
        SeedSupport::insert($pdo, 'user_roles', [
            'tenant_id' => null,
            'user_id' => $userId,
            'role_id' => $roleId,
            'company_id' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
};
