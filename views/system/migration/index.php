<?php
/** Migración guiada: exportar paquete. */
$active = 'migration';
?>
<?php require VIEW_PATH . '/partials/admin_nav.php'; ?>

<h1><?= e(__('migration.title')) ?></h1>
<p class="text-muted"><?= e(__('migration.intro')) ?></p>

<div class="card">
    <h3><?= e(__('migration.export_title')) ?></h3>
    <p><?= e(__('migration.export_body')) ?></p>
    <form method="post" action="<?= e(url('/gestion/sistema/migracion/exportar')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary"><?= e(__('migration.export_btn')) ?></button>
    </form>
</div>

<div class="card">
    <h3><?= e(__('migration.import_title')) ?></h3>
    <p class="text-muted"><?= e(__('migration.import_body')) ?></p>
</div>
