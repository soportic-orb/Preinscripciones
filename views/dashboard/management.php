<?php
/** Panel de gestión (base — se amplía con catálogo, preinscripciones, etc.). */
use App\Core\Rbac;
?>
<h1><?= e(__('dashboard.management_title')) ?></h1>
<p class="text-muted"><?= e(__('dashboard.management_intro')) ?></p>

<div class="card">
    <table class="info-table">
        <tr>
            <th><?= e(__('dashboard.welcome', ['name' => ''])) ?></th>
            <td><?= e($user?->name ?? '') ?></td>
        </tr>
        <tr>
            <th><?= e(__('dashboard.role')) ?></th>
            <td><span class="badge"><?= e($user?->role ?? '') ?></span></td>
        </tr>
    </table>
    <?php if ($user !== null && Rbac::isAdmin($user)): ?>
        <p class="mt-4"><a class="btn btn-outline" href="<?= e(url('/gestion/sistema')) ?>"><?= e(__('nav.system')) ?></a></p>
    <?php endif; ?>
</div>

<div class="card">
    <span class="badge"><?= e(__('dashboard.pending_module')) ?></span>
</div>
