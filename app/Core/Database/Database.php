<?php

declare(strict_types=1);

namespace App\Core\Database;

use App\Core\Config;
use PDO;
use PDOStatement;

/**
 * Lapisan akses database terpusat.
 *
 * Aturan:
 *  - seluruh nilai dari input diikat sebagai parameter (prepared statement);
 *  - nama tabel dan kolom hanya berasal dari kode (bukan input pengguna) dan
 *    divalidasi terhadap pola aman sebelum dipakai;
 *  - perubahan multi-tabel dilakukan dalam transaksi.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public const IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    public static function connect(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $driver = strtolower(Config::string('database.driver', 'mysql'));

        if ($driver === 'sqlite') {
            $path = Config::string('database.path', 'storage/database/mo.sqlite');

            if ($path !== ':memory:' && !str_starts_with($path, '/')) {
                $path = Config::basePath($path);
            }

            $directory = $path === ':memory:' ? null : dirname($path);
            if ($directory !== null && !is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }

            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // SQLite menegakkan foreign key hanya bila diaktifkan per koneksi.
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode = WAL');

            self::$pdo = $pdo;
            return self::$pdo;
        }

        $host = Config::string('database.host', '127.0.0.1');
        $port = Config::int('database.port', 3306);
        $database = Config::string('database.database', 'mo_hris');
        $charset = Config::string('database.charset', 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_TIMEOUT            => Config::int('database.options.timeout', 10),
        ];

        // Emulasi prepared statement dimatikan agar MySQL yang mem-parsing
        // parameter (menghindari kasus edge pada tipe data).
        $options[PDO::ATTR_EMULATE_PREPARES] = (bool) (Config::get('database.options.emulate_prepares', false));

        $pdo = new PDO(
            $dsn,
            Config::string('database.username', 'root'),
            (string) Config::get('database.password', ''),
            $options
        );

        // Seluruh waktu disimpan UTC; sesi MySQL dipaksa ke UTC.
        $pdo->exec("SET time_zone = '+00:00'");

        self::$pdo = $pdo;
        return self::$pdo;
    }

    public static function pdo(): PDO
    {
        return self::connect();
    }

    public static function driver(): string
    {
        return strtolower(Config::string('database.driver', 'mysql'));
    }

    public static function disconnect(): void
    {
        self::$pdo = null;
    }

    /**
     * Ganti koneksi (dipakai pengujian).
     */
    public static function useConnection(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /**
     * @param array<int|string, mixed> $params
     */
    private static function prepare(string $sql, array $params = []): PDOStatement
    {
        $pdo = self::connect();
        $statement = $pdo->prepare($sql);

        $index = 0;
        foreach ($params as $key => $value) {
            $parameter = is_int($key) ? ++$index : $key;

            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_null($value) => PDO::PARAM_NULL,
                is_bool($value) => PDO::PARAM_BOOL,
                is_resource($value) => PDO::PARAM_LOB,
                default         => PDO::PARAM_STR,
            };

            $statement->bindValue($parameter, $value, $type);
        }

        $statement->execute();

        return $statement;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public static function select(string $sql, array $params = []): array
    {
        return self::prepare($sql, $params)->fetchAll();
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function selectOne(string $sql, array $params = []): ?array
    {
        $row = self::prepare($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Nilai satu kolom dari baris pertama.
     */
    public static function scalar(string $sql, array $params = [], mixed $default = null): mixed
    {
        $row = self::prepare($sql, $params)->fetchColumn();
        return $row === false ? $default : $row;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return list<int|string>
     */
    public static function column(string $sql, array $params = []): array
    {
        $values = [];
        foreach (self::select($sql, $params) as $row) {
            $first = reset($row);
            if (is_scalar($first)) {
                $values[] = $first;
            }
        }
        return $values;
    }

    /**
     * Jalankan statement dan kembalikan jumlah baris terpengaruh.
     */
    public static function statement(string $sql, array $params = []): int
    {
        return self::prepare($sql, $params)->rowCount();
    }

    /**
     * Jalankan statement INSERT dan kembalikan id baru.
     */
    public static function insert(string $sql, array $params = []): int
    {
        self::prepare($sql, $params);
        return (int) self::connect()->lastInsertId();
    }

    /**
     * Sisipkan satu baris dari array asosiatif.
     *
     * @param array<string, mixed> $data
     */
    public static function insertRow(string $table, array $data): int
    {
        self::assertIdentifier($table);
        $columns = self::quoteColumns(array_keys($data));

        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', self::quoteTable($table), $columns, $placeholders);

        return self::insert($sql, array_values($data));
    }

    /**
     * Perbarui baris berdasarkan kondisi.
     *
     * @param array<string, mixed> $data
     * @param array<int|string, mixed> $whereParams
     */
    public static function updateRow(string $table, array $data, string $where, array $whereParams = []): int
    {
        self::assertIdentifier($table);

        $assignments = [];
        foreach (array_keys($data) as $column) {
            self::assertIdentifier((string) $column);
            $assignments[] = self::quoteIdentifier((string) $column) . ' = ?';
        }

        $sql = sprintf('UPDATE %s SET %s WHERE %s', self::quoteTable($table), implode(', ', $assignments), $where);

        return self::statement($sql, array_merge(array_values($data), $whereParams));
    }

    public static function count(string $table, string $where = '1 = 1', array $params = []): int
    {
        self::assertIdentifier($table);
        return (int) self::scalar(
            sprintf('SELECT COUNT(*) FROM %s WHERE %s', self::quoteTable($table), $where),
            $params,
            0
        );
    }

    public static function exists(string $table, string $where, array $params = []): bool
    {
        return self::count($table, $where, $params) > 0;
    }

    /**
     * Jalankan callback dalam transaksi; otomatis rollback bila exception.
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connect();

        if ($pdo->inTransaction()) {
            return $callback($pdo);
        }

        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function beginTransaction(): void
    {
        $pdo = self::connect();
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }
    }

    public static function commit(): void
    {
        $pdo = self::connect();
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    }

    public static function rollBack(): void
    {
        $pdo = self::connect();
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    /**
     * Nama tabel yang diapit sesuai dialek driver.
     */
    public static function quoteTable(string $table): string
    {
        self::assertIdentifier($table);
        return self::quoteIdentifier($table);
    }

    /**
     * @param list<string> $columns
     */
    public static function quoteColumns(array $columns): string
    {
        $quoted = [];
        foreach ($columns as $column) {
            self::assertIdentifier((string) $column);
            $quoted[] = self::quoteIdentifier((string) $column);
        }
        return implode(', ', $quoted);
    }

    public static function quoteIdentifier(string $identifier): string
    {
        return self::driver() === 'sqlite'
            ? '"' . str_replace('"', '""', $identifier) . '"'
            : '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Tolak identifier yang tidak aman sebelum dipakai pada SQL.
     */
    public static function assertIdentifier(string $identifier): void
    {
        if (preg_match(self::IDENTIFIER_PATTERN, $identifier) !== 1) {
            throw new \InvalidArgumentException('Identifier tidak valid: ' . $identifier);
        }
    }

    /**
     * placeholder untuk daftar IN (...).
     */
    public static function placeholders(int $count): string
    {
        return implode(', ', array_fill(0, max(0, $count), '?'));
    }
}
