<?php
/**
 * Beranda.
 *
 * @var array<string, mixed>|null $company
 * @var list<array{label: string, value: int|string, hint: string}> $stats
 * @var list<array{name: string, date: string}> $birthdays
 * @var list<array<string, mixed>> $activities
 */
?>
<div class="mo-page-head">
    <div>
        <h1><?= e(t('dashboard.title')) ?><?php if ($company !== null): ?> — <?= e((string) $company['name']) ?><?php endif; ?></h1>
        <div class="mo-page-head__subtitle"><?= e(t('dashboard.subtitle')) ?></div>
    </div>
</div>

<div class="mo-stat-grid mo-mb-16">
    <?php foreach ($stats as $stat): ?>
        <div class="mo-card mo-stat">
            <span class="mo-stat__label"><?= e(t($stat['label'])) ?></span>
            <span class="mo-stat__value"><?= e(number_format((int) $stat['value'], 0, ',', '.')) ?></span>
            <span class="mo-stat__hint"><?= e(t($stat['hint'])) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<div class="mo-grid mo-grid--2">
    <div class="mo-card">
        <div class="mo-card__header"><div class="mo-card__title"><?= e(t('dashboard.birthdays')) ?></div></div>
        <div class="mo-card__body">
            <?php if ($birthdays === []): ?>
                <?= mo_empty_state(t('dashboard.birthdays_empty'), t('dashboard.birthdays_empty_hint')) ?>
            <?php else: ?>
                <table class="mo-table">
                    <tbody>
                    <?php foreach ($birthdays as $birthday): ?>
                        <tr>
                            <td style="width:44px"><?= mo_avatar($birthday['name'], null, ['size' => 'sm']) ?></td>
                            <td><?= e($birthday['name']) ?></td>
                            <td class="mo-text-right mo-text-muted"><?= e($birthday['date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="mo-card">
        <div class="mo-card__header"><div class="mo-card__title"><?= e(t('dashboard.activities')) ?></div></div>
        <div class="mo-card__body">
            <?php if ($activities === []): ?>
                <?= mo_empty_state(t('dashboard.activities_empty'), t('dashboard.activities_empty_hint')) ?>
            <?php else: ?>
                <table class="mo-table mo-table--compact">
                    <tbody>
                    <?php foreach ($activities as $activity): ?>
                        <?php $activity = (array) $activity; ?>
                        <tr>
                            <td class="mo-font-mono"><?= e((string) ($activity['action'] ?? '')) ?></td>
                            <td class="mo-text-right mo-text-muted mo-text-sm"><?= e(format_date((string) ($activity['created_at'] ?? ''), 'd M H:i')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
