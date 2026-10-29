<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;
use App\Core\Translator;
use Closure;

/**
 * Memastikan konteks tenant tersedia sebelum controller berjalan.
 *
 * Bila pengguna belum memilih tenant, ia diarahkan ke pemilihan tenant.
 */
final class TenantMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        if (TenantContext::id() === null || TenantContext::tenant() === null) {
            if ($request->wantsJson()) {
                return Response::json([
                    'success' => false,
                    'message' => Translator::get('message.forbidden'),
                ], 409);
            }

            return Response::redirect(url('select-tenant'));
        }

        return $next($request);
    }
}
