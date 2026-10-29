<?php
/**
 * Profil akun.
 *
 * @var array<string, mixed> $user
 * @var list<array<string, mixed>> $roles
 * @var list<array<string, mixed>> $tenants
 */
?>
<div class="mo-page-head">
    <div>
        <h1><?= e(t('profile.title')) ?></h1>
        <div class="mo-page-head__subtitle"><?= e(t('profile.subtitle')) ?></div>
    </div>
</div>

<div class="mo-grid mo-grid--2">
    <div class="mo-card">
        <div class="mo-card__header"><div class="mo-card__title"><?= e(t('profile.account')) ?></div></div>
        <div class="mo-card__body">
            <form method="post" action="<?= e(url('profile')) ?>" class="mo-form" data-mo-form>
                <?= csrf_field() ?>
                <?= mo_input('name', t('auth.field.name'), $user['name'] ?? '', ['required' => true]) ?>
                <?= mo_input('email', t('auth.email'), $user['email'] ?? '', ['disabled' => true]) ?>
                <?= mo_input('phone', t('profile.phone'), $user['phone'] ?? '') ?>
                <?= mo_select('locale', t('profile.language'), ['id' => 'Bahasa Indonesia', 'en' => 'English'], $user['locale'] ?? 'id', ['required' => true]) ?>
                <?= mo_input('timezone', t('profile.timezone'), $user['timezone'] ?? 'Asia/Jakarta') ?>
                <div>
                    <?= mo_button(t('profile.save'), ['variant' => 'primary']) ?>
                </div>
            </form>
        </div>
    </div>

    <div class="mo-stack">
        <div class="mo-card">
            <div class="mo-card__header"><div class="mo-card__title"><?= e(t('profile.change_password')) ?></div></div>
            <div class="mo-card__body">
                <form method="post" action="<?= e(url('profile/password')) ?>" class="mo-form" data-mo-form>
                    <?= csrf_field() ?>
                    <?= mo_input('current_password', t('profile.current_password'), '', ['type' => 'password', 'required' => true]) ?>
                    <?= mo_input('password', t('profile.new_password'), '', ['type' => 'password', 'required' => true]) ?>
                    <?= mo_input('password_confirmation', t('profile.confirm_new_password'), '', ['type' => 'password', 'required' => true]) ?>
                    <?= mo_button(t('profile.change_password'), ['variant' => 'primary']) ?>
                </form>
            </div>
        </div>

        <div class="mo-card">
            <div class="mo-card__header"><div class="mo-card__title"><?= e(t('profile.roles')) ?></div></div>
            <?php if ($roles === []): ?>
                <div class="mo-card__body"><?= mo_empty_state(t('profile.roles_empty'), t('profile.roles_empty_hint')) ?></div>
            <?php else: ?>
                <table class="mo-table">
                    <thead><tr><th><?= e(t('profile.role')) ?></th><th><?= e(t('profile.scope')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($roles as $role): ?>
                        <tr>
                            <td><?= e($role['name']) ?><div class="mo-text-subtle mo-text-sm"><?= e($role['code']) ?></div></td>
                            <td><?= e($role['company']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
