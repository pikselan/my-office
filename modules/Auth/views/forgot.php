<?php
/** Halaman permintaan tautan reset kata sandi. */
?>
<h2 class="mo-mb-8"><?= e(t('auth.forgot_title')) ?></h2>
<p class="mo-text-muted mo-text-sm mo-mb-16"><?= e(t('auth.forgot_subtitle')) ?></p>

<form method="post" action="<?= e(url('forgot-password')) ?>" class="mo-form" data-mo-form>
    <?= csrf_field() ?>
    <?= mo_input('email', t('auth.email'), '', ['type' => 'email', 'required' => true]) ?>
    <button type="submit" class="mo-btn mo-btn--primary mo-btn--block"><?= e(t('auth.forgot_button')) ?></button>
</form>

<div class="mo-auth__switch">
    <a href="<?= e(url('login')) ?>"><?= e(t('auth.back_to_login')) ?></a>
</div>
