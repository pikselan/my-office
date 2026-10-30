<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Representasi request HTTP.
 *
 * Seluruh nilai masukan dianggap tidak terpercaya. Pengambilan nilai
 * dilakukan melalui metode pada kelas ini agar konsisten (trim, konversi tipe).
 */
final class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private string $rawBody;

    /** @var array<string, mixed> */
    private array $query;
    /** @var array<string, mixed> */
    private array $post;
    /** @var array<string, mixed> */
    private array $cookies;
    /** @var array<string, mixed> */
    private array $server;
    /** @var array<string, mixed> */
    private array $files;
    /** @var array<string, mixed> */
    private array $routeParams = [];
    /** @var array<string, mixed> */
    private array $attributes = [];

    private ?string $id = null;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $server
     * @param array<string, mixed> $files
     */
    public function __construct(
        string $method,
        string $uri,
        array $query = [],
        array $post = [],
        array $cookies = [],
        array $server = [],
        array $files = [],
        string $rawBody = ''
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->path = '/' . trim((string) parse_url($uri, PHP_URL_PATH), '/');
        $this->query = $query;
        $this->post = $post;
        $this->cookies = $cookies;
        $this->server = $server;
        $this->files = $files;
        $this->rawBody = $rawBody;

        // Dukungan metode PUT/PATCH/DELETE dari formulir HTML.
        $override = $this->post['_method'] ?? null;
        if ($this->method === 'POST' && is_string($override) && in_array(strtoupper($override), ['PUT', 'PATCH', 'DELETE'], true)) {
            $this->method = strtoupper($override);
        }
    }

    public static function createFromGlobals(): self
    {
        $body = '';
        if (PHP_SAPI !== 'cli') {
            $body = (string) file_get_contents('php://input');
        }

        return new self(
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            $_GET,
            $_POST,
            $_COOKIE,
            $_SERVER,
            $_FILES,
            $body
        );
    }

    /**
     * Identitas unik request untuk penelusuran log.
     */
    public static function id(): string
    {
        static $id = null;
        if ($id === null) {
            $id = bin2hex(random_bytes(8));
        }
        return $id;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isWrite(): bool
    {
        return in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function fullUrlHost(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = (string) ($this->server['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host;
    }

    public function fullUrl(): string
    {
        $scheme = (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($this->server['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host . $this->uri;
    }

    /**
     * Nilai query string.
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Nilai query string sebagai string yang sudah di-trim.
     */
    public function queryString(string $key, string $default = ''): string
    {
        $value = $this->query[$key] ?? $default;
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /**
     * Nilai dari body (POST/PUT/PATCH) atau query string.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        if (array_key_exists($key, $this->query)) {
            return $this->query[$key];
        }

        $json = $this->json();
        return $json[$key] ?? $default;
    }

    /**
     * Nilai masukan sebagai string yang sudah di-trim.
     */
    public function str(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        if (is_array($value)) {
            return $default;
        }
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function intVal(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function boolVal(string $key, bool $default = false): bool
    {
        $value = $this->input($key, null);
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes', 'y'], true);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->post) || array_key_exists($key, $this->query);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json());
    }

    /**
     * Ambil hanya kunci tertentu (mencegah mass assignment).
     *
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $result = [];
        $source = $this->all();

        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                $result[$key] = $source[$key];
            }
        }

        return $result;
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        $data = $this->all();
        foreach ($keys as $key) {
            unset($data[$key]);
        }
        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        static $decoded = null;

        if ($decoded !== null) {
            return $decoded;
        }

        $decoded = [];

        if ($this->rawBody !== '') {
            $contentType = strtolower((string) $this->header('Content-Type'));
            if (str_contains($contentType, 'json')) {
                $data = json_decode($this->rawBody, true);
                if (is_array($data)) {
                    $decoded = $data;
                }
            }
        }

        return $decoded;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;
        return $value !== null ? (string) $value : $default;
    }

    public function ip(): string
    {
        $forwarded = $this->header('X-Forwarded-For');
        if ($forwarded !== null && $forwarded !== '') {
            return trim(explode(',', $forwarded)[0]);
        }
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    /**
     * Berkas yang diunggah, dinormalisasi menjadi daftar.
     *
     * @return array<int, array{name:string,type:string,tmp_name:string,error:int,size:int}>
     */
    public function files(string $key): array
    {
        $files = $this->files[$key] ?? null;
        if ($files === null) {
            return [];
        }

        $isMultiple = is_array($files) && isset($files['name']) && is_array($files['name']);
        $list = $isMultiple ? self::normalizeFiles($files) : [$files];

        $result = [];
        foreach ($list as $file) {
            if (!is_array($file)) {
                continue;
            }
            $result[] = [
                'name' => (string) ($file['name'] ?? ''),
                'type' => (string) ($file['type'] ?? ''),
                'tmp_name' => (string) ($file['tmp_name'] ?? ''),
                'error' => (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($file['size'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function cookies(): array
    {
        return $this->cookies;
    }

    /**
     * @return array<string, mixed>
     */
    public function server(): array
    {
        return $this->server;
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        $accept = (string) ($this->header('Accept') ?? '');
        return $this->isAjax() || str_contains($accept, 'application/json');
    }

    public function isSecure(): bool
    {
        if (($this->server['HTTPS'] ?? 'off') === 'on') {
            return true;
        }
        return strtolower((string) $this->header('X-Forwarded-Proto')) === 'https';
    }

    /**
     * Parameter dari pola route.
     *
     * @return array<string, mixed>
     */
    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function routeParam(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * Data tambahan yang ditempelkan middleware pada request.
     */
    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }
}
