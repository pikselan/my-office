<?php

declare(strict_types=1);

namespace App\Core\Database;

use App\Core\Logger;
use PDO;

/**
 * Menjalankan migrasi SQL berurutan.
 *
 * Setiap berkas migrasi:
 *  - dieksekusi berurutan berdasarkan nomor pada nama berkas;
 *  - dijalankan dalam transaksi bila driver mendukung (SQLite mendukung DDL
 *    transaksional; MySQL melakukan implicit commit pada DDL sehingga transaksi
 *    bersifat best-effort);
 *  - dicatat pada tabel `schema_migrations` bersama nomor batch.
 */
final class Migrator
{
    private string $path;
    private PDO $pdo;

    public function __construct(string $path, ?PDO $pdo = null)
    {
        $this->path = rtrim($path, '/');
        $this->pdo = $pdo ?? Database::pdo();
    }

    public function ensureTable(): void
    {
        if (Database::driver() === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS "schema_migrations" ('
                . ' "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,'
                . ' "migration" TEXT NOT NULL,'
                . ' "batch" INTEGER NOT NULL DEFAULT 1,'
                . ' "executed_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,'
                . ' "execution_ms" INTEGER NOT NULL DEFAULT 0'
                . ')'
            );
            $this->pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS "schema_migrations_migration_unique" ON "schema_migrations" ("migration")');
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS `schema_migrations` ('
            . ' `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . ' `migration` VARCHAR(190) NOT NULL,'
            . ' `batch` INT UNSIGNED NOT NULL DEFAULT 1,'
            . ' `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . ' `execution_ms` INT UNSIGNED NOT NULL DEFAULT 0,'
            . ' PRIMARY KEY (`id`),'
            . ' UNIQUE KEY `uq_schema_migrations_migration` (`migration`)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * @return list<string>
     */
    public function files(): array
    {
        $files = glob($this->path . '/*.sql') ?: [];
        sort($files, SORT_NATURAL);
        return array_map('basename', $files);
    }

    /**
     * @return list<string>
     */
    public function applied(): array
    {
        $rows = Database::select('SELECT migration FROM schema_migrations ORDER BY id');
        return array_map(static fn (array $row): string => (string) $row['migration'], $rows);
    }

    /**
     * @return list<string>
     */
    public function pending(): array
    {
        $applied = $this->applied();
        return array_values(array_filter($this->files(), static fn (string $file): bool => !in_array($file, $applied, true)));
    }

    public function nextBatch(): int
    {
        $value = Database::scalar('SELECT MAX(batch) FROM schema_migrations', [], 0);
        return ((int) $value) + 1;
    }

    /**
     * Jalankan seluruh migrasi yang belum diterapkan.
     *
     * @return array{applied: list<string>, skipped: list<string>, batch: int}
     */
    public function run(?callable $progress = null): array
    {
        $this->ensureTable();

        $pending = $this->pending();

        if ($pending === []) {
            return ['applied' => [], 'skipped' => [], 'batch' => $this->nextBatch() - 1];
        }

        $batch = $this->nextBatch();
        $applied = [];
        $skipped = [];

        foreach ($pending as $file) {
            $fullPath = $this->path . '/' . $file;
            $sql = (string) file_get_contents($fullPath);

            if ($progress !== null) {
                $progress($file, 'running');
            }

            $started = microtime(true);

            try {
                $this->execute($sql);
            } catch (\Throwable $e) {
                Database::rollBack();
                Logger::error('Migrasi gagal: ' . $file, ['error' => $e->getMessage()]);

                if ($progress !== null) {
                    $progress($file, 'failed');
                }

                throw new \RuntimeException(sprintf('Migrasi %s gagal: %s', $file, $e->getMessage()), 0, $e);
            }

            $duration = (int) round((microtime(true) - $started) * 1000);

            Database::statement(
                'INSERT INTO schema_migrations (migration, batch, executed_at, execution_ms) VALUES (?, ?, ?, ?)',
                [$file, $batch, gmdate('Y-m-d H:i:s'), $duration]
            );

            $applied[] = $file;

            if ($progress !== null) {
                $progress($file, 'done');
            }
        }

        return ['applied' => $applied, 'skipped' => $skipped, 'batch' => $batch];
    }

    /**
     * Eksekusi isi satu berkas migrasi.
     */
    private function execute(string $sql): void
    {
        $isSqlite = Database::driver() === 'sqlite';
        $statements = SqliteDialect::splitStatements($sql);

        if ($isSqlite) {
            $translated = [];
            foreach ($statements as $statement) {
                foreach (SqliteDialect::translateStatement($statement) as $item) {
                    $translated[] = $item;
                }
            }
            $statements = $translated;
        }

        Database::beginTransaction();

        try {
            foreach ($statements as $statement) {
                if (trim($statement) === '') {
                    continue;
                }
                Database::statement($statement);
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * Hapus seluruh tabel (dipakai `db:fresh` pada lingkungan development).
     *
     * @return list<string>
     */
    public function dropAllTables(): array
    {
        $dropped = [];

        if (Database::driver() === 'sqlite') {
            $this->pdo->exec('PRAGMA foreign_keys = OFF');

            $rows = Database::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");

            foreach ($rows as $row) {
                $table = (string) $row['name'];
                Database::statement('DROP TABLE IF EXISTS "' . $table . '"');
                $dropped[] = $table;
            }

            $this->pdo->exec('PRAGMA foreign_keys = ON');
            return $dropped;
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $rows = Database::select('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()');

        foreach ($rows as $row) {
            $table = (string) reset($row);
            Database::statement('DROP TABLE IF EXISTS `' . $table . '`');
            $dropped[] = $table;
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        return $dropped;
    }
}
