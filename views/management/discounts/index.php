<?php
/** Listado de descuentos/becas/códigos. */
$active = 'discounts';
?>
<?php require VIEW_PATH . '/partials/management_nav.php'; ?>

<div class="toolbar">
    <h1><?= e(__('discounts.title')) ?></h1>
    <a class="btn btn-primary" href="<?= e(url('/gestion/descuentos/nuevo')) ?>"><?= e(__('discounts.new')) ?></a>
</div>

<div class="card">
    <?php if ($discounts === []): ?>
        <p class="text-muted" style="margin:0"><?= e(__('discounts.none')) ?></p>
    <?php else: ?>
        <table class="data-table">
            <thead><tr><th><?= e(__('discounts.name')) ?></th><th><?= e(__('discounts.code')) ?></th><th><?= e(__('discounts.type')) ?></th><th><?= e(__('discounts.value')) ?></th><th><?= e(__('discounts.uses')) ?></th><th><?= e(__('fields.active')) ?></th></tr></thead>
            <tbody>
                <?php foreach ($discounts as $d): ?>
                    <tr>
                        <td><?= e((string) $d['name']) ?></td>
                        <td><?php if ($d['code']): ?><code><?= e((string) $d['code']) ?></code><?php else: ?>—<?php endif; ?></td>
                        <td><?= e(__('discounts.types.' . $d['type'])) ?></td>
                        <td><?= $d['type'] === 'percent' ? e((string) (float) $d['value']) . ' %' : e(number_format((float) $d['value'], 2, ',', '.')) . ' €' ?></td>
                        <td><?= (int) $d['used_count'] ?><?= (int) $d['max_uses'] > 0 ? ' / ' . (int) $d['max_uses'] : '' ?></td>
                        <td><?= (int) $d['is_active'] === 1 ? '✓' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
