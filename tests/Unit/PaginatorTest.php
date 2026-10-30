<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Paginator;
use Tests\Support\TestCase;

final class PaginatorTest extends TestCase
{
    public function testHalamanTerakhirDihitungDariTotal(): void
    {
        $paginator = new Paginator(95, 25, 1, '/employees');

        $this->assertSame(4, $paginator->lastPage());
        $this->assertSame(25, $paginator->perPage());
        $this->assertTrue($paginator->hasPages());
    }

    public function testOffsetMengikutiHalamanAktif(): void
    {
        $paginator = new Paginator(95, 25, 3, '/employees');

        $this->assertSame(50, $paginator->offset());
        $this->assertSame(51, $paginator->from());
        $this->assertSame(75, $paginator->to());
    }

    public function testHalamanTidakBolehMelebihiHalamanTerakhir(): void
    {
        $paginator = new Paginator(10, 25, 99, '/employees');

        $this->assertSame(1, $paginator->currentPage());
    }

    public function testTautanMempertahankanParameterPencarian(): void
    {
        $paginator = new Paginator(95, 25, 1, '/employees', ['q' => 'budi', 'status' => 'active']);

        $this->assertSame('/employees?q=budi&status=active&page=2', $paginator->url(2));
    }

    public function testTanpaHalamanCukupSatuHalaman(): void
    {
        $paginator = new Paginator(3, 25, 1, '/employees');

        $this->assertFalse($paginator->hasPages());
        $this->assertStringContainsString('1–3', $paginator->render());
    }

    public function testRenderMenyertakanTautanHalamanAktif(): void
    {
        $paginator = new Paginator(95, 25, 2, '/employees');
        $html = $paginator->render();

        $this->assertStringContainsString('is-active', $html);
        $this->assertStringContainsString('/employees?page=3', $html);
    }
}
