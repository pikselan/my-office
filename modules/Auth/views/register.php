<?php
/**
 * Halaman pendaftaran tenant baru.
 *
 * @var list<array<string, mixed>> $plans
 */
?>
<h2 class="mo-mb-8"><?= e(t('auth.register_title')) ?></h2>
<p class="mo-text-muted mo-text-sm mo-mb-16"><?= e(t('auth.register_subtitle')) ?></p>

<?php if (errors('_general') !== null): ?>
    <div class="mo-alert mo-alert--danger"><div><?= e((string) errors('_general')) ?></div></div>
<?php endif; ?>

<form method="post" action="<?= e(url('register')) ?>" class="mo-form" data-mo-form>
    <?= csrf_field() ?>

    <?= mo_input('owner_name', t('auth.field.name'), '', ['required' => true, 'placeholder' => t('auth.field.your_name_placeholder')]) ?>
    <?= mo_input('email', t('auth.email'), '', ['type' => 'email', 'required' => true, 'placeholder' => t('auth.field.email_placeholder')]) ?>
    <?= mo_input('tenant_name', t('auth.field.tenant_name'), '', ['required' => true, 'hint' => t('auth.field.tenant_hint')]) ?>
    <?= mo_input('company_name', t('auth.field.company_name'), '', ['required' => true, 'hint' => t('auth.field.company_hint')]) ?>

    <div class="mo-field">
        <label class="mo-field__label" for="plan_code"><?= e(t('auth.field.plan')) ?> <span>*</span></label>
        <select class="mo-select" id="plan_code" name="plan_code" required>
            <?php foreach ($plans as $plan): ?>
                <option value="<?= e($plan['code']) ?>"<?= old('plan_code', 'starter') === $plan['code'] ? ' selected' : '' ?>>
                    <?= e($plan['name']) ?> — <?= e(money($plan['monthly_price'], $plan['currency'])) ?><?= e(t('common.per_month')) ?>
                    (<?= (int) $plan['employee_limit'] === 0 ? e(t('common.employees_unlimited')) : e(t('common.employees_count', ['count' => number_format((int) $plan['employee_limit'], 0, ',', '.')])) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (errors('plan_code') !== null): ?>
            <span class="mo-field__error"><?= e((string) errors('plan_code')) ?></span>
        <?php endif; ?>
    </div>

    <?= mo_input('password', t('auth.password'), '', ['type' => 'password', 'required' => true, 'hint' => t('auth.field.password_hint', ['min' => (string) \App\Core\Config::int('security.password.min_length', 8)]) ] ) ?>
    <?= mo_input('password_confirmation', t('auth.field.password_confirm'), '', ['type' => 'password', 'required' => true]) ?>

    <label class="mo-check">
        <input type="checkbox" name="accept" value="1" required>
        <span><?= e(t('auth.tos')) ?></span>
    </label>
    <?php if (errors('accept') !== null): ?>
        <span class="mo-field__error"><?= e((string) errors('accept')) ?></span>
    <?php endif; ?>

    <button type="submit" class="mo-btn mo-btn--primary mo-btn--block"><?= e(t('auth.register_button')) ?></button>
</form>

<div class="mo-auth__switch">
    <?= e(t('auth.has_account')) ?> <a href="<?= e(url('login')) ?>"><?= e(t('auth.login_link')) ?></a>
</div>
