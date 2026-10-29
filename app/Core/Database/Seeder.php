<?php

declare(strict_types=1);

namespace App\Core\Database;

use App\Core\Logger;
use Closure;
use PDO;

/**
 * Menjalankan berkas seed pada database/seeds.
 *
 * Setiap berkas seed mengembalikan closure:
 *
 *     <?php
 *     return function (PDO $pdo): void {
 *         // idempoten: aman dijalankan lebih dari sekali
 *     };
 *
 * Seed bersifat idempoten karena tidak ada tabel riwayat seed; pendekatan ini
 * dipilih agar seed dapat dijalankan ulang setelah perubahan data awal.
 */
final class Seeder
{
    private string $path;
    private PDO $pdo;

    public function __construct(string $path, ?PDO $pdo = null)
    {
        $this->path = rtrim($path, '/');
        $this->pdo = $pdo ?? Database::pdo();
    }

    /**
     * @return list<string>
     */
    public function files(): array
    {
        $files = glob($this->path . '/*.php') ?: [];
        sort($files, SORT_NATURAL);
        return array_map('basename', $files);
    }

    /**
     * @param callable(string, string): void|null $progress
     * @return list<string>
     */
    public function run(?callable $progress = null): array
    {
        $executed = [];

        foreach ($this->files() as $file) {
            $fullPath = $this->path . '/' . $file;

            /** @var mixed $seed */
            $seed = require $fullPath;

            if (!$seed instanceof Closure) {
                throw new \RuntimeException('Seed harus mengembalikan closure: ' . $file);
            }

            if ($progress !== null) {
                $progress($file, 'running');
            }

            try {
                Database::transaction(static function (PDO $pdo) use ($seed): void {
                    $seed($pdo);
                });
            } catch (\Throwable $e) {
                Logger::error('Seed gagal: ' . $file, ['error' => $e->getMessage()]);

                if ($progress !== null) {
                    $progress($file, 'failed');
                }

                throw new \RuntimeException(sprintf('Seed %s gagal: %s', $file, $e->getMessage()), 0, $e);
            }

            $executed[] = $file;

            if ($progress !== null) {
                $progress($file, 'done');
            }
        }

        return $executed;
    }
}
