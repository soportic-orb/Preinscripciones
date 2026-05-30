<?php
/** Panel de gestión: accesos directos al proceso. */
$active = 'management';
?>
<?php require VIEW_PATH . '/partials/management_nav.php'; ?>

<h1><?= e(__('dashboard.management_title')) ?></h1>
<p class="text-muted"><?= e(__('dashboard.management_intro')) ?></p>

<div class="features">
    <a class="card" href="<?= e(url('/gestion/preinscripciones')) ?>" style="text-decoration:none">
        <h3><?= e(__('management.preinscriptions')) ?></h3>
        <p class="text-muted"><?= e(__('management.preinscriptions_intro')) ?></p>
    </a>
    <a class="card" href="<?= e(url('/gestion/cursos')) ?>" style="text-decoration:none">
        <h3><?= e(__('catalog.courses')) ?></h3>
        <p class="text-muted"><?= e(__('catalog.courses_intro')) ?></p>
    </a>
</div>
