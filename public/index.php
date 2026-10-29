<?php

declare(strict_types=1);

/**
 * Satu-satunya entry point HTTP.
 *
 * Document root Apache harus diarahkan ke direktori `public/`.
 */

define('MO_START', microtime(true));

/** @var App\Core\App $moApp */
$moApp = require __DIR__ . '/../bootstrap/app.php';

$moApp->run();
