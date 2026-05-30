<?php
/** Actualizaciones OTA por Git. */
$active = 'updates';
?>
<?php require VIEW_PATH . '/partials/admin_nav.php'; ?>

<h1><?= e(__('updates.title')) ?></h1>

<?php if (!$available): ?>
    <div class="card">
        <div class="alert alert-warn" style="background:var(--color-warning-bg);color:var(--color-warning);border:1px solid var(--color-warning);padding:12px;border-radius:5px">
            <?= e(__('updates.unavailable')) ?>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <table class="info-table">
            <tr><th><?= e(__('updates.branch')) ?></th><td><code><?= e($branch) ?></code></td></tr>
            <tr><th><?= e(__('updates.current_commit')) ?></th><td><code><?= e(substr((string) $commit, 0, 12)) ?></code></td></tr>
        </table>
        <a class="btn btn-outline" href="<?= e(url('/gestion/sistema/actualizaciones?check=1')) ?>"><?= e(__('updates.check')) ?></a>
    </div>

    <?php if ($check !== null): ?>
        <div class="card">
            <?php if (!empty($check['error'])): ?>
                <div class="alert" style="background:var(--color-error-bg);color:var(--color-error);padding:12px;border-radius:5px"><?= e($check['error']) ?></div>
            <?php elseif ($check['available']): ?>
                <h3><?= e(__('updates.new_available', ['n' => $check['behind']])) ?></h3>
                <div class="log" style="margin-bottom:12px"><?php foreach ($check['changelog'] as $c) {
                    echo e($c) . "\n";
                } ?></div>
                <form method="post" action="<?= e(url('/gestion/sistema/actualizaciones')) ?>" onsubmit="return confirm('<?= e(__('updates.confirm')) ?>')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary"><?= e(__('updates.update_now')) ?></button>
                </form>
            <?php else: ?>
                <div class="alert alert-ok" style="background:var(--color-success-bg);color:var(--color-success);padding:12px;border-radius:5px"><?= e(__('updates.up_to_date')) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <p class="text-muted"><?= e(__('updates.safeguards')) ?></p>
    </div>
<?php endif; ?>
