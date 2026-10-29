<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;

/**
 * Halaman sementara pada tahap fondasi.
 *
 * Akan digantikan oleh DashboardController pada batch 6–7.
 */
final class HomeController
{
    public function index(Request $request): Response
    {
        $body = '<!doctype html><html lang="id"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . e(Config::string('app.name', 'MO')) . '</title></head>'
            . '<body style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#F7F8FA;color:#111827;margin:0">'
            . '<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px">'
            . '<div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:32px;max-width:560px">'
            . '<div style="width:48px;height:48px;border-radius:10px;background:#6D28D9;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:20px">MO</div>'
            . '<h1 style="font-size:22px;margin:16px 0 8px">' . e(Config::string('app.name', 'MO (MyOffice)')) . '</h1>'
            . '<p style="color:#6B7280;line-height:1.6;margin:0 0 16px">Fondasi aplikasi aktif. Modul bisnis ditambahkan pada batch berikutnya.</p>'
            . '<p style="color:#9CA3AF;font-size:13px;margin:0">Lingkungan: ' . e(Config::string('app.env', 'production'))
            . ' · Driver: ' . e(Config::string('database.driver', 'mysql')) . '</p>'
            . '</div></div></body></html>';

        return Response::html($body);
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
