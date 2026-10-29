<?php

declare(strict_types=1);

namespace App\Core\Database;

/**
 * Penerjemah DDL MySQL 8 ke SQLite.
 *
 * Tujuan: satu sumber kebenaran skema (DDL MySQL pada database/migrations)
 * tetap dapat dieksekusi pada lingkungan tanpa MySQL Server, yaitu sandbox
 * pengembangan dan pengujian. Kelas ini HANYA dipakai oleh tooling/test;
 * jalur produksi menjalankan berkas migrasi apa adanya pada MySQL.
 *
 * Batasan yang diterima (tidak diterjemahkan, tidak dipakai di skema MO):
 * stored procedure, trigger, view, partisi, FULLTEXT, dan fungsi JSON MySQL.
 */
final class SqliteDialect
{
    /**
     * Terjemahkan satu berkas migrasi.
     */
    public static function translate(string $sql): string
    {
        $output = [];

        foreach (self::splitStatements($sql) as $statement) {
            foreach (self::translateStatement($statement) as $translated) {
                $output[] = $translated;
            }
        }

        return implode(";\n", $output) . ";\n";
    }

    /**
     * Pisahkan berkas SQL menjadi statement, menghormati literal string
     * dan komentar.
     *
     * @return list<string>
     */
    public static function splitStatements(string $sql): array
    {
        $sql = self::stripComments($sql);

        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $inString = false;
        $quote = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($inString) {
                $buffer .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $buffer .= $sql[$i + 1];
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    $inString = false;
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $inString = true;
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }

    /**
     * @return list<string>
     */
    public static function translateStatement(string $statement): array
    {
        $trimmed = ltrim($statement);

        if (preg_match('/^CREATE\s+TABLE\b/i', $trimmed) === 1) {
            return self::translateCreateTable($statement);
        }

        if (preg_match('/^ALTER\s+TABLE\b/i', $trimmed) === 1) {
            return [self::translateTypes($statement)];
        }

        return [$statement];
    }

    /**
     * @return list<string>
     */
    private static function translateCreateTable(string $statement): array
    {
        $open = strpos($statement, '(');
        $close = strrpos($statement, ')');

        if ($open === false || $close === false || $close < $open) {
            return [$statement];
        }

        $head = substr($statement, 0, $open);
        $body = substr($statement, $open + 1, $close - $open - 1);
        $tail = substr($statement, $close + 1);

        if (preg_match('/^CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?[`"\[]?([A-Za-z_][A-Za-z0-9_]*)[`"\]]?/i', $head, $m) !== 1) {
            return [$statement];
        }

        $ifNotExists = trim($m[1] ?? '') !== '' ? 'IF NOT EXISTS ' : '';
        $table = $m[2];

        // Opsi tabel MySQL (ENGINE, CHARSET, COLLATE, COMMENT) dibuang.
        $tail = trim(preg_replace('/\b(ENGINE|DEFAULT\s+CHARSET|CHARACTER\s+SET|COLLATE|COMMENT|AUTO_INCREMENT|ROW_FORMAT|KEY_BLOCK_SIZE)\b.*/is', '', $tail) ?? '');
        $tail = rtrim(rtrim($tail), ';');

        $elements = self::splitTopLevel($body);

        $columns = [];
        $indexes = [];
        $tableConstraints = [];
        $autoIncrementColumn = null;
        $primaryKeyColumns = null;

        foreach ($elements as $element) {
            $element = trim($element);

            if ($element === '') {
                continue;
            }

            $upper = strtoupper($element);

            if (str_starts_with($upper, 'PRIMARY KEY')) {
                if (preg_match('/^PRIMARY\s+KEY\s*\((.*)\)$/is', $element, $matches) === 1) {
                    $primaryKeyColumns = trim($matches[1]);
                }
                continue;
            }

            if (preg_match('/^(UNIQUE\s+KEY|UNIQUE\s+INDEX|UNIQUE)\s+([A-Za-z_][A-Za-z0-9_]*)\s*\((.*)\)$/is', $element, $matches) === 1) {
                $indexes[] = sprintf(
                    'CREATE UNIQUE INDEX IF NOT EXISTS "%s" ON "%s" (%s)',
                    $matches[2],
                    $table,
                    self::normalizeColumnList($matches[3])
                );
                continue;
            }

            if (preg_match('/^(UNIQUE\s+KEY|UNIQUE\s+INDEX|UNIQUE)\s*\((.*)\)$/is', $element, $matches) === 1) {
                $indexes[] = sprintf(
                    'CREATE UNIQUE INDEX IF NOT EXISTS "%s_%s" ON "%s" (%s)',
                    $table,
                    self::indexSuffix($matches[2]),
                    $table,
                    self::normalizeColumnList($matches[2])
                );
                continue;
            }

            if (preg_match('/^(KEY|INDEX)\s+([A-Za-z_][A-Za-z0-9_]*)\s*\((.*)\)$/is', $element, $matches) === 1) {
                $indexes[] = sprintf(
                    'CREATE INDEX IF NOT EXISTS "%s" ON "%s" (%s)',
                    $matches[2],
                    $table,
                    self::normalizeColumnList($matches[3])
                );
                continue;
            }

            if (preg_match('/^CONSTRAINT\s+([A-Za-z_][A-Za-z0-9_]*)\s+UNIQUE\s*\((.*)\)$/is', $element, $matches) === 1) {
                $indexes[] = sprintf(
                    'CREATE UNIQUE INDEX IF NOT EXISTS "%s" ON "%s" (%s)',
                    $matches[1],
                    $table,
                    self::normalizeColumnList($matches[2])
                );
                continue;
            }

            if (str_starts_with($upper, 'CONSTRAINT') && str_contains($upper, 'FOREIGN KEY')) {
                $tableConstraints[] = self::translateTypes($element);
                continue;
            }

            if (str_starts_with($upper, 'FOREIGN KEY')) {
                $tableConstraints[] = self::translateTypes($element);
                continue;
            }

            // Definisi kolom.
            $name = self::columnName($element);
            $translated = self::translateTypes($element);

            if (stripos($element, 'AUTO_INCREMENT') !== false && $name !== null) {
                $autoIncrementColumn = $name;
                $translated = preg_replace('/\bAUTO_INCREMENT\b/i', '', $translated) ?? $translated;
                $translated = rtrim(trim($translated));
            }

            $columns[] = $translated;
        }

        // Tangani AUTO_INCREMENT: gabungkan dengan PRIMARY KEY pada kolomnya.
        if ($autoIncrementColumn !== null) {
            foreach ($columns as $index => $column) {
                if (self::columnName($column) === $autoIncrementColumn) {
                    $columns[$index] = rtrim($column) . ' PRIMARY KEY AUTOINCREMENT';
                    break;
                }
            }
        } elseif ($primaryKeyColumns !== null) {
            $columns[] = 'PRIMARY KEY (' . self::normalizeColumnList($primaryKeyColumns) . ')';
        }

        $definition = array_merge($columns, $tableConstraints);

        $create = sprintf('CREATE TABLE %s"%s" (%s%s)', $ifNotExists, $table, "\n  ", implode(",\n  ", $definition));

        if ($tail !== '') {
            $create .= ' ' . $tail;
        }

        return array_merge([$create], $indexes);
    }

    /**
     * Terjemahkan tipe data MySQL pada sebuah definisi.
     */
    private static function translateTypes(string $definition): string
    {
        // Hapus atribut yang tidak dikenal SQLite.
        $definition = (string) preg_replace('/\bON\s+UPDATE\s+CURRENT_TIMESTAMP(?:\(\))?/i', '', $definition);
        $definition = (string) preg_replace('/\bCHARACTER\s+SET\s+[A-Za-z0-9_]+/i', '', $definition);
        $definition = (string) preg_replace('/\bCOLLATE\s+[A-Za-z0-9_]+/i', '', $definition);
        $definition = (string) preg_replace('/COMMENT\s+(["\']).*?\1/si', '', $definition);
        $definition = (string) preg_replace('/\bUNSIGNED\b/i', '', $definition);
        $definition = (string) preg_replace('/\bZEROFILL\b/i', '', $definition);
        $definition = (string) preg_replace('/\bINVISIBLE\b/i', '', $definition);

        // Setiap pengganti diakhiri spasi karena pola tipe ikut mengonsumsi
        // spasi sesudahnya; perapian spasi dilakukan pada langkah terakhir.
        $replacements = [
            // Bilangan bulat -> INTEGER
            '/\b(BIGINT|INT|INTEGER|SMALLINT|TINYINT|MEDIUMINT)\b\s*(?:\(\s*\d+\s*\))?/i' => 'INTEGER ',
            '/\b(BOOL|BOOLEAN)\b\s*(?:\(\s*\d+\s*\))?/i' => 'INTEGER ',
            // Teks -> TEXT
            '/\b(LONGTEXT|MEDIUMTEXT|TINYTEXT|VARCHAR|CHAR|TEXT)\b\s*(?:\(\s*\d+\s*\))?/i' => 'TEXT ',
            // Waktu -> TEXT (disimpan UTC dalam format ISO)
            '/\b(DATETIME|TIMESTAMP|DATE|TIME|YEAR)\b\s*(?:\(\s*\d+\s*\))?/i' => 'TEXT ',
            // ENUM/SET -> TEXT (nilai divalidasi aplikasi)
            '/\bENUM\s*\((?:[^()]|\([^()]*\))*\)/i' => 'TEXT ',
            '/\bSET\s*\((?:[^()]|\([^()]*\))*\)/i' => 'TEXT ',
            '/\bJSON\b\s*(?:\(\s*\d+\s*\))?/i' => 'TEXT ',
            '/\bBLOB\b\s*(?:\(\s*\d+\s*\))?/i' => 'BLOB ',
            '/\b(DOUBLE|FLOAT|REAL)\b\s*(?:\([^)]*\))?/i' => 'REAL ',
            '/\bDECIMAL\b\s*(?:\(\s*\d+\s*,\s*\d+\s*\))?/i' => 'DECIMAL ',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $definition = (string) preg_replace($pattern, $replacement, $definition);
        }

        return trim((string) preg_replace('/\s+/', ' ', $definition) ?? '');
    }

    /**
     * Nama kolom dari sebuah definisi kolom.
     */
    private static function columnName(string $definition): ?string
    {
        if (preg_match('/^\s*[`"\[]?([A-Za-z_][A-Za-z0-9_]*)[`"\]]?/', $definition, $matches) !== 1) {
            return null;
        }
        return $matches[1];
    }

    /**
     * Pisahkan isi tabel berdasarkan koma pada tingkat terluar.
     *
     * @return list<string>
     */
    private static function splitTopLevel(string $body): array
    {
        $parts = [];
        $depth = 0;
        $buffer = '';
        $inString = false;
        $quote = '';
        $length = strlen($body);

        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];

            if ($inString) {
                $buffer .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $buffer .= $body[$i + 1];
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    $inString = false;
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $inString = true;
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === '(') {
                $depth++;
            }

            if ($char === ')') {
                $depth = max(0, $depth - 1);
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $parts[] = $buffer;
        }

        return $parts;
    }

    /**
     * Bersihkan daftar kolom dari tanda kutip dan atribut lebar kolom.
     */
    private static function normalizeColumnList(string $list): string
    {
        $columns = [];

        foreach (explode(',', $list) as $column) {
            $column = trim($column);
            $column = (string) preg_replace('/\s*\(\s*\d+\s*\)\s*$/', '', $column);
            $column = trim($column, '`"[] ');

            if ($column !== '') {
                $columns[] = '"' . $column . '"';
            }
        }

        return implode(', ', $columns);
    }

    private static function indexSuffix(string $list): string
    {
        $parts = [];
        foreach (explode(',', $list) as $column) {
            $column = trim($column, '`"[] ');
            if ($column !== '') {
                $parts[] = $column;
            }
        }
        return implode('_', $parts);
    }

    private static function stripComments(string $sql): string
    {
        $sql = (string) preg_replace('!/\*.*?\*/!s', '', $sql);
        $sql = (string) preg_replace('/^\s*--[^\n]*/m', '', $sql);
        $sql = (string) preg_replace('/^\s*#[^\n]*/m', '', $sql);
        return $sql;
    }
}
