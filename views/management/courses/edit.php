<?php
/** Alta/edición de curso (textos multiidioma). */
use App\Models\Course;

$active = 'courses';
$isEdit = $course !== null;
$action = $isEdit ? url('/gestion/cursos/' . $course['id']) : url('/gestion/cursos');
$title = $isEdit ? (json_decode((string) $course['title'], true) ?: []) : [];
$desc = $isEdit ? (json_decode((string) $course['description'], true) ?: []) : [];
$acc = $isEdit ? (json_decode((string) $course['access_requirements'], true) ?: []) : [];
?>
<?php require VIEW_PATH . '/partials/management_nav.php'; ?>

<h1><?= e($isEdit ? __('catalog.edit_course') : __('catalog.new_course')) ?></h1>

<form method="post" action="<?= e($action) ?>" class="card">
    <?= csrf_field() ?>
    <div class="row">
        <div class="form-group"><label><?= e(__('catalog.code')) ?></label><input type="text" name="code" value="<?= e($isEdit ? (string) $course['code'] : '') ?>" required></div>
        <div class="form-group">
            <label><?= e(__('catalog.type')) ?></label>
            <select name="course_type">
                <?php foreach (Course::TYPES as $t): ?>
                    <option value="<?= e($t) ?>" <?= $isEdit && $course['course_type'] === $t ? 'selected' : '' ?>><?= e(__('catalog.types_label.' . $t)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label><?= e(__('catalog.area')) ?></label><input type="text" name="area" value="<?= e($isEdit ? (string) ($course['area'] ?? '') : '') ?>"></div>
    </div>

    <div class="form-group">
        <label><?= e(__('catalog.course')) ?> / <?= e(__('catalog.description')) ?> / <?= e(__('catalog.access_requirements')) ?></label>
        <div class="locale-tabs" data-tabs="course">
            <?php foreach ($locales as $i => $loc): ?>
                <button type="button" class="<?= $i === 0 ? 'active' : '' ?>" data-tab="<?= e($loc) ?>"><?= e($loc) ?></button>
            <?php endforeach; ?>
        </div>
        <?php foreach ($locales as $i => $loc): ?>
            <div class="locale-pane <?= $i === 0 ? 'active' : '' ?>" data-pane="<?= e($loc) ?>">
                <input type="text" name="title[<?= e($loc) ?>]" placeholder="<?= e(__('catalog.course')) ?> (<?= e($loc) ?>)" value="<?= e($title[$loc] ?? '') ?>" style="margin-bottom:6px">
                <textarea name="description[<?= e($loc) ?>]" rows="3" placeholder="<?= e(__('catalog.description')) ?> (<?= e($loc) ?>)" style="margin-bottom:6px"><?= e($desc[$loc] ?? '') ?></textarea>
                <textarea name="access_requirements[<?= e($loc) ?>]" rows="2" placeholder="<?= e(__('catalog.access_requirements')) ?> (<?= e($loc) ?>)"><?= e($acc[$loc] ?? '') ?></textarea>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row">
        <div class="form-group"><label><?= e(__('catalog.price')) ?> (€)</label><input type="number" step="0.01" name="price" value="<?= e($isEdit && $course['price'] !== null ? (string) $course['price'] : '') ?>"></div>
        <div class="form-group">
            <label><?= e(__('catalog.prerequisite')) ?></label>
            <select name="prerequisite_course_id">
                <option value="0">—</option>
                <?php foreach ($courses as $c): if ($isEdit && (int) $c['id'] === (int) $course['id']) {
                    continue;
                } ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $isEdit && (int) ($course['prerequisite_course_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e(Course::localized($c['title'])) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group checkbox-row" style="align-items:center">
            <input type="checkbox" id="is_active" name="is_active" value="1" <?= !$isEdit || (int) $course['is_active'] === 1 ? 'checked' : '' ?>>
            <label for="is_active" style="font-weight:400"><?= e(__('fields.active')) ?></label>
        </div>
    </div>

    <div class="actions" style="display:flex;justify-content:space-between">
        <a class="btn btn-ghost" href="<?= e(url('/gestion/cursos')) ?>"><?= e(__('common.cancel')) ?></a>
        <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
    </div>
</form>

<?php if ($isEdit): ?>
    <div class="card">
        <p><a class="btn btn-outline" href="<?= e(url('/gestion/cursos/' . $course['id'] . '/ediciones/nueva')) ?>"><?= e(__('catalog.new_edition')) ?></a></p>
    </div>
<?php endif; ?>

<script>
document.querySelectorAll('.locale-tabs[data-tabs="course"] button').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var loc = btn.getAttribute('data-tab');
        document.querySelectorAll('.locale-tabs[data-tabs="course"] button').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        document.querySelectorAll('.locale-pane').forEach(function (p) { p.classList.toggle('active', p.getAttribute('data-pane') === loc); });
    });
});
</script>
