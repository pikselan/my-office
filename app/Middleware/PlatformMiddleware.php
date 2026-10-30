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
 * Membatasi halaman administrasi platform.
 */
final class PlatformMiddleware implements MiddlewareInterface
{
    private string $permission;

    public function __construct(string $permission = 'platform.tenant.view')
    {
        $this->permission = $permission;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!Authorization::isPlatformAdmin()) {
            throw HttpException::forbidden();
        }

        return $next($request);
    }
}
