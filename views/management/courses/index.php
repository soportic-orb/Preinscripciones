<?php
/** Listado de cursos del catálogo. */
use App\Models\Course;
use App\Models\CourseEdition;

$active = 'courses';
?>
<?php require VIEW_PATH . '/partials/management_nav.php'; ?>

<div class="toolbar">
    <h1><?= e(__('catalog.courses')) ?></h1>
    <a class="btn btn-primary" href="<?= e(url('/gestion/cursos/nuevo')) ?>"><?= e(__('catalog.new_course')) ?></a>
</div>

<div class="card">
    <?php if ($courses === []): ?>
        <p class="text-muted" style="margin:0"><?= e(__('catalog.no_courses')) ?></p>
    <?php else: ?>
        <table class="data-table">
            <thead><tr><th><?= e(__('catalog.code')) ?></th><th><?= e(__('catalog.course')) ?></th><th><?= e(__('catalog.type')) ?></th><th><?= e(__('catalog.editions')) ?></th><th><?= e(__('fields.active')) ?></th><th></th></tr></thead>
            <tbody>
                <?php foreach ($courses as $c): ?>
                    <tr>
                        <td><code><?= e((string) $c['code']) ?></code></td>
                        <td><?= e(Course::localized($c['title'])) ?></td>
                        <td><?= e(__('catalog.types_label.' . $c['course_type'])) ?></td>
                        <td><?= count(CourseEdition::forCourse((int) $c['id'])) ?></td>
                        <td><?= (int) $c['is_active'] === 1 ? '✓' : '—' ?></td>
                        <td class="actions-cell">
                            <a class="btn btn-ghost btn-sm" href="<?= e(url('/gestion/cursos/' . $c['id'] . '/editar')) ?>"><?= e(__('fields.edit')) ?></a>
                            <a class="btn btn-ghost btn-sm" href="<?= e(url('/gestion/cursos/' . $c['id'] . '/ediciones/nueva')) ?>"><?= e(__('catalog.new_edition')) ?></a>
                        </td>
                    </tr>
                    <?php foreach (CourseEdition::forCourse((int) $c['id']) as $ed): ?>
                        <tr>
                            <td></td>
                            <td colspan="2" style="padding-left:24px">↳ <?= e((string) $ed['name']) ?></td>
                            <td><span class="badge"><?= e(__('catalog.statuses.' . $ed['status'])) ?></span></td>
                            <td></td>
                            <td><a class="btn btn-ghost btn-sm" href="<?= e(url('/gestion/ediciones/' . $ed['id'] . '/editar')) ?>"><?= e(__('fields.edit')) ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
