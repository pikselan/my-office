<?php

declare(strict_types=1);

/**
 * Bootstrap pengujian.
 *
 * Pengujian dijalankan pada SQLite in-memory agar:
 *  - setiap proses uji dimulai dari skema bersih (dari migrasi yang sama
 *    dengan produksi, diterjemahkan oleh SqliteDialect);
 *  - tidak bergantung pada server MySQL yang tidak tersedia di sandbox.
 *
 * Perhatikan: ini berarti uji integrasi memakai dialek SQLite. Uji terhadap
 * MySQL 8 tetap wajib dilakukan sebelum rilis (lihat docs/ENVIRONMENT.md).
 */

use App\Core\Config;
use App\Core\Database\Database;
use App\Core\Database\Migrator;
use App\Core\Database\Seeder;

$moBasePath = dirname(__DIR__);

$moApp = require $moBasePath . '/bootstrap/app.php';

Config::set('database.driver', 'sqlite');
Config::set('database.path', ':memory:');
Config::set('app.env', 'testing');

$pdo = Database::connect();

(new Migrator($moBasePath . '/database/migrations', $pdo))->run();
(new Seeder($moBasePath . '/database/seeds', $pdo))->run();

return $moApp;
