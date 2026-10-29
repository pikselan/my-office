<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Exception yang membawa status HTTP untuk ditampilkan kepada pengguna.
 *
 * Dipakai untuk 403 (tidak diizinkan), 404 (tidak ditemukan), 422 (validasi),
 * dan sejenisnya. Pesan yang dibawa aman untuk ditampilkan.
 */
class HttpException extends \RuntimeException
{
    /** @var array<string, mixed> */
    private array $context;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(int $status = 500, string $message = '', array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : 'Kesalahan pada permintaan', 0, $previous);
        $this->code = $status;
        $this->context = $context;
    }

    public function status(): int
    {
        return (int) $this->getCode();
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    public static function notFound(string $message = 'Data tidak ditemukan'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'Anda tidak memiliki izin untuk tindakan ini'): self
    {
        return new self(403, $message);
    }

    public static function unprocessable(string $message = 'Data tidak valid'): self
    {
        return new self(422, $message);
    }

    public static function badRequest(string $message = 'Permintaan tidak valid'): self
    {
        return new self(400, $message);
    }
}
