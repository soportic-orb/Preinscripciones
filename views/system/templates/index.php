<?php
/** Listado de plantillas de email por evento e idioma. */
use App\Core\I18n;

$active = 'templates';
$map = [];
foreach ($templates as $t) {
    $map[$t['event'] . '/' . $t['locale']] = $t;
}
?>
<?php require VIEW_PATH . '/partials/admin_nav.php'; ?>

<h1><?= e(__('templates.title')) ?></h1>
<p class="text-muted"><?= e(__('templates.intro')) ?></p>

<div class="card">
    <table class="data-table">
        <thead><tr><th><?= e(__('templates.event')) ?></th><?php foreach ($locales as $loc): ?><th><?= e(strtoupper($loc)) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
            <?php foreach ($events as $event): ?>
                <tr>
                    <td><code><?= e($event) ?></code></td>
                    <?php foreach ($locales as $loc): $t = $map[$event . '/' . $loc] ?? null; ?>
                        <td>
                            <a class="btn btn-ghost btn-sm" href="<?= e(url('/gestion/sistema/plantillas/' . $event . '/' . $loc)) ?>">
                                <?php if ($t && (int) $t['is_active'] === 1): ?>✎ <?= e(__('templates.custom')) ?><?php else: ?>+ <?= e(__('templates.default')) ?><?php endif; ?>
                            </a>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
