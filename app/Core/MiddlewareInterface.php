<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

/**
 * Kontrak middleware.
 *
 * Middleware harus mengembalikan Response, baik dengan memanggil $next($request)
 * atau dengan menghentikan alur (mis. redirect login).
 */
interface MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response;
}
