<?php
/** Alta/edición de convocatoria/edición + requisitos documentales. */
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\DocumentRequirement;

$active = 'courses';
$isEdit = $edition !== null;
$action = $isEdit ? url('/gestion/ediciones/' . $edition['id']) : url('/gestion/cursos/' . $course['id'] . '/ediciones');
$methods = $isEdit && $edition['payment_methods'] ? (json_decode((string) $edition['payment_methods'], true) ?: []) : [];
$val = static fn (string $k, string $d = ''): string => $isEdit && $edition[$k] !== null ? (string) $edition[$k] : $d;
?>
<?php require VIEW_PATH . '/partials/management_nav.php'; ?>

<h1><?= e(Course::localized($course['title'])) ?> — <?= e($isEdit ? __('catalog.edit_edition') : __('catalog.new_edition')) ?></h1>

<form method="post" action="<?= e($action) ?>" class="card">
    <?= csrf_field() ?>
    <div class="row">
        <div class="form-group" style="flex:2"><label><?= e(__('catalog.edition_name')) ?></label><input type="text" name="name" value="<?= e($val('name')) ?>" required></div>
        <div class="form-group">
            <label><?= e(__('catalog.modality')) ?></label>
            <select name="modality">
                <?php foreach (CourseEdition::MODALITIES as $m): ?>
                    <option value="<?= e($m) ?>" <?= $val('modality', 'presencial') === $m ? 'selected' : '' ?>><?= e(__('catalog.modalities.' . $m)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label><?= e(__('catalog.status')) ?></label>
            <select name="status">
                <?php foreach (CourseEdition::STATUS as $s): ?>
                    <option value="<?= e($s) ?>" <?= $val('status', 'draft') === $s ? 'selected' : '' ?>><?= e(__('catalog.statuses.' . $s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="row">
        <div class="form-group"><label><?= e(__('catalog.start')) ?></label><input type="date" name="start_date" value="<?= e(substr($val('start_date'), 0, 10)) ?>"></div>
        <div class="form-group"><label><?= e(__('catalog.end')) ?></label><input type="date" name="end_date" value="<?= e(substr($val('end_date'), 0, 10)) ?>"></div>
        <div class="form-group"><label><?= e(__('catalog.schedule')) ?></label><input type="text" name="schedule" value="<?= e($val('schedule')) ?>"></div>
    </div>
    <div class="row">
        <div class="form-group"><label><?= e(__('catalog.location')) ?></label><input type="text" name="location" value="<?= e($val('location')) ?>"></div>
        <div class="form-group"><label><?= e(__('catalog.price')) ?> (€)</label><input type="number" step="0.01" name="price" value="<?= e($val('price')) ?>"></div>
        <div class="form-group"><label><?= e(__('catalog.capacity')) ?></label><input type="number" name="capacity" value="<?= e($val('capacity', '0')) ?>"></div>
    </div>
    <div class="row">
        <div class="form-group"><label><?= e(__('catalog.open_at')) ?></label><input type="date" name="open_at" value="<?= e(substr($val('preinscription_open_at'), 0, 10)) ?>"></div>
        <div class="form-group"><label><?= e(__('catalog.close_at')) ?></label><input type="date" name="close_at" value="<?= e(substr($val('preinscription_close_at'), 0, 10)) ?>"></div>
        <div class="form-group checkbox-row" style="align-items:center">
            <input type="checkbox" id="waitlist_enabled" name="waitlist_enabled" value="1" <?= !$isEdit || (int) $edition['waitlist_enabled'] === 1 ? 'checked' : '' ?>>
            <label for="waitlist_enabled" style="font-weight:400"><?= e(__('catalog.waitlist')) ?></label>
        </div>
    </div>
    <div class="form-group">
        <label><?= e(__('catalog.payment_methods')) ?></label>
        <div style="display:flex;gap:16px">
            <?php foreach (['stripe', 'bizum', 'transfer'] as $m): ?>
                <label style="font-weight:400"><input type="checkbox" name="pay_<?= e($m) ?>" value="1" <?= in_array($m, $methods, true) ? 'checked' : '' ?>> <?= e(__('settings.pay_' . $m)) ?></label>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="actions" style="display:flex;justify-content:space-between">
        <a class="btn btn-ghost" href="<?= e(url('/gestion/cursos')) ?>"><?= e(__('common.cancel')) ?></a>
        <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
    </div>
</form>

<?php if ($isEdit): ?>
    <div class="card">
        <h3><?= e(__('catalog.document_requirements')) ?></h3>
        <?php if ($requirements !== []): ?>
            <table class="data-table">
                <thead><tr><th><?= e(__('documents.document')) ?></th><th><?= e(__('fields.required')) ?></th><th><?= e(__('documents.expiry')) ?></th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($requirements as $req): ?>
                        <tr>
                            <td><?= e(DocumentRequirement::localized($req['name'])) ?></td>
                            <td><?= (int) $req['is_required'] === 1 ? '✓' : '—' ?></td>
                            <td><?= (int) $req['has_expiry'] === 1 ? '✓' : '—' ?></td>
                            <td>
                                <form method="post" action="<?= e(url('/gestion/ediciones/' . $edition['id'] . '/requisitos/' . $req['id'] . '/eliminar')) ?>" onsubmit="return confirm('?')">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-ghost btn-sm" style="color:var(--color-accent)">✕</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/gestion/ediciones/' . $edition['id'] . '/requisitos')) ?>" style="margin-top:12px">
            <?= csrf_field() ?>
            <div class="row" style="align-items:flex-end">
                <div class="form-group" style="flex:1">
                    <label><?= e(__('catalog.requirement_name')) ?> (es)</label>
                    <input type="text" name="req_name[es]" required>
                </div>
                <div class="form-group checkbox-row" style="align-items:center"><input type="checkbox" name="req_required" value="1" checked id="rr"><label for="rr" style="font-weight:400"><?= e(__('fields.required')) ?></label></div>
                <div class="form-group checkbox-row" style="align-items:center"><input type="checkbox" name="req_expiry" value="1" id="re"><label for="re" style="font-weight:400"><?= e(__('documents.expiry')) ?></label></div>
                <div class="form-group"><button type="submit" class="btn btn-outline"><?= e(__('catalog.add_requirement')) ?></button></div>
            </div>
            <?php foreach (['ca', 'en', 'pt'] as $loc): ?>
                <input type="text" name="req_name[<?= e($loc) ?>]" placeholder="<?= e(__('catalog.requirement_name')) ?> (<?= e($loc) ?>)" style="margin-bottom:6px">
            <?php endforeach; ?>
        </form>
    </div>
<?php endif; ?>
