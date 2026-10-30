<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Tokens;
use Tests\Support\TestCase;

final class TokensTest extends TestCase
{
    public function testTokenAcakTidakSama(): void
    {
        $first = Tokens::generate();
        $second = Tokens::generate();

        $this->assertNotEquals($first, $second);
        $this->assertSame(64, strlen($first));
    }

    public function testHashDeterministik(): void
    {
        $token = Tokens::generate();

        $this->assertSame(Tokens::hash($token), Tokens::hash($token));
        $this->assertNotEquals($token, Tokens::hash($token));
        $this->assertSame(64, strlen(Tokens::hash($token)));
    }

    public function testPerbandinganAman(): void
    {
        $token = Tokens::generate();
        $hash = Tokens::hash($token);

        $this->assertTrue(Tokens::equals($hash, Tokens::hash($token)));
        $this->assertFalse(Tokens::equals($hash, Tokens::hash(Tokens::generate())));
    }

    public function testTokenMentahTidakMengandungKarakterBerbahaya(): void
    {
        $token = Tokens::generate();
        $this->assertSame(1, preg_match('/^[a-f0-9]+$/', $token));
    }
}
