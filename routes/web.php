<?php

declare(strict_types=1);

/**
 * Route antarmuka web.
 *
 * @var App\Core\App $moApp
 */

use App\Controllers\HomeController;

$moRouter = $moApp->router();

$moRouter->get('/', [HomeController::class, 'index'])->name('home');
$moRouter->get('/health', [HomeController::class, 'health'])->name('health');
