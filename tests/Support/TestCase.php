<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Kelas dasar pengujian dengan kumpulan assertion sederhana.
 */
abstract class TestCase
{
    /** @var list<string> */
    protected array $failures = [];

    protected int $assertions = 0;

    /**
     * @return list<string>
     */
    public function failures(): array
    {
        return $this->failures;
    }

    public function assertionCount(): int
    {
        return $this->assertions;
    }

    /**
     * Daftar metode uji (diawali `test`).
     *
     * @return list<string>
     */
    public function methods(): array
    {
        $methods = [];

        foreach (get_class_methods($this) as $method) {
            if (str_starts_with($method, 'test')) {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    protected function assertTrue(mixed $value, string $message = 'Nilai diharapkan true'): void
    {
        $this->assertions++;
        if ($value !== true) {
            $this->fail($message . ' (didapat: ' . $this->export($value) . ')');
        }
    }

    protected function assertFalse(mixed $value, string $message = 'Nilai diharapkan false'): void
    {
        $this->assertions++;
        if ($value !== false) {
            $this->fail($message . ' (didapat: ' . $this->export($value) . ')');
        }
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = 'Nilai tidak sama persis'): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            $this->fail($message . ' (diharapkan: ' . $this->export($expected) . ', didapat: ' . $this->export($actual) . ')');
        }
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = 'Nilai tidak sama'): void
    {
        $this->assertions++;
        if ($expected != $actual) {
            $this->fail($message . ' (diharapkan: ' . $this->export($expected) . ', didapat: ' . $this->export($actual) . ')');
        }
    }

    protected function assertNotEquals(mixed $expected, mixed $actual, string $message = 'Nilai seharusnya berbeda'): void
    {
        $this->assertions++;
        if ($expected == $actual) {
            $this->fail($message . ' (keduanya: ' . $this->export($actual) . ')');
        }
    }

    protected function assertNull(mixed $value, string $message = 'Nilai diharapkan null'): void
    {
        $this->assertions++;
        if ($value !== null) {
            $this->fail($message . ' (didapat: ' . $this->export($value) . ')');
        }
    }

    protected function assertNotNull(mixed $value, string $message = 'Nilai tidak boleh null'): void
    {
        $this->assertions++;
        if ($value === null) {
            $this->fail($message);
        }
    }

    protected function assertCount(int $expected, mixed $value, string $message = 'Jumlah elemen tidak sesuai'): void
    {
        $this->assertions++;
        $count = is_countable($value) ? count($value) : -1;

        if ($count !== $expected) {
            $this->fail($message . ' (diharapkan: ' . $expected . ', didapat: ' . $count . ')');
        }
    }

    protected function assertGreaterThan(int|float $expected, int|float $actual, string $message = 'Nilai terlalu kecil'): void
    {
        $this->assertions++;
        if ($actual <= $expected) {
            $this->fail($message . ' (diharapkan > ' . $expected . ', didapat: ' . $actual . ')');
        }
    }

    protected function assertEmpty(mixed $value, string $message = 'Nilai diharapkan kosong'): void
    {
        $this->assertions++;
        if ($value !== [] && $value !== '' && $value !== null && $value !== false) {
            $this->fail($message . ' (didapat: ' . $this->export($value) . ')');
        }
    }

    protected function assertNotEmpty(mixed $value, string $message = 'Nilai tidak boleh kosong'): void
    {
        $this->assertions++;
        if ($value === [] || $value === '' || $value === null || $value === false) {
            $this->fail($message);
        }
    }

    /**
     * @param array<string, mixed>|list<mixed> $haystack
     */
    protected function assertArrayHasKey(string|int $key, array $haystack, string $message = 'Kunci tidak ditemukan'): void
    {
        $this->assertions++;
        if (!array_key_exists($key, $haystack)) {
            $this->fail($message . ' (kunci: ' . $key . ')');
        }
    }

    /**
     * @param array<string, mixed>|list<mixed> $haystack
     */
    protected function assertNotHasKey(string|int $key, array $haystack, string $message = 'Kunci seharusnya tidak ada'): void
    {
        $this->assertions++;
        if (array_key_exists($key, $haystack)) {
            $this->fail($message . ' (kunci: ' . $key . ')');
        }
    }

    /**
     * @param list<mixed>|string $haystack
     */
    protected function assertContains(mixed $needle, array|string $haystack, string $message = 'Nilai tidak ditemukan'): void
    {
        $this->assertions++;
        $found = is_array($haystack) ? in_array($needle, $haystack, true) : str_contains($haystack, (string) $needle);

        if (!$found) {
            $this->fail($message . ' (dicari: ' . $this->export($needle) . ')');
        }
    }

    /**
     * @param list<mixed>|string $haystack
     */
    protected function assertNotContains(mixed $needle, array|string $haystack, string $message = 'Nilai seharusnya tidak ada'): void
    {
        $this->assertions++;
        $found = is_array($haystack) ? in_array($needle, $haystack, true) : str_contains($haystack, (string) $needle);

        if ($found) {
            $this->fail($message . ' (dicari: ' . $this->export($needle) . ')');
        }
    }

    protected function assertStringContainsString(string $needle, string $haystack, string $message = 'Teks tidak ditemukan'): void
    {
        $this->assertContains($needle, $haystack, $message);
    }

    /**
     * Pastikan closure melemparkan exception tertentu.
     */
    protected function assertThrows(callable $callback, string $exceptionClass = \Throwable::class, string $message = 'Exception tidak dilemparkan'): void
    {
        $this->assertions++;

        try {
            $callback();
        } catch (\Throwable $e) {
            if ($e instanceof $exceptionClass) {
                return;
            }
            $this->fail($message . ' (dilemparkan: ' . get_class($e) . ')');
            return;
        }

        $this->fail($message . ' (tidak ada exception)');
    }

    protected function fail(string $message): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[1] ?? [];
        $location = isset($caller['class'], $caller['function']) ? $caller['class'] . '::' . $caller['function'] : 'unknown';

        $this->failures[] = $location . ' — ' . $message;
    }

    protected function export(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: 'array';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        return gettype($value);
    }
}
