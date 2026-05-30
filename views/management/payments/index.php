<?php
/** Gestión de pagos: justificantes en revisión + facturas emitidas. */
use App\Models\Invoice;
use App\Models\Payment;

$active = 'payments';
$money = static fn ($n) => number_format((float) $n, 2, ',', '.') . ' €';
?>
<?php require VIEW_PATH . '/partials/management_nav.php'; ?>

<h1><?= e(__('payments.management')) ?></h1>

<div class="card">
    <h3><?= e(__('payments.in_review')) ?></h3>
    <?php if ($review === []): ?>
        <p class="text-muted" style="margin:0"><?= e(__('payments.none_in_review')) ?></p>
    <?php else: ?>
        <table class="data-table">
            <thead><tr><th><?= e(__('management.student')) ?></th><th><?= e(__('payments.method')) ?></th><th><?= e(__('payments.amount')) ?></th><th><?= e(__('payments.proof')) ?></th><th></th></tr></thead>
            <tbody>
                <?php foreach ($review as $p): ?>
                    <tr>
                        <td><?= e((string) $p['student_name']) ?><div class="field-hint"><?= e((string) $p['student_email']) ?></div></td>
                        <td><?= e((string) ($p['method'] ?? '')) ?><?php if ($p['reference']): ?><div class="field-hint"><?= e((string) $p['reference']) ?></div><?php endif; ?></td>
                        <td><?= $money(Payment::netAmount($p)) ?></td>
                        <td><?php if ($p['proof_path']): ?>—<?php endif; ?></td>
                        <td>
                            <div style="display:flex;gap:6px">
                                <form method="post" action="<?= e(url('/gestion/pagos/' . $p['id'] . '/validar')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="decision" value="approve">
                                    <button class="btn btn-outline btn-sm"><?= e(__('payments.validate')) ?></button>
                                </form>
                                <form method="post" action="<?= e(url('/gestion/pagos/' . $p['id'] . '/validar')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="decision" value="reject">
                                    <button class="btn btn-ghost btn-sm" style="color:var(--color-accent)"><?= e(__('payments.reject')) ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h3><?= e(__('billing.invoices')) ?></h3>
    <?php if ($invoices === []): ?>
        <p class="text-muted" style="margin:0"><?= e(__('billing.no_invoices')) ?></p>
    <?php else: ?>
        <table class="data-table">
            <thead><tr><th><?= e(__('billing.number')) ?></th><th><?= e(__('management.student')) ?></th><th><?= e(__('billing.date')) ?></th><th><?= e(__('invoices.total')) ?></th><th></th></tr></thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><?= e((string) $inv['full_number']) ?></td>
                        <td><?= e((string) $inv['student_name']) ?></td>
                        <td><?= e(substr((string) $inv['issued_at'], 0, 10)) ?></td>
                        <td><?= $money($inv['total']) ?></td>
                        <td>
                            <div style="display:flex;gap:6px;align-items:center">
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('/factura/' . $inv['id'])) ?>"><?= e(__('documents.download')) ?></a>
                                <?php if ($inv['type'] === Invoice::TYPE_INVOICE && $inv['payment_id']): ?>
                                    <form method="post" action="<?= e(url('/gestion/pagos/' . $inv['payment_id'] . '/reembolso')) ?>" onsubmit="return confirm('<?= e(__('payments.confirm_refund')) ?>')" style="display:flex;gap:4px">
                                        <?= csrf_field() ?>
                                        <input type="text" name="reason" placeholder="<?= e(__('documents.reason')) ?>" style="width:120px">
                                        <button class="btn btn-ghost btn-sm" style="color:var(--color-accent)"><?= e(__('payments.refund')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
