<?php

declare(strict_types=1);

/**
 * Bootstrap aplikasi.
 *
 * Dipakai oleh:
 *   - public/index.php (request web)
 *   - bin/mo           (command line)
 */

$moBasePath = dirname(__DIR__);

require_once $moBasePath . '/app/Core/Autoload.php';

App\Core\Autoload::register($moBasePath);
require_once $moBasePath . '/app/Support/helpers.php';

$moApp = App\Core\App::boot($moBasePath);

// Route dimuat di sini agar tersedia baik untuk web maupun CLI (pengujian).
require_once $moBasePath . '/routes/web.php';
if (is_file($moBasePath . '/routes/api.php')) {
    require_once $moBasePath . '/routes/api.php';
}

return $moApp;
