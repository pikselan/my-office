<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Representasi response HTTP.
 */
final class Response
{
    private string $body;
    private int $status;
    /** @var array<string, string> */
    private array $headers;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $body = '', int $status = 200, array $headers = [])
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
    }

    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($body, $status, $headers + ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * @param array<string, mixed>|list<mixed> $data
     */
    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            $headers + ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function noContent(int $status = 204): self
    {
        return new self('', $status);
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        foreach ($headers as $key => $value) {
            $clone->headers[$key] = $value;
        }
        return $clone;
    }

    public function withStatus(int $status): self
    {
        $clone = clone $this;
        $clone->status = $status;
        return $clone;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (PHP_SAPI === 'cli' && !self::$forceSendInCli) {
            return;
        }

        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        echo $this->body;
    }

    private static bool $forceSendInCli = false;

    /**
     * Untuk keperluan pengujian: keluarkan output meski berjalan di CLI.
     */
    public static function forceSendInCli(bool $value = true): void
    {
        self::$forceSendInCli = $value;
    }
}
