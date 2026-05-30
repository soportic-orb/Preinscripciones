<?php
/** Ajustes → Sistema: estado de plataforma y servidor. */
$yes = __('system.available');
$no = __('system.unavailable');
?>
<h1><?= e(__('system.title')) ?></h1>
<p class="text-muted"><?= e(__('system.intro')) ?></p>

<div class="card">
    <table class="info-table">
        <tr><th><?= e(__('system.app_version')) ?></th><td><?= e($info['app_version']) ?></td></tr>
        <tr><th><?= e(__('system.commit')) ?></th><td><?= e($info['commit'] ?? '—') ?></td></tr>
        <tr><th><?= e(__('system.php_version')) ?></th><td><?= e($info['php_version']) ?></td></tr>
        <tr><th><?= e(__('system.db_driver')) ?></th><td><?= e($info['db_driver']) ?></td></tr>
        <tr><th><?= e(__('system.git_available')) ?></th><td><?= $info['git_available'] ? e($yes) : e($no) ?></td></tr>
        <tr><th><?= e(__('system.mysqldump_available')) ?></th><td><?= $info['mysqldump_available'] ? e($yes) : e($no) ?></td></tr>
        <tr><th><?= e(__('system.can_exec')) ?></th><td><?= $info['can_exec'] ? e($yes) : e($no) ?></td></tr>
    </table>
</div>
