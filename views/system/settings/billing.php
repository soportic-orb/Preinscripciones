<?php
/** Ajustes de facturación: datos fiscales del centro, IVA/exención, IBAN, Bizum. */
$active = 'billing';
$b = $billing ?? [];
?>
<?php require VIEW_PATH . '/partials/admin_nav.php'; ?>

<h1><?= e(__('billing.settings_title')) ?></h1>

<form method="post" action="<?= e(url('/gestion/sistema/facturacion')) ?>" class="card">
    <?= csrf_field() ?>
    <h3><?= e(__('billing.academy_data')) ?></h3>
    <div class="form-group"><label><?= e(__('billing.academy_name')) ?></label><input type="text" name="academy_name" value="<?= e($b['academy_name'] ?? 'Institut d\'Estudis Mèdics') ?>"></div>
    <div class="row">
        <div class="form-group"><label><?= e(__('billing.academy_taxid')) ?></label><input type="text" name="academy_taxid" value="<?= e($b['academy_taxid'] ?? '') ?>"></div>
        <div class="form-group"><label><?= e(__('billing.invoice_series')) ?></label><input type="text" name="invoice_series" value="<?= e($b['invoice_series'] ?? 'A') ?>" maxlength="10"></div>
    </div>
    <div class="form-group"><label><?= e(__('billing.academy_address')) ?></label><textarea name="academy_address" rows="2"><?= e($b['academy_address'] ?? '') ?></textarea></div>

    <h3><?= e(__('billing.tax')) ?></h3>
    <div class="form-group checkbox-row">
        <input type="checkbox" id="vat_exempt" name="vat_exempt" value="1" <?= ($b['vat_exempt'] ?? '1') === '1' ? 'checked' : '' ?>>
        <label for="vat_exempt" style="font-weight:400"><?= e(__('billing.vat_exempt')) ?></label>
    </div>
    <div class="form-group" style="max-width:160px"><label><?= e(__('billing.tax_rate')) ?> (%)</label><input type="number" step="0.01" name="tax_rate" value="<?= e($b['tax_rate'] ?? '21') ?>"></div>

    <h3><?= e(__('billing.payment_data')) ?></h3>
    <div class="row">
        <div class="form-group" style="flex:2"><label><?= e(__('billing.iban')) ?></label><input type="text" name="iban" value="<?= e($b['iban'] ?? '') ?>"></div>
        <div class="form-group"><label><?= e(__('billing.bizum_number')) ?></label><input type="text" name="bizum_number" value="<?= e($b['bizum_number'] ?? '') ?>"></div>
    </div>

    <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
</form>
