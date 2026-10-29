<?php

/**
 * Layout halaman autentikasi.
 *
 * @var string $content
 * @var string $title
 */

use App\Core\Csrf;
use App\Core\Translator;

$locale = Translator::locale();
?>
<!doctype html>
<html lang="<?= e($locale) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
<title><?= e($title ?? config('app.name')) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/css/mo.css')) ?>">
</head>
<body>
<div class="mo-auth">
    <div class="mo-auth__aside">
        <div>
            <span class="mo-logo" style="width:44px;height:44px;font-size:17px">MO</span>
        </div>
        <h2><?= e(t('app.tagline')) ?></h2>
        <p><?= e(t('app.description')) ?></p>
        <div class="mo-auth__features">
            <div class="mo-auth__feature"><span>✓</span><span><?= e(t('auth.feature.multi_company')) ?></span></div>
            <div class="mo-auth__feature"><span>✓</span><span><?= e(t('auth.feature.organization')) ?></span></div>
            <div class="mo-auth__feature"><span>✓</span><span><?= e(t('auth.feature.access')) ?></span></div>
            <div class="mo-auth__feature"><span>✓</span><span><?= e(t('auth.feature.isolation')) ?></span></div>
        </div>
    </div>

    <div class="mo-auth__main">
        <div class="mo-auth__card">
            <div class="mo-auth__brand">
                <span class="mo-logo">MO</span>
                <div>
                    <strong><?= e(config('app.name')) ?></strong>
                    <div class="mo-text-subtle mo-text-sm"><?= e(t('app.tagline')) ?></div>
                </div>
            </div>

            <?php if (($flashError = \App\Core\Session::getFlash('error')) !== null): ?>
                <div class="mo-alert mo-alert--danger"><div><?= e(is_string($flashError) ? $flashError : '') ?></div></div>
            <?php endif; ?>
            <?php if (($flashSuccess = \App\Core\Session::getFlash('success')) !== null): ?>
                <div class="mo-alert mo-alert--success"><div><?= e(is_string($flashSuccess) ? $flashSuccess : '') ?></div></div>
            <?php endif; ?>

            <div class="mo-card">
                <div class="mo-card__body">
                    <?= $content ?? '' ?>
                </div>
            </div>

            <div class="mo-auth__switch">
                <?php foreach (Translator::available() as $code): ?>
                    <form method="post" action="<?= e(url('locale')) ?>" style="display:inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="locale" value="<?= e($code) ?>">
                        <button type="submit" class="mo-btn mo-btn--ghost mo-btn--sm"><?= e(strtoupper($code)) ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<div class="mo-toasts"></div>
<script src="<?= e(asset('assets/js/mo.js')) ?>"></script>
</body>
</html>
