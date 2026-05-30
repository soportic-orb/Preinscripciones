<?php
/** Listado de preinscripciones con filtros para gestión. */
use App\Models\Course;
use App\Services\PreinscriptionStatus;

$active = 'preinscriptions';
?>
<?php require VIEW_PATH . '/partials/management_nav.php'; ?>

<h1><?= e(__('management.preinscriptions')) ?></h1>

<form method="get" action="<?= e(url('/gestion/preinscripciones')) ?>" class="card">
    <div class="row" style="align-items:flex-end">
        <div class="form-group" style="flex:1"><label><?= e(__('management.search')) ?></label><input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('management.search_ph')) ?>"></div>
        <div class="form-group">
            <label><?= e(__('management.status')) ?></label>
            <select name="status">
                <option value="">—</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(PreinscriptionStatus::label($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label><?= e(__('catalog.edition')) ?></label>
            <select name="edition_id">
                <option value="">—</option>
                <?php foreach ($editions as $ed): ?>
                    <option value="<?= (int) $ed['id'] ?>" <?= (string) $filters['edition_id'] === (string) $ed['id'] ? 'selected' : '' ?>><?= e((string) $ed['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><button type="submit" class="btn btn-primary"><?= e(__('management.filter')) ?></button></div>
    </div>
</form>

<div class="card">
    <?php if ($rows === []): ?>
        <p class="text-muted" style="margin:0"><?= e(__('management.no_results')) ?></p>
    <?php else: ?>
        <table class="data-table">
            <thead><tr><th><?= e(__('catalog.course')) ?></th><th><?= e(__('catalog.edition')) ?></th><th><?= e(__('management.student')) ?></th><th><?= e(__('management.status')) ?></th><th></th></tr></thead>
            <tbody>
                <?php foreach ($rows as $p): ?>
                    <tr>
                        <td><?= e(Course::localized($p['course_title'] ?? '')) ?></td>
                        <td><?= e((string) $p['edition_name']) ?></td>
                        <td><?= e((string) $p['student_name']) ?><div class="field-hint"><?= e((string) $p['student_email']) ?></div></td>
                        <td><span class="badge"><?= e(PreinscriptionStatus::label((string) $p['status'])) ?></span></td>
                        <td><a class="btn btn-ghost btn-sm" href="<?= e(url('/gestion/preinscripciones/' . $p['id'])) ?>"><?= e(__('common.view')) ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
