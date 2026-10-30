<?php
/**
 * Pemilihan tenant bila akun tergabung di lebih dari satu tenant.
 *
 * @var list<array<string, mixed>> $tenants
 */
?>
<h2 class="mo-mb-8"><?= e(t('auth.select_tenant_title')) ?></h2>
<p class="mo-text-muted mo-text-sm mo-mb-16"><?= e(t('auth.select_tenant_subtitle')) ?></p>

<form method="post" action="<?= e(url('select-tenant')) ?>" class="mo-form" data-mo-form>
    <?= csrf_field() ?>

    <div class="mo-field">
        <?php foreach ($tenants as $index => $tenant): ?>
            <label class="mo-panel__item" style="border:1px solid var(--mo-border);margin-bottom:8px;cursor:pointer">
                <input type="radio" name="tenant_id" value="<?= e((string) $tenant['id']) ?>" <?= $index === 0 ? 'checked' : '' ?>>
                <span class="mo-panel__item-main">
                    <span class="mo-panel__item-title"><?= e((string) $tenant['name']) ?></span>
                    <span class="mo-panel__item-sub"><?= e((string) ($tenant['role_code'] ?? '')) ?></span>
                </span>
                <?= mo_status_badge((string) ($tenant['status'] ?? 'active')) ?>
            </label>
        <?php endforeach; ?>
        <?php if (errors('tenant_id') !== null): ?>
            <span class="mo-field__error"><?= e((string) errors('tenant_id')) ?></span>
        <?php endif; ?>
    </div>

    <button type="submit" class="mo-btn mo-btn--primary mo-btn--block"><?= e(t('auth.select_tenant_button')) ?></button>
</form>
