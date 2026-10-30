<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database\Database;
use App\Core\Validator;
use Tests\Support\TestCase;

final class ValidatorTest extends TestCase
{
    public function testAturanRequired(): void
    {
        $errors = Validator::validate(['name' => ''], ['name' => 'required'], ['name' => 'Nama']);

        $this->assertArrayHasKey('name', $errors);
        $this->assertStringContainsString('Nama', $errors['name'][0]);

        $this->assertEmpty(Validator::validate(['name' => 'Budi'], ['name' => 'required']));
    }

    public function testAturanEmail(): void
    {
        $this->assertArrayHasKey('email', Validator::validate(['email' => 'bukan-email'], ['email' => 'required|email']));
        $this->assertEmpty(Validator::validate(['email' => 'budi@contoh.co.id'], ['email' => 'required|email']));
    }

    public function testAturanMinDanMax(): void
    {
        $this->assertArrayHasKey('nama', Validator::validate(['nama' => 'ab'], ['nama' => 'required|min:3']));
        $this->assertArrayHasKey('nama', Validator::validate(['nama' => str_repeat('a', 200)], ['nama' => 'required|max:150']));
        $this->assertEmpty(Validator::validate(['nama' => 'Budi'], ['nama' => 'required|min:3|max:150']));
    }

    public function testAturanConfirmed(): void
    {
        $errors = Validator::validate(
            ['password' => 'rahasia123', 'password_confirmation' => 'beda123'],
            ['password' => 'required|confirmed']
        );

        $this->assertArrayHasKey('password', $errors);
        $this->assertEmpty(Validator::validate(
            ['password' => 'rahasia123', 'password_confirmation' => 'rahasia123'],
            ['password' => 'required|confirmed']
        ));
    }

    public function testAturanInDanNotIn(): void
    {
        $this->assertEmpty(Validator::validate(['status' => 'active'], ['status' => 'in:active,archived']));
        $this->assertArrayHasKey('status', Validator::validate(['status' => 'hapus'], ['status' => 'in:active,archived']));
    }

    public function testAturanUnique(): void
    {
        $email = 'unittest' . bin2hex(random_bytes(4)) . '@contoh.test';

        Database::insertRow('users', [
            'name' => 'Uji',
            'email' => $email,
            'password_hash' => password_hash('rahasia', PASSWORD_DEFAULT),
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->assertArrayHasKey('email', Validator::validate(['email' => $email], ['email' => 'unique:users,email']));

        $free = 'bebas' . bin2hex(random_bytes(4)) . '@contoh.test';
        $this->assertEmpty(Validator::validate(['email' => $free], ['email' => 'unique:users,email']));
    }

    public function testAturanUniqueMengabaikanBarisSendiri(): void
    {
        $email = 'ignore' . bin2hex(random_bytes(4)) . '@contoh.test';

        $id = Database::insertRow('users', [
            'name' => 'Uji Ignore',
            'email' => $email,
            'password_hash' => password_hash('rahasia', PASSWORD_DEFAULT),
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $errors = Validator::validate(['email' => $email], ['email' => 'unique:users,email,id,' . $id]);
        $this->assertEmpty($errors);
    }

    public function testAturanExists(): void
    {
        $this->assertEmpty(Validator::validate(['code' => 'starter'], ['code' => 'exists:plans,code']));
        $this->assertArrayHasKey('field', Validator::validate(['code' => 'tidak-ada'], ['code' => 'exists:plans,code']));
    }

    public function testAturanTanggal(): void
    {
        $this->assertEmpty(Validator::validate(['tanggal' => '2026-01-15'], ['tanggal' => 'required|date']));
        $this->assertArrayHasKey('tanggal', Validator::validate(['tanggal' => '2026-02-30'], ['tanggal' => 'required|date']));
        $this->assertArrayHasKey('tanggal', Validator::validate(['tanggal' => '15/01/2026'], ['tanggal' => 'required|date_format:Y-m-d']));
    }

    public function testAturanMinCharsUntukKataSandi(): void
    {
        // Angka murni harus diukur panjangnya, bukan nilainya.
        $this->assertArrayHasKey('password', Validator::validate(['password' => '123'], ['password' => 'min_chars:8']));
        $this->assertEmpty(Validator::validate(['password' => '12345678'], ['password' => 'min_chars:8']));
        $this->assertArrayHasKey('password', Validator::validate(['password' => str_repeat('a', 201)], ['password' => 'max_chars:200']));
    }

    public function testBidangKosongTidakDivalidasiAturanLain(): void
    {
        $errors = Validator::validate(['email' => ''], ['email' => 'email|min:50']);
        $this->assertEmpty($errors);
    }

    public function testBeberapaAturanPadaSatuBidang(): void
    {
        $errors = Validator::validate(['kode' => 'x!'], ['kode' => 'required|min:3|alpha_dash']);
        $this->assertArrayHasKey('kode', $errors);
        $this->assertTrue(count($errors['kode']) >= 2, 'Dua aturan dilanggar sekaligus');
    }
}
