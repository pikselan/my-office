<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Database\Database;

/**
 * Validator input.
 *
 * Dipakai pada sisi server sebagai sumber kebenaran; validasi pada browser
 * hanya untuk umpan balik cepat.
 *
 * Bentuk aturan: 'required|email|max:150' atau 'unique:users,email'.
 * Pesan diambil dari Translator dengan kunci `validation.*`.
 */
final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, string|list<string>> */
    private array $rules;

    /** @var array<string, string> */
    private array $labels;

    /** @var array<string, array{0:string,1:int}|null> */
    private array $sizeCache = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $labels
     */
    private function __construct(array $data, array $rules, array $labels = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->labels = $labels;
    }

    /**
     * Jalankan validasi; kembalikan daftar error per bidang.
     *
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $labels
     * @return array<string, list<string>>
     */
    public static function validate(array $data, array $rules, array $labels = []): array
    {
        return (new self($data, $rules, $labels))->run();
    }

    /**
     * @return array<string, list<string>>
     */
    public function run(): array
    {
        foreach ($this->rules as $field => $rules) {
            $ruleList = is_array($rules) ? $rules : explode('|', (string) $rules);

            foreach ($ruleList as $rule) {
                $rule = trim((string) $rule);
                if ($rule === '') {
                    continue;
                }

                [$name, $parameters] = $this->parseRule($rule);

                if (!$this->apply($field, $name, $parameters)) {
                    // Satu kegagalan per bidang sudah cukup untuk formulir;
                    // aturan berikutnya tetap dijalankan agar pesan lengkap.
                    continue;
                }
            }
        }

        return $this->errors;
    }

    /**
     * @param list<string> $parameters
     */
    private function apply(string $field, string $name, array $parameters): bool
    {
        $value = $this->value($field);
        $present = $this->present($field);

        if ($name === 'required' && !$this->validateRequired($value)) {
            return $this->fail($field, 'required');
        }

        // Aturan lain hanya dijalankan bila nilai ada, kecuali aturan
        // yang secara eksplisit memeriksa ketiadaan nilai.
        if (!$present && !in_array($name, ['required', 'boolean', 'array'], true)) {
            return true;
        }

        return match ($name) {
            'email'      => filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? true : $this->fail($field, 'email'),
            'url'        => filter_var($value, FILTER_VALIDATE_URL) !== false ? true : $this->fail($field, 'url'),
            'numeric'    => is_numeric($value) ? true : $this->fail($field, 'numeric'),
            'integer'    => filter_var($value, FILTER_VALIDATE_INT) !== false ? true : $this->fail($field, 'integer'),
            'boolean'    => true,
            'array'      => is_array($value) ? true : $this->fail($field, 'array'),
            'string'     => is_string($value) ? true : $this->fail($field, 'string'),
            'alpha_dash' => preg_match('/^[a-zA-Z0-9_-]+$/', (string) $value) === 1 ? true : $this->fail($field, 'alpha_dash'),
            'alpha_num'  => preg_match('/^[a-zA-Z0-9]+$/', (string) $value) === 1 ? true : $this->fail($field, 'alpha_num'),
            'date'       => $this->validateDate($value) ? true : $this->fail($field, 'date'),
            'date_format' => $this->validateDateFormat($value, $parameters[0] ?? 'Y-m-d')
                ? true : $this->fail($field, 'date_format', ['format' => $parameters[0] ?? 'Y-m-d']),
            'min'        => $this->validateMin($field, $value, $parameters[0] ?? '0'),
            'max'        => $this->validateMax($field, $value, $parameters[0] ?? '0'),
            // min_chars / max_chars selalu membandingkan panjang teks,
            // dipakai untuk kata sandi agar '12345678' tidak lolos min:8.
            'min_chars'  => $this->validateMinChars($field, $value, $parameters[0] ?? '0'),
            'max_chars'  => $this->validateMaxChars($field, $value, $parameters[0] ?? '0'),
            'digits'     => is_numeric($value) && strlen((string) (int) $value) === (int) ($parameters[0] ?? 0)
                ? true : $this->fail($field, 'digits', ['digits' => $parameters[0] ?? 0]),
            'in'         => in_array((string) $value, $parameters, true) ? true : $this->fail($field, 'in'),
            'not_in'     => !in_array((string) $value, $parameters, true) ? true : $this->fail($field, 'not_in'),
            'confirmed'  => $value === ($this->data[$field . '_confirmation'] ?? null) ? true : $this->fail($field, 'confirmed'),
            'same'       => $value === ($this->data[$parameters[0] ?? ''] ?? null)
                ? true : $this->fail($field, 'same', ['other' => $parameters[0] ?? '']),
            'different'  => $value !== ($this->data[$parameters[0] ?? ''] ?? null)
                ? true : $this->fail($field, 'different', ['other' => $parameters[0] ?? '']),
            'regex'      => $this->validateRegex($value, $parameters[0] ?? '') ? true : $this->fail($field, 'regex'),
            'after'      => $this->validateAfter($value, $parameters[0] ?? '') ? true : $this->fail($field, 'after', ['date' => $parameters[0] ?? '']),
            'before'     => $this->validateBefore($value, $parameters[0] ?? '') ? true : $this->fail($field, 'before', ['date' => $parameters[0] ?? '']),
            'unique'     => $this->validateUnique($field, $value, $parameters),
            'exists'     => $this->validateExists($value, $parameters),
            default      => true,
        };
    }

    /**
     * @param list<string> $parameters
     */
    private function validateMin(string $field, mixed $value, string $parameter): bool
    {
        $size = $this->size($field, $value);

        if ($size < (float) $parameter) {
            return $this->fail($field, is_numeric($value) ? 'min.numeric' : 'min.string', ['min' => $parameter]);
        }

        return true;
    }

    /**
     * @param list<string> $parameters
     */
    private function validateMax(string $field, mixed $value, string $parameter): bool
    {
        $size = $this->size($field, $value);

        if ($size > (float) $parameter) {
            return $this->fail($field, is_numeric($value) ? 'max.numeric' : 'max.string', ['max' => $parameter]);
        }

        return true;
    }

    private function validateMinChars(string $field, mixed $value, string $parameter): bool
    {
        $length = $this->length($value);

        if ($length < (int) $parameter) {
            return $this->fail($field, 'min.string', ['min' => $parameter]);
        }

        return true;
    }

    private function validateMaxChars(string $field, mixed $value, string $parameter): bool
    {
        $length = $this->length($value);

        if ($length > (int) $parameter) {
            return $this->fail($field, 'max.string', ['max' => $parameter]);
        }

        return true;
    }

    private function length(mixed $value): int
    {
        if (is_array($value)) {
            return count($value);
        }

        if ($value === null) {
            return 0;
        }

        return function_exists('mb_strlen') ? mb_strlen((string) $value, 'UTF-8') : strlen((string) $value);
    }

    /**
     * @param list<string> $parameters
     */
    private function validateUnique(string $field, mixed $value, array $parameters): bool
    {
        [$table, $column] = $this->parseTableColumn($parameters, $field);

        if ($table === null) {
            return true;
        }

        $sql = sprintf('SELECT COUNT(*) FROM %s WHERE %s = ?', Database::quoteTable($table), Database::quoteIdentifier($column));
        $params = [$value];

        // Bentuk: unique:table,column,ignoreColumn,ignoreValue
        if (isset($parameters[2], $parameters[3])) {
            $ignoreColumn = (string) $parameters[2];
            Database::assertIdentifier($ignoreColumn);

            if ($parameters[3] !== '' && $parameters[3] !== null) {
                $sql .= ' AND ' . Database::quoteIdentifier($ignoreColumn) . ' <> ?';
                $params[] = $parameters[3];
            }
        }

        // Kolom soft delete tidak dihitung sebagai duplikasi.
        if ($this->tableHasColumn($table, 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }

        $count = (int) Database::scalar($sql, $params, 0);

        return $count === 0 ? true : $this->fail($field, 'unique');
    }

    /**
     * @param list<string> $parameters
     */
    private function validateExists(mixed $value, array $parameters): bool
    {
        [$table, $column] = $this->parseTableColumn($parameters, 'field');

        if ($table === null) {
            return true;
        }

        $sql = sprintf('SELECT COUNT(*) FROM %s WHERE %s = ?', Database::quoteTable($table), Database::quoteIdentifier($column));
        $params = [$value];

        if (isset($parameters[2])) {
            $extraColumn = (string) $parameters[2];
            Database::assertIdentifier($extraColumn);
            $sql .= ' AND ' . Database::quoteIdentifier($extraColumn) . ' = ?';
            $params[] = $parameters[3] ?? null;
        }

        return ((int) Database::scalar($sql, $params, 0)) > 0 ? true : $this->fail('field', 'exists');
    }

    /**
     * @param list<string> $parameters
     * @return array{0: ?string, 1: string}
     */
    private function parseTableColumn(array $parameters, string $fallbackColumn): array
    {
        $table = $parameters[0] ?? null;
        $column = $parameters[1] ?? $fallbackColumn;

        if ($table === null || $table === '') {
            return [null, $column];
        }

        try {
            Database::assertIdentifier($table);
            Database::assertIdentifier($column);
        } catch (\InvalidArgumentException) {
            return [null, $column];
        }

        return [$table, $column];
    }

    private function validateRequired(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_array($value)) {
            return $value !== [];
        }
        return $value !== null;
    }

    private function validateDate(mixed $value): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4));
        }

        return strtotime($value) !== false;
    }

    private function validateDateFormat(mixed $value, string $format): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $parsed = \DateTimeImmutable::createFromFormat($format, $value);
        return $parsed !== false && $parsed->format($format) === $value;
    }

    private function validateRegex(mixed $value, string $pattern): bool
    {
        if ($pattern === '') {
            return true;
        }

        $delimiter = $pattern[0];
        $end = strrpos($pattern, $delimiter);

        if ($end === false || $end === 0) {
            return false;
        }

        $body = substr($pattern, 1, $end - 1);
        $modifiers = substr($pattern, $end + 1);

        return preg_match($delimiter . $body . $delimiter . $modifiers, (string) $value) === 1;
    }

    private function validateAfter(mixed $value, string $parameter): bool
    {
        if ($parameter === '' || !is_string($value)) {
            return true;
        }

        $base = $this->data[$parameter] ?? $parameter;

        return strtotime($value) > strtotime((string) $base);
    }

    private function validateBefore(mixed $value, string $parameter): bool
    {
        if ($parameter === '' || !is_string($value)) {
            return true;
        }

        $base = $this->data[$parameter] ?? $parameter;

        return strtotime($value) < strtotime((string) $base);
    }

    /**
     * Ukuran nilai: panjang teks untuk string, nilai untuk angka.
     */
    private function size(string $field, mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_array($value)) {
            return (float) count($value);
        }

        return (float) (function_exists('mb_strlen') ? mb_strlen((string) $value, 'UTF-8') : strlen((string) $value));
    }

    private function present(string $field): bool
    {
        if (!array_key_exists($field, $this->data)) {
            return false;
        }

        $value = $this->data[$field];

        return !($value === null || (is_string($value) && trim($value) === ''));
    }

    private function value(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;

        if (array_key_exists($key, $this->sizeCache)) {
            return $this->sizeCache[$key] !== null;
        }

        $has = false;

        try {
            if (Database::driver() === 'sqlite') {
                $rows = Database::select('PRAGMA table_info(' . Database::quoteTable($table) . ')');
                foreach ($rows as $row) {
                    if (($row['name'] ?? null) === $column) {
                        $has = true;
                        break;
                    }
                }
            } else {
                $count = (int) Database::scalar(
                    'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                    [$table, $column],
                    0
                );
                $has = $count > 0;
            }
        } catch (\Throwable) {
            $has = false;
        }

        $this->sizeCache[$key] = $has ? ['', 0] : null;

        return $has;
    }

    /**
     * @param array<string, mixed> $replace
     */
    private function fail(string $field, string $rule, array $replace = []): bool
    {
        $label = $this->labels[$field] ?? $field;

        $this->errors[$field][] = Translator::get(
            'validation.' . $rule,
            array_merge(['attribute' => $label], $replace)
        );

        return false;
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function parseRule(string $rule): array
    {
        if (!str_contains($rule, ':')) {
            return [$rule, []];
        }

        [$name, $parameterString] = explode(':', $rule, 2);

        return [trim($name), explode(',', $parameterString)];
    }
}
