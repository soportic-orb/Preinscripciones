<?php
/** Ajustes generales de plataforma. */
$active = 'settings';
$pay = $payments ?? [];
?>
<?php require VIEW_PATH . '/partials/admin_nav.php'; ?>

<h1><?= e(__('settings.general_title')) ?></h1>

<form method="post" action="<?= e(url('/gestion/sistema/ajustes')) ?>" class="card">
    <?= csrf_field() ?>
    <div class="row">
        <div class="form-group">
            <label><?= e(__('settings.default_locale')) ?></label>
            <select name="default_locale">
                <?php foreach ($locales as $loc): ?>
                    <option value="<?= e($loc) ?>" <?= ($settings['default_locale'] ?? 'es') === $loc ? 'selected' : '' ?>><?= e(\App\Core\I18n::nativeName($loc)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label><?= e(__('settings.data_retention')) ?></label>
            <input type="number" name="data_retention_days" value="<?= e($settings['data_retention_days'] ?? '0') ?>" min="0">
        </div>
    </div>

    <h3><?= e(__('settings.payment_methods')) ?></h3>
    <div class="form-group checkbox-row">
        <input type="checkbox" id="pay_stripe" name="pay_stripe" value="1" <?= ($pay['stripe'] ?? '') === '1' ? 'checked' : '' ?>>
        <label for="pay_stripe" style="font-weight:400"><?= e(__('settings.pay_stripe')) ?></label>
    </div>
    <div class="form-group checkbox-row">
        <input type="checkbox" id="pay_bizum" name="pay_bizum" value="1" <?= ($pay['bizum'] ?? '') === '1' ? 'checked' : '' ?>>
        <label for="pay_bizum" style="font-weight:400"><?= e(__('settings.pay_bizum')) ?></label>
    </div>
    <div class="form-group checkbox-row">
        <input type="checkbox" id="pay_transfer" name="pay_transfer" value="1" <?= ($pay['transfer'] ?? '') === '1' ? 'checked' : '' ?>>
        <label for="pay_transfer" style="font-weight:400"><?= e(__('settings.pay_transfer')) ?></label>
    </div>

    <button type="submit" class="btn btn-primary mt-4"><?= e(__('common.save')) ?></button>
</form>
