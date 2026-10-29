<?php

declare(strict_types=1);

/**
 * Route antarmuka web.
 *
 * @var App\Core\App $moApp
 */

use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\ProfileController;
use App\Middleware\CsrfMiddleware;
use Modules\Auth\Controllers\AuthController;

$moRouter = $moApp->router();

$moRouter->get('/', [HomeController::class, 'index'])->name('home');
$moRouter->get('/health', [HomeController::class, 'health'])->name('health');

/* ---------------------------------------------------------- Autentikasi -- */

$moRouter->get('/login', [AuthController::class, 'showLogin'])->name('auth.login');
$moRouter->post('/login', [AuthController::class, 'login'])->middleware(CsrfMiddleware::class);
$moRouter->get('/register', [AuthController::class, 'showRegister'])->name('auth.register');
$moRouter->post('/register', [AuthController::class, 'register'])->middleware(CsrfMiddleware::class);
$moRouter->post('/logout', [AuthController::class, 'logout'])->name('auth.logout')->middleware(CsrfMiddleware::class);

$moRouter->get('/verify-email/{token}', [AuthController::class, 'verifyEmail'])->name('auth.verify');

$moRouter->get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('auth.forgot');
$moRouter->post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware(CsrfMiddleware::class);
$moRouter->get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('auth.reset');
$moRouter->post('/reset-password', [AuthController::class, 'resetPassword'])->middleware(CsrfMiddleware::class);

/* ------------------------------------------------- Preferensi & konteks -- */

$moRouter->post('/locale', [AuthController::class, 'switchLocale'])->name('locale.switch')->middleware(CsrfMiddleware::class);
$moRouter->post('/company/switch', [AuthController::class, 'switchCompany'])->name('company.switch')->middleware(CsrfMiddleware::class);

/* --------------------------------------------------------- Terlindungi -- */

$moRouter->group(['middleware' => [\App\Middleware\AuthMiddleware::class, \App\Middleware\CsrfMiddleware::class]], function () use ($moRouter): void {
    $moRouter->get('/select-tenant', [AuthController::class, 'showSelectTenant'])->name('auth.select-tenant');
    $moRouter->post('/select-tenant', [AuthController::class, 'selectTenant']);
});

/* --------------------------------------------------- Butuh tenant aktif -- */

$moRouter->group(['middleware' => [
    \App\Middleware\AuthMiddleware::class,
    \App\Middleware\CsrfMiddleware::class,
    \App\Middleware\TenantMiddleware::class,
]], function () use ($moRouter): void {
    $moRouter->get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    $moRouter->get('/profile', [ProfileController::class, 'index'])->name('profile');
    $moRouter->post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    $moRouter->post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});
