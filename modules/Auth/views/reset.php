<?php
/**
 * Halaman pengaturan ulang kata sandi.
 *
 * @var string $token
 */
?>
<h2 class="mo-mb-8"><?= e(t('auth.reset.title')) ?></h2>
<p class="mo-text-muted mo-text-sm mo-mb-16"><?= e(t('auth.reset_subtitle')) ?></p>

<form method="post" action="<?= e(url('reset-password')) ?>" class="mo-form" data-mo-form>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <?= mo_input('password', t('profile.new_password'), '', ['type' => 'password', 'required' => true]) ?>
    <?= mo_input('password_confirmation', t('profile.confirm_new_password'), '', ['type' => 'password', 'required' => true]) ?>
    <button type="submit" class="mo-btn mo-btn--primary mo-btn--block"><?= e(t('auth.reset_button')) ?></button>
</form>
