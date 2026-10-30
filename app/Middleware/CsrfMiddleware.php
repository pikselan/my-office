<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Translator;
use Closure;

/**
 * Menolak request yang mengubah data tanpa token CSRF yang sah.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isWrite()) {
            return $next($request);
        }

        $token = Csrf::tokenFromRequest($request);

        if (Csrf::verify($token)) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return Response::json([
                'success' => false,
                'message' => Translator::get('message.request_failed'),
                'error' => 'csrf',
            ], 422);
        }

        \App\Core\Session::flash('error', Translator::get('message.request_failed'));

        return Response::redirect(url('/'));
    }
}
