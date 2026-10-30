<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Registry dan runner perintah CLI (`bin/mo`).
 */
final class Console
{
    /** @var array<string, array{description:string,handler:callable,usage:string}> */
    private array $commands = [];

    public const COLOR_GREEN = "\033[32m";
    public const COLOR_RED = "\033[31m";
    public const COLOR_YELLOW = "\033[33m";
    public const COLOR_CYAN = "\033[36m";
    public const COLOR_GRAY = "\033[90m";
    public const COLOR_RESET = "\033[0m";

    public function register(string $name, string $description, callable $handler, string $usage = ''): void
    {
        $this->commands[$name] = [
            'description' => $description,
            'handler'     => $handler,
            'usage'       => $usage !== '' ? $usage : $name,
        ];
    }

    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    /**
     * @return array<string, array{description:string,handler:callable,usage:string}>
     */
    public function commands(): array
    {
        return $this->commands;
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $name = $argv[1] ?? 'list';
        $arguments = array_slice($argv, 2);

        if ($name === '' ) {
            $name = 'list';
        }

        if (in_array($name, ['-h', '--help', 'help'], true)) {
            $this->printHelp();
            return 0;
        }

        if (!isset($this->commands[$name])) {
            $this->error("Perintah tidak dikenal: {$name}");
            $this->line('');
            $this->printHelp();
            return 1;
        }

        [$positional, $options] = self::parseArguments($arguments);

        try {
            $status = ($this->commands[$name]['handler'])($positional, $options, $this);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            if (Config::bool('app.debug', false)) {
                $this->line(self::COLOR_GRAY . $e->getTraceAsString() . self::COLOR_RESET);
            }

            return 1;
        }

        return is_int($status) ? $status : 0;
    }

    /**
     * Pisahkan argumen posisi dan opsi (`--flag`, `--key=value`, `-k value`).
     *
     * @param list<string> $arguments
     * @return array{0: list<string>, 1: array<string, mixed>}
     */
    public static function parseArguments(array $arguments): array
    {
        $positional = [];
        $options = [];

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--')) {
                $body = substr($argument, 2);
                if (str_contains($body, '=')) {
                    [$key, $value] = explode('=', $body, 2);
                    $options[$key] = $value;
                } else {
                    $options[$body] = true;
                }
                continue;
            }

            if (str_starts_with($argument, '-') && strlen($argument) > 1) {
                $options[substr($argument, 1)] = true;
                continue;
            }

            $positional[] = $argument;
        }

        return [$positional, $options];
    }

    public function printHelp(): void
    {
        $this->line(self::COLOR_CYAN . 'MO (MyOffice) — command line' . self::COLOR_RESET);
        $this->line('');
        $this->line('Penggunaan:');
        $this->line('  php bin/mo <perintah> [argumen] [--opsi]');
        $this->line('');
        $this->line('Perintah tersedia:');

        $width = 0;
        foreach (array_keys($this->commands) as $name) {
            $width = max($width, strlen($name));
        }

        foreach ($this->commands as $name => $command) {
            $this->line('  ' . self::COLOR_GREEN . str_pad($name, $width) . self::COLOR_RESET . '  ' . $command['description']);
        }
    }

    public function line(string $message = ''): void
    {
        echo $message, PHP_EOL;
    }

    public function info(string $message): void
    {
        $this->line(self::COLOR_CYAN . $message . self::COLOR_RESET);
    }

    public function success(string $message): void
    {
        $this->line(self::COLOR_GREEN . '  ✓ ' . $message . self::COLOR_RESET);
    }

    public function warn(string $message): void
    {
        $this->line(self::COLOR_YELLOW . '  ! ' . $message . self::COLOR_RESET);
    }

    public function error(string $message): void
    {
        fwrite(STDERR, self::COLOR_RED . '  ✗ ' . $message . self::COLOR_RESET . PHP_EOL);
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    public function table(array $headers, array $rows): void
    {
        $widths = [];
        foreach ($headers as $index => $header) {
            $widths[$index] = strlen($header);
        }
        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                $widths[$index] = max($widths[$index] ?? 0, strlen((string) $cell));
            }
        }

        $line = '';
        $separator = '';
        foreach ($widths as $index => $width) {
            $line .= str_pad($headers[$index] ?? '', $width) . '  ';
            $separator .= str_repeat('-', $width) . '  ';
        }

        $this->line(trim($line));
        $this->line(trim($separator));

        foreach ($rows as $row) {
            $out = '';
            foreach ($widths as $index => $width) {
                $out .= str_pad((string) ($row[$index] ?? ''), $width) . '  ';
            }
            $this->line(trim($out));
        }
    }
}
