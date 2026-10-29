<?php
/**
 * Halaman masuk.
 *
 * @var string|null $title
 */
?>
<h2 class="mo-mb-8"><?= e(t('auth.login_title')) ?></h2>
<p class="mo-text-muted mo-text-sm mo-mb-16"><?= e(t('auth.login_subtitle')) ?></p>

<?php if (errors('_general') !== null): ?>
    <div class="mo-alert mo-alert--danger"><div><?= e((string) errors('_general')) ?></div></div>
<?php endif; ?>

<form method="post" action="<?= e(url('login')) ?>" class="mo-form" data-mo-form>
    <?= csrf_field() ?>

    <?= mo_input('email', t('auth.email'), '', ['type' => 'email', 'required' => true, 'autocomplete' => 'username', 'placeholder' => t('auth.field.email_placeholder')]) ?>
    <?= mo_input('password', t('auth.password'), '', ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>

    <div class="mo-flex-between">
        <label class="mo-check"><input type="checkbox" name="remember" value="1"> <span><?= e(t('auth.remember')) ?></span></label>
        <a href="<?= e(url('forgot-password')) ?>" class="mo-text-sm"><?= e(t('auth.forgot')) ?></a>
    </div>

    <button type="submit" class="mo-btn mo-btn--primary mo-btn--block"><?= e(t('auth.login_button')) ?></button>
</form>

<div class="mo-auth__switch">
    <?= e(t('auth.no_account')) ?> <a href="<?= e(url('register')) ?>"><?= e(t('auth.register_link')) ?></a>
</div>
