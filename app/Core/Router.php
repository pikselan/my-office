<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use ReflectionClass;
use ReflectionMethod;

/**
 * Router HTTP dengan dukungan grup, middleware, dan nama route.
 *
 * Pola URI:
 *   /employees/{id}        -> parameter alfanumerik bebas
 *   /employees/{id:number} -> hanya angka
 */
final class Router
{
    /** @var list<array{methods:list<string>,uri:string,pattern:string,handler:mixed,middleware:list<mixed>,name:?string}> */
    private array $routes = [];

    /** @var list<array{prefix:string,middleware:list<mixed>,name:string}> */
    private array $groupStack = [];

    /** @var array<string, string> */
    private array $named = [];

    private int $lastIndex = -1;

    public function get(string $uri, mixed $handler): self
    {
        return $this->match(['GET', 'HEAD'], $uri, $handler);
    }

    public function post(string $uri, mixed $handler): self
    {
        return $this->match(['POST'], $uri, $handler);
    }

    public function put(string $uri, mixed $handler): self
    {
        return $this->match(['PUT'], $uri, $handler);
    }

    public function patch(string $uri, mixed $handler): self
    {
        return $this->match(['PATCH'], $uri, $handler);
    }

    public function delete(string $uri, mixed $handler): self
    {
        return $this->match(['DELETE'], $uri, $handler);
    }

    public function any(string $uri, mixed $handler): self
    {
        return $this->match(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], $uri, $handler);
    }

    /**
     * @param list<string> $methods
     */
    public function match(array $methods, string $uri, mixed $handler): self
    {
        $prefix = '';
        $middleware = [];
        $namePrefix = '';

        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'];
            $middleware = array_merge($middleware, $group['middleware']);
            $namePrefix .= $group['name'];
        }

        $fullUri = $prefix . $uri;
        $fullUri = '/' . trim($fullUri, '/');
        if ($fullUri !== '/' ) {
            $fullUri = rtrim($fullUri, '/');
        }

        $this->routes[] = [
            'methods'    => $methods,
            'uri'        => $fullUri,
            'pattern'    => $this->compile($fullUri),
            'handler'    => $handler,
            'middleware' => $middleware,
            'name'       => null,
        ];

        $this->lastIndex = count($this->routes) - 1;
        $this->namePrefixes[$this->lastIndex] = $namePrefix;

        return $this;
    }

    /** @var array<int, string> */
    private array $namePrefixes = [];

    /**
     * Beri nama pada route terakhir yang didaftarkan.
     */
    public function name(string $name): self
    {
        if ($this->lastIndex < 0) {
            return $this;
        }

        $prefix = $this->namePrefixes[$this->lastIndex] ?? '';
        $full = $prefix . $name;

        $this->routes[$this->lastIndex]['name'] = $full;
        $this->named[$full] = $this->routes[$this->lastIndex]['uri'];

        return $this;
    }

    /**
     * Tambah middleware pada route terakhir yang didaftarkan.
     *
     * @param string|array<int, string> $middleware
     */
    public function middleware(string|array $middleware): self
    {
        if ($this->lastIndex < 0) {
            return $this;
        }

        $list = is_array($middleware) ? $middleware : [$middleware];
        $this->routes[$this->lastIndex]['middleware'] = array_merge(
            $this->routes[$this->lastIndex]['middleware'],
            $list
        );

        return $this;
    }

    /**
     * @param array{prefix?:string,middleware?:list<string>|string,name?:string} $attributes
     */
    public function group(array $attributes, Closure $callback): void
    {
        $middleware = $attributes['middleware'] ?? [];
        $middleware = is_array($middleware) ? $middleware : [$middleware];

        $this->groupStack[] = [
            'prefix'     => isset($attributes['prefix']) ? '/' . trim((string) $attributes['prefix'], '/') : '',
            'middleware' => $middleware,
            'name'       => (string) ($attributes['name'] ?? ''),
        ];

        $callback($this);

        array_pop($this->groupStack);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function routes(): array
    {
        return $this->routes;
    }

    public function hasRoute(string $name): bool
    {
        return isset($this->named[$name]);
    }

    /**
     * Bangun URI dari nama route.
     *
     * @param array<string, mixed> $params
     */
    public function url(string $name, array $params = []): string
    {
        $uri = $this->named[$name] ?? '/';

        foreach ($params as $key => $value) {
            $uri = str_replace('{' . $key . '}', (string) $value, $uri);
            $uri = str_replace('{' . $key . ':number}', (string) $value, $uri);
        }

        return $uri;
    }

    /**
     * Cocokkan request dengan route dan jalankan.
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = rtrim($request->path(), '/');
        if ($path === '') {
            $path = '/';
        }

        foreach ($this->routes as $route) {
            if (!in_array($method, $route['methods'], true)) {
                continue;
            }

            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            $request->setRouteParams($params);

            return $this->runPipeline($route, $request);
        }

        // Periksa apakah path dikenal dengan metode lain.
        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $path)) {
                throw new HttpException(405, 'Metode tidak diizinkan untuk endpoint ini');
            }
        }

        throw HttpException::notFound('Halaman tidak ditemukan');
    }

    /**
     * @param array<string, mixed> $route
     */
    private function runPipeline(array $route, Request $request): Response
    {
        $core = static function (Request $request) use ($route): Response {
            return self::callHandler($route['handler'], $request);
        };

        $pipeline = $core;

        foreach (array_reverse($route['middleware']) as $middleware) {
            $pipeline = static function (Request $request) use ($middleware, $pipeline): Response {
                if (is_string($middleware)) {
                    [$class, $arguments] = self::parseMiddleware($middleware);

                    /** @var MiddlewareInterface $instance */
                    $instance = $arguments === [] ? new $class() : new $class(...$arguments);

                    return $instance->handle($request, $pipeline);
                }

                if ($middleware instanceof Closure) {
                    return $middleware($request, $pipeline);
                }

                throw new \RuntimeException('Middleware tidak dikenal');
            };
        }

        return $pipeline($request);
    }

    /**
     * Dukung penulisan middleware dengan argumen: `Kelas:argumen1,argumen2`.
     *
     * @return array{0: string, 1: list<string>}
     */
    private static function parseMiddleware(string $middleware): array
    {
        if (!str_contains($middleware, ':')) {
            return [$middleware, []];
        }

        [$class, $arguments] = explode(':', $middleware, 2);

        return [trim($class), explode(',', $arguments)];
    }

    private static function callHandler(mixed $handler, Request $request): Response
    {
        if ($handler instanceof Closure) {
            $result = $handler($request);
            return self::normalize($result);
        }

        [$class, $method] = self::parseHandler($handler);

        if (!class_exists($class)) {
            throw new \RuntimeException('Controller tidak ditemukan: ' . $class);
        }

        $instance = new $class();

        if (!method_exists($instance, $method)) {
            throw new \RuntimeException('Metode controller tidak ditemukan: ' . $class . '@' . $method);
        }

        $reflection = new ReflectionMethod($instance, $method);
        if (!$reflection->isPublic()) {
            throw new HttpException(404, 'Halaman tidak ditemukan');
        }

        $arguments = [];
        $params = $request->routeParams();

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            if ($type instanceof \ReflectionNamedType && $type->getName() === Request::class) {
                $arguments[] = $request;
                continue;
            }

            if (array_key_exists($name, $params)) {
                $value = $params[$name];
                $arguments[] = ($type instanceof \ReflectionNamedType && $type->getName() === 'int')
                    ? (int) $value
                    : $value;
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->allowsNull()) {
                $arguments[] = null;
                continue;
            }

            $arguments[] = null;
        }

        $result = $reflection->invokeArgs($instance, $arguments);

        return self::normalize($result);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function parseHandler(mixed $handler): array
    {
        if (is_array($handler) && count($handler) === 2) {
            return [(string) $handler[0], (string) $handler[1]];
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            return explode('@', $handler, 2);
        }

        throw new \RuntimeException('Handler route tidak valid');
    }

    private static function normalize(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_string($result)) {
            return Response::html($result);
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        if ($result === null) {
            return Response::html('');
        }

        throw new \RuntimeException('Controller harus mengembalikan Response, string, atau array');
    }

    private function compile(string $uri): string
    {
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([a-zA-Z]+))?\}/',
            static function (array $matches): string {
                $name = $matches[1];
                $constraint = $matches[2] ?? 'default';

                return match ($constraint) {
                    'number' => '(?P<' . $name . '>[0-9]+)',
                    'slug'   => '(?P<' . $name . '>[a-z0-9-]+)',
                    default  => '(?P<' . $name . '>[^/]+)',
                };
            },
            $uri
        );

        if (!is_string($pattern)) {
            $pattern = $uri;
        }

        return '#^' . str_replace('#', '\#', $pattern) . '$#';
    }
}
