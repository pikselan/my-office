<?php

declare(strict_types=1);

/**
 * Route API internal (dipakai oleh JavaScript aplikasi).
 *
 * @var App\Core\App $moApp
 */

$moRouter = $moApp->router();

$moRouter->group(['prefix' => '/api', 'name' => 'api.'], static function ($router): void {
    $router->get('/ping', static function (App\Core\Request $request): App\Core\Response {
        return App\Core\Response::json([
            'success' => true,
            'data' => ['pong' => true, 'time' => now()],
            'request_id' => App\Core\Request::id(),
        ]);
    })->name('ping');
});
