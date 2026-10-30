<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Paginator;
use Tests\Support\TestCase;

final class ComponentsTest extends TestCase
{
    public function testInputMengEscapeNilai(): void
    {
        $html = mo_input('nama', 'Nama', '<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testInputMenandaiKolomWajib(): void
    {
        $html = mo_input('email', 'Email', '', ['required' => true]);

        $this->assertStringContainsString('required', $html);
        $this->assertStringContainsString('<span>*</span>', $html);
    }

    public function testSelectMenandaiPilihanAktif(): void
    {
        $html = mo_select('status', 'Status', ['active' => 'Aktif', 'inactive' => 'Nonaktif'], 'inactive');

        $this->assertStringContainsString('value="inactive" selected', $html);
    }

    public function testBadgeStatusMenggunakanWarnaYangTepat(): void
    {
        $this->assertStringContainsString('mo-badge--success', mo_status_badge('active'));
        $this->assertStringContainsString('mo-badge--danger', mo_status_badge('rejected'));
        $this->assertStringContainsString('Nonaktif', mo_status_badge('inactive'));
    }

    public function testEmptyStateMenyertakanAksi(): void
    {
        $html = mo_empty_state('Belum ada data', 'Tambahkan data baru.', ['action' => '<button>Tambah</button>']);

        $this->assertStringContainsString('mo-empty', $html);
        $this->assertStringContainsString('<button>Tambah</button>', $html);
    }

    public function testAvatarMenghasilkanInisial(): void
    {
        $this->assertStringContainsString('>BS<', mo_avatar('Budi Santoso'));
        $this->assertStringContainsString('>?<', mo_avatar(''));
    }

    public function testModalMenyertakanTombolTutup(): void
    {
        $html = mo_modal('mo-test', 'Judul', '<p>Isi</p>', '<button>Simpan</button>');

        $this->assertStringContainsString('id="mo-test"', $html);
        $this->assertStringContainsString('data-mo-modal-close', $html);
        $this->assertStringContainsString('<button>Simpan</button>', $html);
    }

    public function testStatCardMenampilkanNilai(): void
    {
        $html = mo_stat_card('Karyawan', 42, 'Terdaftar');

        $this->assertStringContainsString('42', $html);
        $this->assertStringContainsString('Karyawan', $html);
    }

    public function testPaginationMenghasilkanTautan(): void
    {
        $html = mo_pagination(new Paginator(60, 25, 2, '/x'));

        $this->assertStringContainsString('/x?page=1', $html);
        $this->assertStringContainsString('/x?page=3', $html);
    }
}
