<?php
/** Panel de estudiante (base — se amplía en bloques posteriores). */
?>
<h1><?= e(__('dashboard.welcome', ['name' => $user?->name ?? ''])) ?></h1>
<p class="text-muted"><?= e(__('dashboard.student_intro')) ?></p>

<div class="card">
    <h3><?= e(__('nav.dashboard')) ?></h3>
    <p><?= e(__('dashboard.no_preinscriptions')) ?></p>
    <span class="badge"><?= e(__('dashboard.pending_module')) ?></span>
</div>
