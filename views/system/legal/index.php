<?php
/** Listado de textos legales y sus versiones. */
$active = 'legal';
?>
<?php require VIEW_PATH . '/partials/admin_nav.php'; ?>

<h1><?= e(__('legal.title')) ?></h1>
<p class="text-muted"><?= e(__('legal.intro')) ?></p>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th><?= e(__('legal.title')) ?></th>
                <th><?= e(__('legal.current_version')) ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($types as $type): ?>
                <tr>
                    <td><?= e(__('legal.types.' . $type)) ?></td>
                    <td>
                        <?php if (($current[$type] ?? 0) > 0): ?>
                            <span class="badge">v<?= (int) $current[$type] ?></span>
                        <?php else: ?>
                            <span class="text-muted"><?= e(__('legal.no_version')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="actions-cell">
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('/gestion/sistema/legales/' . $type . '/editar')) ?>"><?= e(__('legal.new_version')) ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($versions !== []): ?>
<div class="card">
    <h3><?= e(__('legal.version')) ?></h3>
    <table class="data-table">
        <thead><tr><th><?= e(__('legal.title')) ?></th><th><?= e(__('legal.version')) ?></th><th><?= e(__('legal.locales')) ?></th><th><?= e(__('legal.created_at')) ?></th></tr></thead>
        <tbody>
            <?php foreach ($versions as $v): ?>
                <tr>
                    <td><?= e(__('legal.types.' . $v['doc_type'])) ?></td>
                    <td>v<?= (int) $v['version'] ?></td>
                    <td><?= (int) $v['locales'] ?></td>
                    <td><?= e((string) $v['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
