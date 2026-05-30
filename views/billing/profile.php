<?php
/** Datos fiscales del estudiante + listado de facturas. */
use App\Models\Invoice;

$p = $profile ?? [];
?>
<p><a href="<?= e(url('/panel')) ?>">&larr; <?= e(__('nav.dashboard')) ?></a></p>
<h1><?= e(__('billing.title')) ?></h1>

<div class="card">
    <h3><?= e(__('billing.fiscal_data')) ?></h3>
    <form method="post" action="<?= e(url('/panel/facturacion')) ?>">
        <?= csrf_field() ?>
        <div class="form-group checkbox-row">
            <input type="checkbox" id="is_company" name="is_company" value="1" <?= !empty($p['is_company']) ? 'checked' : '' ?>>
            <label for="is_company" style="font-weight:400"><?= e(__('billing.is_company')) ?></label>
        </div>
        <div class="row">
            <div class="form-group" style="flex:2"><label><?= e(__('billing.name')) ?></label><input type="text" name="name" value="<?= e($p['name'] ?? '') ?>" required></div>
            <div class="form-group"><label><?= e(__('billing.tax_id')) ?></label><input type="text" name="tax_id" value="<?= e($p['tax_id'] ?? '') ?>"></div>
        </div>
        <div class="form-group"><label><?= e(__('billing.address')) ?></label><input type="text" name="address" value="<?= e($p['address'] ?? '') ?>"></div>
        <div class="row">
            <div class="form-group"><label><?= e(__('billing.city')) ?></label><input type="text" name="city" value="<?= e($p['city'] ?? '') ?>"></div>
            <div class="form-group"><label><?= e(__('billing.postal_code')) ?></label><input type="text" name="postal_code" value="<?= e($p['postal_code'] ?? '') ?>"></div>
            <div class="form-group"><label><?= e(__('billing.country')) ?></label><input type="text" name="country" value="<?= e($p['country'] ?? '') ?>"></div>
        </div>
        <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
    </form>
</div>

<div class="card">
    <h3><?= e(__('billing.invoices')) ?></h3>
    <?php if ($invoices === []): ?>
        <p class="text-muted" style="margin:0"><?= e(__('billing.no_invoices')) ?></p>
    <?php else: ?>
        <table class="data-table">
            <thead><tr><th><?= e(__('billing.number')) ?></th><th><?= e(__('billing.date')) ?></th><th><?= e(__('invoices.total')) ?></th><th></th></tr></thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><?= e((string) $inv['full_number']) ?> <?php if ($inv['type'] === Invoice::TYPE_CREDIT): ?><span class="badge"><?= e(__('invoices.credit_note')) ?></span><?php endif; ?></td>
                        <td><?= e(substr((string) $inv['issued_at'], 0, 10)) ?></td>
                        <td><?= e(number_format((float) $inv['total'], 2, ',', '.')) ?> €</td>
                        <td><a class="btn btn-ghost btn-sm" href="<?= e(url('/factura/' . $inv['id'])) ?>"><?= e(__('documents.download')) ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
