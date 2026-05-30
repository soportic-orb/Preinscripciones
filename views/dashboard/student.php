<?php
/** Panel de estudiante: listado de preinscripciones + accesos. */
use App\Models\Course;
use App\Services\PreinscriptionStatus;

$preinscriptions = $preinscriptions ?? [];
?>
<div class="toolbar">
    <div>
        <h1><?= e(__('dashboard.welcome', ['name' => $user?->name ?? ''])) ?></h1>
        <p class="text-muted" style="margin:0"><?= e(__('dashboard.student_intro')) ?></p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('/preinscripcion')) ?>"><?= e(__('catalog.new_preinscription')) ?></a>
</div>

<div class="card">
    <h3><?= e(__('dashboard.my_preinscriptions')) ?></h3>
    <?php if ($preinscriptions === []): ?>
        <p class="text-muted"><?= e(__('dashboard.no_preinscriptions')) ?></p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th><?= e(__('catalog.course')) ?></th>
                    <th><?= e(__('catalog.edition')) ?></th>
                    <th><?= e(__('management.status')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($preinscriptions as $p): ?>
                    <tr>
                        <td><?= e(Course::localized($p['course_title'] ?? '')) ?></td>
                        <td><?= e((string) $p['edition_name']) ?></td>
                        <td><span class="badge"><?= e(PreinscriptionStatus::label((string) $p['status'])) ?></span></td>
                        <td>
                            <?php if ($p['status'] === PreinscriptionStatus::BORRADOR): ?>
                                <a class="btn btn-outline btn-sm" href="<?= e(url('/preinscripcion/' . $p['id'] . '/paso/' . (int) $p['wizard_step'])) ?>"><?= e(__('preinscription.resume')) ?></a>
                            <?php else: ?>
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('/panel/preinscripcion/' . $p['id'])) ?>"><?= e(__('common.view')) ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h3><?= e(__('rgpd.title')) ?></h3>
    <p class="text-muted"><?= e(__('rgpd.intro')) ?></p>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
        <a class="btn btn-outline btn-sm" href="<?= e(url('/panel/exportar-datos')) ?>"><?= e(__('rgpd.export')) ?></a>
        <form method="post" action="<?= e(url('/panel/solicitar-supresion')) ?>" onsubmit="return confirm('<?= e(__('rgpd.confirm_deletion')) ?>')">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--color-accent)"><?= e(__('rgpd.request_deletion')) ?></button>
        </form>
    </div>
</div>
