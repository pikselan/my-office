<?php

/**
 * Layout utama aplikasi: sidebar, topbar, dan area konten.
 *
 * @var string $content
 * @var string $title
 */

use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\TenantContext;
use App\Core\Translator;

$user = Auth::user() ?? [];
$tenant = TenantContext::tenant();
$company = TenantContext::company();
$companies = TenantContext::companies();
$currentPath = request()?->path() ?? '/';
$locale = Translator::locale();
?>
<!doctype html>
<html lang="<?= e($locale) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
<title><?= e($title ?? '') ?> · <?= e(config('app.name')) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/css/mo.css')) ?>">
</head>
<body>
<div class="mo-sidebar-backdrop"></div>

<aside class="mo-sidebar" id="mo-sidebar">
    <div class="mo-sidebar__brand">
        <span class="mo-logo">MO</span>
        <span class="mo-sidebar__brand-text">
            <strong>MyOffice</strong>
            <span><?= e($tenant['name'] ?? 'MO') ?></span>
        </span>
    </div>

    <nav class="mo-sidebar__nav">
        <?php foreach (mo_menu($currentPath) as $group): ?>
            <div class="mo-nav-group">
                <div class="mo-nav-group__label"><?= e($group['label']) ?></div>
                <?php foreach ($group['items'] as $item): ?>
                    <a class="mo-nav-item<?= $item['active'] ? ' is-active' : '' ?>" href="<?= e(url($item['href'])) ?>">
                        <?= mo_icon($item['icon']) ?>
                        <span class="mo-nav-item__text"><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>
</aside>

<div class="mo-main">
    <header class="mo-topbar">
        <button type="button" class="mo-btn mo-btn--icon" data-mo-toggle="sidebar" aria-label="Menu">
            <?= mo_icon('menu', 20) ?>
        </button>

        <div class="mo-topbar__title mo-hide-mobile"><?= e($title ?? '') ?></div>
        <div class="mo-topbar__spacer"></div>

        <div class="mo-topbar__actions">
            <?php if (count($companies) > 1): ?>
                <div class="mo-dropdown">
                    <button type="button" class="mo-btn mo-btn--sm" data-mo-dropdown>
                        <?= mo_icon('building', 16) ?>
                        <span class="mo-hide-mobile"><?= e($company['name'] ?? t('common.select_company')) ?></span>
                        <?= mo_icon('chevron', 14) ?>
                    </button>
                    <div class="mo-dropdown__menu">
                        <div class="mo-dropdown__header"><?= e(t('common.company')) ?></div>
                        <?php foreach ($companies as $option): ?>
                            <form method="post" action="<?= e(url('company/switch')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="company_id" value="<?= e((string) $option['id']) ?>">
                                <button type="submit" class="mo-dropdown__item">
                                    <?= mo_icon('check', 16) ?>
                                    <span><?= e($option['name']) ?></span>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif ($company !== null): ?>
                <span class="mo-badge mo-hide-mobile"><?= e($company['name']) ?></span>
            <?php endif; ?>

            <div class="mo-dropdown">
                <button type="button" class="mo-btn mo-btn--sm" data-mo-dropdown>
                    <?= mo_icon('globe', 16) ?>
                    <span><?= e(strtoupper($locale)) ?></span>
                </button>
                <div class="mo-dropdown__menu">
                    <?php foreach (Translator::available() as $code): ?>
                        <form method="post" action="<?= e(url('locale')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="locale" value="<?= e($code) ?>">
                            <button type="submit" class="mo-dropdown__item">
                                <?= e(strtoupper($code)) ?> — <?= $code === 'id' ? 'Bahasa Indonesia' : 'English' ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mo-dropdown">
                <button type="button" class="mo-btn mo-btn--icon" data-mo-dropdown aria-label="Akun">
                    <?= mo_avatar((string) ($user['name'] ?? 'U'), null, ['size' => 'sm']) ?>
                </button>
                <div class="mo-dropdown__menu">
                    <div class="mo-dropdown__header">
                        <?= e($user['name'] ?? '') ?><br>
                        <span class="mo-text-subtle"><?= e($user['email'] ?? '') ?></span>
                    </div>
                    <div class="mo-dropdown__divider"></div>
                    <a class="mo-dropdown__item" href="<?= e(url('profile')) ?>"><?= mo_icon('users', 16) ?> <?= e(t('nav.profile')) ?></a>
                    <?php if (Authorization::isPlatformAdmin()): ?>
                        <a class="mo-dropdown__item" href="<?= e(url('platform')) ?>"><?= mo_icon('server', 16) ?> <?= e(t('nav.platform')) ?></a>
                    <?php endif; ?>
                    <div class="mo-dropdown__divider"></div>
                    <form method="post" action="<?= e(url('logout')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="mo-dropdown__item mo-dropdown__item--danger">
                            <?= mo_icon('logout', 16) ?> <?= e(t('nav.logout')) ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="mo-content">
        <?php if (($flashSuccess = \App\Core\Session::getFlash('success')) !== null): ?>
            <div class="mo-alert mo-alert--success" data-mo-flash-type="success" data-mo-flash="<?= e(is_string($flashSuccess) ? $flashSuccess : '') ?>">
                <div><?= e(is_string($flashSuccess) ? $flashSuccess : '') ?></div>
            </div>
        <?php endif; ?>
        <?php if (($flashError = \App\Core\Session::getFlash('error')) !== null): ?>
            <div class="mo-alert mo-alert--danger" data-mo-flash-type="danger" data-mo-flash="<?= e(is_string($flashError) ? $flashError : '') ?>">
                <div><?= e(is_string($flashError) ? $flashError : '') ?></div>
            </div>
        <?php endif; ?>
        <?php if (($flashWarning = \App\Core\Session::getFlash('warning')) !== null): ?>
            <div class="mo-alert mo-alert--warning"><div><?= e(is_string($flashWarning) ? $flashWarning : '') ?></div></div>
        <?php endif; ?>

        <?= $content ?? '' ?>
    </main>
</div>

<div class="mo-toasts"></div>
<?= mo_confirm_modal() ?>
<script src="<?= e(asset('assets/js/mo.js')) ?>"></script>
</body>
</html>
