<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Translator;
use Closure;

/**
 * Memastikan pengguna sudah masuk.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            if ($request->wantsJson()) {
                return Response::json([
                    'success' => false,
                    'message' => Translator::get('auth.login_title'),
                ], 401);
            }

            if ($request->isGet()) {
                Session::set('mo.intended', $request->uri());
            }

            Session::flash('warning', Translator::get('auth.login_subtitle'));

            return Response::redirect(url('login'));
        }

        return $next($request);
    }
}
