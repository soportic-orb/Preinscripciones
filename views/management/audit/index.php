<?php
/** Visor de auditoría con filtros. */
$active = 'audit';
?>
<?php require VIEW_PATH . '/partials/management_nav.php'; ?>

<h1><?= e(__('audit.title')) ?></h1>
<p class="text-muted"><?= e(__('audit.intro')) ?> · <?= (int) $total ?> <?= e(__('audit.records')) ?></p>

<form method="get" action="<?= e(url('/gestion/auditoria')) ?>" class="card">
    <div class="row" style="align-items:flex-end">
        <div class="form-group"><label><?= e(__('audit.action')) ?></label><input type="text" name="action" value="<?= e($filters['action']) ?>"></div>
        <div class="form-group"><label><?= e(__('audit.actor')) ?></label><input type="text" name="actor" value="<?= e($filters['actor']) ?>" placeholder="email"></div>
        <div class="form-group"><label><?= e(__('audit.from')) ?></label><input type="date" name="from" value="<?= e($filters['from']) ?>"></div>
        <div class="form-group"><label><?= e(__('audit.to')) ?></label><input type="date" name="to" value="<?= e($filters['to']) ?>"></div>
        <div class="form-group"><button type="submit" class="btn btn-primary"><?= e(__('management.filter')) ?></button></div>
    </div>
</form>

<div class="card">
    <table class="data-table">
        <thead><tr><th><?= e(__('audit.date')) ?></th><th><?= e(__('audit.actor')) ?></th><th><?= e(__('audit.action')) ?></th><th><?= e(__('audit.entity')) ?></th><th>IP</th></tr></thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e((string) $r['created_at']) ?></td>
                    <td><?= e((string) ($r['actor_email'] ?? '—')) ?></td>
                    <td><code><?= e((string) $r['action']) ?></code></td>
                    <td><?= e(trim(($r['entity_type'] ?? '') . ' ' . ($r['entity_id'] ?? ''))) ?: '—' ?></td>
                    <td><?= e((string) ($r['ip'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?><tr><td colspan="5" class="text-muted"><?= e(__('audit.empty')) ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php if ($pages > 1): ?>
        <div style="display:flex;gap:8px;margin-top:12px;align-items:center">
            <?php for ($i = 1; $i <= min($pages, 15); $i++): ?>
                <a class="btn btn-ghost btn-sm <?= $i === $page ? 'active' : '' ?>"
                   href="<?= e(url('/gestion/auditoria?page=' . $i . '&action=' . urlencode($filters['action']) . '&actor=' . urlencode($filters['actor']))) ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
