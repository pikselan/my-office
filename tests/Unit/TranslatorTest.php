<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Translator;
use Tests\Support\TestCase;

final class TranslatorTest extends TestCase
{
    public function testBahasaDefaultAdalahIndonesia(): void
    {
        $this->assertSame('id', Config::string('app.locale.default'));
        $this->assertContains('id', Translator::available());
        $this->assertContains('en', Translator::available());
    }

    public function testKunciTerjemahanTersediaPadaDuaBahasa(): void
    {
        $keys = [
            'app.name', 'nav.dashboard', 'nav.employees', 'action.save',
            'auth.login', 'validation.required', 'message.saved', 'status.active',
        ];

        foreach ($keys as $key) {
            $this->assertTrue(Translator::has($key, 'id'), 'Kunci id belum ada: ' . $key);
            $this->assertTrue(Translator::has($key, 'en'), 'Kunci en belum ada: ' . $key);
        }
    }

    public function testNilaiBerbedaAntarBahasa(): void
    {
        $indonesian = Translator::get('nav.employees', [], 'id');
        $english = Translator::get('nav.employees', [], 'en');

        $this->assertNotEquals($indonesian, $english);
        $this->assertSame('Karyawan', $indonesian);
        $this->assertSame('Employees', $english);
    }

    public function testPenggantianPlaceholder(): void
    {
        $message = Translator::get('validation.min.string', ['attribute' => 'Nama', 'min' => 3], 'id');
        $this->assertStringContainsString('Nama', $message);
        $this->assertStringContainsString('3', $message);
        $this->assertNotContains(':attribute', $message);
    }

    public function testKunciYangTidakAdaDikembalikanApaAdanya(): void
    {
        Translator::resetMissing();
        $value = Translator::get('key.yang.belum.ada', [], 'id');

        $this->assertSame('key.yang.belum.ada', $value);
        $this->assertArrayHasKey('key.yang.belum.ada', Translator::missing());

        Translator::resetMissing();
        $this->assertEmpty(Translator::missing());
    }

    public function testLocaleYangTidakDikenalDitolak(): void
    {
        Translator::setLocale('id');
        Translator::setLocale('xx');
        $this->assertSame('id', Translator::locale());

        Translator::setLocale('en');
        $this->assertSame('en', Translator::locale());
        Translator::setLocale('id');
    }
}
