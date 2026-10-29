<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;

/**
 * Halaman awal: mengarahkan ke beranda bila sudah masuk, ke login bila belum.
 */
final class HomeController
{
    public function index(Request $request): Response
    {
        if (Auth::check()) {
            return redirect(TenantContext::id() !== null ? 'dashboard' : 'select-tenant');
        }

        return redirect('login');
    }

    /**
     * Endpoint pemeriksaan kesehatan untuk pemantauan dan smoke test.
     */
    public function health(Request $request): Response
    {
        return Response::json([
            'success' => true,
            'data' => [
                'app'      => Config::string('app.name'),
                'env'      => Config::string('app.env'),
                'timezone' => Config::string('app.timezone'),
                'utc_time' => now(),
                'php'      => PHP_VERSION,
                'driver'   => Config::string('database.driver'),
            ],
            'request_id' => Request::id(),
        ]);
    }
}
