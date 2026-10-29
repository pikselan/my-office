<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Authorization;
use App\Core\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Memeriksa permission tertentu sebelum controller dijalankan.
 *
 * Dipakai pada route: `->middleware('App\Middleware\PermissionMiddleware:employee.view')`.
 */
final class PermissionMiddleware implements MiddlewareInterface
{
    private string $permission;

    public function __construct(string $permission = '')
    {
        $this->permission = $permission;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->permission === '') {
            return $next($request);
        }

        if (Authorization::can($this->permission)) {
            return $next($request);
        }

        throw HttpException::forbidden();
    }
}
