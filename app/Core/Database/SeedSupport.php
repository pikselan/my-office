<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;

/**
 * Utilitas untuk seed yang idempoten.
 *
 * Seed di MO dapat dijalankan berulang kali, sehingga setiap penyisipan
 * harus aman bila data sudah ada.
 */
final class SeedSupport
{
    /**
     * Cari id berdasarkan kondisi; mendukung nilai NULL.
     *
     * @param array<string, mixed> $match
     */
    public static function findId(PDO $pdo, string $table, array $match): ?int
    {
        [$where, $params] = self::where($match);

        $statement = $pdo->prepare(sprintf('SELECT id FROM %s WHERE %s LIMIT 1', self::table($table), $where));
        $statement->execute($params);
        $value = $statement->fetchColumn();

        return $value === false ? null : (int) $value;
    }

    /**
     * Sisipkan bila belum ada; kembalikan id baris yang ada atau baru.
     *
     * @param array<string, mixed> $match
     * @param array<string, mixed> $data
     */
    public static function insertIfMissing(PDO $pdo, string $table, array $match, array $data): int
    {
        $existing = self::findId($pdo, $table, $match);

        if ($existing !== null) {
            return $existing;
        }

        return self::insert($pdo, $table, array_merge($match, $data));
    }

    /**
     * Sisipkan atau perbarui berdasarkan kondisi.
     *
     * @param array<string, mixed> $match
     * @param array<string, mixed> $data
     */
    public static function upsert(PDO $pdo, string $table, array $match, array $data): int
    {
        $existing = self::findId($pdo, $table, $match);

        if ($existing === null) {
            return self::insert($pdo, $table, array_merge($match, $data));
        }

        self::update($pdo, $table, $existing, $data);

        return $existing;
    }

    /**
     * Sisipkan baris pada tabel pivot bila kombinasi belum ada.
     *
     * @param array<string, mixed> $data
     */
    public static function linkIfMissing(PDO $pdo, string $table, array $data): void
    {
        [$where, $params] = self::where($data);

        $statement = $pdo->prepare(sprintf('SELECT COUNT(*) FROM %s WHERE %s', self::table($table), $where));
        $statement->execute($params);

        if ((int) $statement->fetchColumn() > 0) {
            return;
        }

        self::insert($pdo, $table, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function insert(PDO $pdo, string $table, array $data): int
    {
        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($data as $column => $value) {
            Database::assertIdentifier((string) $column);
            $columns[] = Database::quoteIdentifier((string) $column);
            $placeholders[] = '?';
            $params[] = $value;
        }

        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', self::table($table), implode(', ', $columns), implode(', ', $placeholders));
        $pdo->prepare($sql)->execute($params);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(PDO $pdo, string $table, int $id, array $data): void
    {
        if ($data === []) {
            return;
        }

        $assignments = [];
        $params = [];

        foreach ($data as $column => $value) {
            Database::assertIdentifier((string) $column);
            $assignments[] = Database::quoteIdentifier((string) $column) . ' = ?';
            $params[] = $value;
        }

        $params[] = $id;

        $pdo->prepare(sprintf('UPDATE %s SET %s WHERE id = ?', self::table($table), implode(', ', $assignments)))->execute($params);
    }

    /**
     * Bangun klausa WHERE yang mendukung perbandingan NULL.
     *
     * @param array<string, mixed> $match
     * @return array{0: string, 1: list<mixed>}
     */
    public static function where(array $match): array
    {
        $conditions = [];
        $params = [];

        foreach ($match as $column => $value) {
            Database::assertIdentifier((string) $column);
            $quoted = Database::quoteIdentifier((string) $column);

            if ($value === null) {
                $conditions[] = $quoted . ' IS NULL';
                continue;
            }

            $conditions[] = $quoted . ' = ?';
            $params[] = $value;
        }

        return [$conditions === [] ? '1 = 1' : implode(' AND ', $conditions), $params];
    }

    private static function table(string $table): string
    {
        Database::assertIdentifier($table);
        return Database::quoteIdentifier($table);
    }
}
