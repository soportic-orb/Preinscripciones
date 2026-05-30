<?php
/** Página de pago del estudiante: calendario de cobros, descuento y métodos. */
use App\Models\Course;
use App\Models\Payment;

$money = static fn ($n) => number_format((float) $n, 2, ',', '.') . ' €';
$pendingPayments = array_filter($payments, fn ($p) => in_array($p['status'], ['pendiente', 'rechazado'], true));
?>
<p><a href="<?= e(url('/panel/preinscripcion/' . $pre['id'])) ?>">&larr; <?= e(__('common.back')) ?></a></p>
<h1><?= e(__('payments.title')) ?></h1>
<div class="card" style="margin-bottom:16px">
    <strong><?= e(Course::localized($edition['course_title'] ?? '')) ?></strong> — <?= e((string) ($edition['name'] ?? '')) ?>
</div>

<!-- Calendario de cobros -->
<div class="card">
    <h3><?= e(__('payments.schedule')) ?></h3>
    <table class="data-table">
        <thead><tr><th><?= e(__('payments.concept')) ?></th><th><?= e(__('payments.due')) ?></th><th><?= e(__('payments.amount')) ?></th><th><?= e(__('payments.status')) ?></th></tr></thead>
        <tbody>
            <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?= e(__('payments.concept_' . $p['concept'])) ?><?= (int) $p['sequence'] > 1 ? ' #' . (int) $p['sequence'] : '' ?></td>
                    <td><?= e($p['due_date'] ? substr((string) $p['due_date'], 0, 10) : '—') ?></td>
                    <td>
                        <?= $money(Payment::netAmount($p)) ?>
                        <?php if ((float) $p['discount_amount'] > 0): ?><span class="field-hint"><?= e(__('payments.discount')) ?>: −<?= $money($p['discount_amount']) ?></span><?php endif; ?>
                    </td>
                    <td><span class="badge"><?= e(__('payments.statuses.' . $p['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($pendingPayments !== []): ?>
    <!-- Código de descuento -->
    <div class="card">
        <h3><?= e(__('payments.discount_code')) ?></h3>
        <form method="post" action="<?= e(url('/panel/preinscripcion/' . $pre['id'] . '/pago/descuento')) ?>" style="display:flex;gap:8px;max-width:360px">
            <?= csrf_field() ?>
            <input type="text" name="code" placeholder="<?= e(__('payments.discount_code')) ?>">
            <button type="submit" class="btn btn-outline"><?= e(__('payments.apply')) ?></button>
        </form>
    </div>

    <!-- Métodos de pago para el primer cobro pendiente -->
    <?php $next = array_values($pendingPayments)[0]; ?>
    <div class="card">
        <h3><?= e(__('payments.pay_now', ['amount' => $money(Payment::netAmount($next))])) ?></h3>

        <?php if (($payMethods['stripe'] ?? '1') !== '0'): ?>
            <form method="post" action="<?= e(url('/panel/preinscripcion/' . $pre['id'] . '/pago/stripe')) ?>" style="margin-bottom:12px">
                <?= csrf_field() ?>
                <input type="hidden" name="payment_id" value="<?= (int) $next['id'] ?>">
                <button type="submit" class="btn btn-primary"><?= e(__('payments.pay_card')) ?></button>
                <?php if (!$stripeEnabled): ?><span class="field-hint"><?= e(__('payments.simulated_mode')) ?></span><?php endif; ?>
            </form>
        <?php endif; ?>

        <?php if (($payMethods['transfer'] ?? '1') !== '0' || ($payMethods['bizum'] ?? '1') !== '0'): ?>
            <hr style="border:none;border-top:1px solid var(--color-border);margin:12px 0">
            <form method="post" action="<?= e(url('/panel/preinscripcion/' . $pre['id'] . '/pago/justificante')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="payment_id" value="<?= (int) $next['id'] ?>">
                <div class="row" style="align-items:flex-end">
                    <div class="form-group">
                        <label><?= e(__('payments.method')) ?></label>
                        <select name="method">
                            <?php if (($payMethods['transfer'] ?? '1') !== '0'): ?><option value="transfer"><?= e(__('settings.pay_transfer')) ?></option><?php endif; ?>
                            <?php if (($payMethods['bizum'] ?? '1') !== '0'): ?><option value="bizum">Bizum</option><?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1"><label><?= e(__('payments.reference')) ?></label><input type="text" name="reference"></div>
                    <div class="form-group"><label><?= e(__('payments.proof')) ?></label><input type="file" name="proof" required></div>
                    <div class="form-group"><button type="submit" class="btn btn-outline"><?= e(__('payments.submit_proof')) ?></button></div>
                </div>
                <div class="field-hint">
                    <?php if ($bank): ?><?= e(__('payments.iban')) ?>: <strong><?= e((string) $bank) ?></strong>. <?php endif; ?>
                    <?php if ($bizum): ?>Bizum: <strong><?= e((string) $bizum) ?></strong>.<?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card"><p class="text-muted" style="margin:0"><?= e(__('payments.all_paid')) ?></p></div>
<?php endif; ?>

<!-- FUNDAE -->
<div class="card">
    <h3><?= e(__('payments.fundae')) ?></h3>
    <p class="text-muted"><?= e(__('payments.fundae_intro')) ?></p>
    <form method="post" action="<?= e(url('/panel/preinscripcion/' . $pre['id'] . '/pago/fundae')) ?>">
        <?= csrf_field() ?>
        <div class="row">
            <div class="form-group"><label><?= e(__('payments.company_name')) ?></label><input type="text" name="company_name" value="<?= e($fundae['company_name'] ?? '') ?>"></div>
            <div class="form-group"><label><?= e(__('payments.company_cif')) ?></label><input type="text" name="company_cif" value="<?= e($fundae['company_cif'] ?? '') ?>"></div>
            <div class="form-group"><label><?= e(__('payments.contribution_account')) ?></label><input type="text" name="contribution_account" value="<?= e($fundae['contribution_account'] ?? '') ?>"></div>
        </div>
        <div class="row">
            <div class="form-group"><label><?= e(__('payments.worker_name')) ?></label><input type="text" name="worker_name" value="<?= e($fundae['worker_name'] ?? '') ?>"></div>
            <div class="form-group"><label><?= e(__('payments.worker_nif')) ?></label><input type="text" name="worker_nif" value="<?= e($fundae['worker_nif'] ?? '') ?>"></div>
        </div>
        <button type="submit" class="btn btn-ghost"><?= e(__('common.save')) ?></button>
    </form>
</div>
