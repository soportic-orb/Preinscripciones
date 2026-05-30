<?php
/** Formulario de creación/edición de un campo dinámico. */
/** @var \App\Models\FieldDefinition|null $field */
$active = 'fields';
$isEdit = $field !== null;
$action = $isEdit ? url('/gestion/sistema/campos/' . $field->id) : url('/gestion/sistema/campos');
$optionsRaw = '';
if ($isEdit) {
    foreach ($field->options as $o) {
        $lab = $o['label']['es'] ?? ($o['value'] ?? '');
        $optionsRaw .= ($o['value'] ?? '') . '|' . $lab . "\n";
    }
}
$val = static fn (string $k, string $d = ''): string => $isEdit ? (string) ($field->$k ?? $d) : $d;
?>
<?php require VIEW_PATH . '/partials/admin_nav.php'; ?>

<h1><?= e($isEdit ? __('fields.edit') : __('fields.new')) ?></h1>

<form method="post" action="<?= e($action) ?>" class="card">
    <?= csrf_field() ?>

    <div class="row">
        <div class="form-group">
            <label><?= e(__('fields.form')) ?></label>
            <select name="form_key">
                <?php foreach ($forms as $f): ?>
                    <option value="<?= e($f) ?>" <?= $val('form_key', 'preinscription') === $f ? 'selected' : '' ?>><?= e(__('fields.forms.' . $f)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label><?= e(__('fields.type')) ?></label>
            <select name="type">
                <?php foreach (\App\Models\FieldDefinition::TYPES as $t): ?>
                    <option value="<?= e($t) ?>" <?= $val('type', 'text') === $t ? 'selected' : '' ?>><?= e(__('fields.types.' . $t)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="form-group">
            <label><?= e(__('fields.field_key')) ?></label>
            <input type="text" name="field_key" value="<?= e($val('field_key')) ?>" <?= $isEdit ? 'readonly' : '' ?>>
            <div class="field-hint"><?= e(__('fields.field_key_hint')) ?></div>
        </div>
        <div class="form-group">
            <label><?= e(__('fields.section')) ?></label>
            <input type="text" name="section" value="<?= e($val('section', 'general')) ?>">
        </div>
        <div class="form-group" style="max-width:120px">
            <label><?= e(__('fields.sort_order')) ?></label>
            <input type="number" name="sort_order" value="<?= e((string) ($isEdit ? $field->sort_order : 0)) ?>">
        </div>
    </div>

    <!-- Textos multiidioma -->
    <div class="form-group">
        <label><?= e(__('fields.label')) ?> / <?= e(__('fields.help')) ?> / <?= e(__('fields.placeholder')) ?></label>
        <div class="locale-tabs" data-tabs="i18n">
            <?php foreach ($locales as $i => $loc): ?>
                <button type="button" class="<?= $i === 0 ? 'active' : '' ?>" data-tab="<?= e($loc) ?>"><?= e($loc) ?></button>
            <?php endforeach; ?>
        </div>
        <?php foreach ($locales as $i => $loc): ?>
            <div class="locale-pane <?= $i === 0 ? 'active' : '' ?>" data-pane="<?= e($loc) ?>">
                <input type="text" name="label[<?= e($loc) ?>]" placeholder="<?= e(__('fields.label')) ?> (<?= e($loc) ?>)"
                       value="<?= e($isEdit ? ($field->label[$loc] ?? '') : '') ?>" style="margin-bottom:6px">
                <input type="text" name="help[<?= e($loc) ?>]" placeholder="<?= e(__('fields.help')) ?> (<?= e($loc) ?>)"
                       value="<?= e($isEdit ? ($field->help[$loc] ?? '') : '') ?>" style="margin-bottom:6px">
                <input type="text" name="placeholder[<?= e($loc) ?>]" placeholder="<?= e(__('fields.placeholder')) ?> (<?= e($loc) ?>)"
                       value="<?= e($isEdit ? ($field->placeholder[$loc] ?? '') : '') ?>">
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Opciones (select/radio) -->
    <div class="form-group">
        <label><?= e(__('fields.options')) ?></label>
        <textarea name="options_raw" rows="4" placeholder="valor|etiqueta"><?= e(trim($optionsRaw)) ?></textarea>
        <div class="field-hint"><?= e(__('fields.options_hint')) ?></div>
    </div>

    <!-- Validaciones -->
    <div class="row">
        <div class="form-group"><label><?= e(__('fields.valid_min')) ?></label><input type="number" name="valid_min" value="<?= e($isEdit ? (string) ($field->validations['min'] ?? '') : '') ?>"></div>
        <div class="form-group"><label><?= e(__('fields.valid_max')) ?></label><input type="number" name="valid_max" value="<?= e($isEdit ? (string) ($field->validations['max'] ?? '') : '') ?>"></div>
        <div class="form-group"><label><?= e(__('fields.valid_regex')) ?></label><input type="text" name="valid_regex" value="<?= e($isEdit ? (string) ($field->validations['regex'] ?? '') : '') ?>"></div>
    </div>

    <div class="row">
        <div class="form-group checkbox-row">
            <input type="checkbox" id="is_required" name="is_required" value="1" <?= $isEdit && $field->is_required ? 'checked' : '' ?>>
            <label for="is_required" style="font-weight:400"><?= e(__('fields.required')) ?></label>
        </div>
        <div class="form-group checkbox-row">
            <input type="checkbox" id="is_active" name="is_active" value="1" <?= !$isEdit || $field->is_active ? 'checked' : '' ?>>
            <label for="is_active" style="font-weight:400"><?= e(__('fields.active')) ?></label>
        </div>
    </div>

    <div class="actions" style="display:flex;justify-content:space-between;margin-top:16px">
        <a class="btn btn-ghost" href="<?= e(url('/gestion/sistema/campos')) ?>"><?= e(__('common.cancel')) ?></a>
        <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
    </div>
</form>

<script>
document.querySelectorAll('.locale-tabs[data-tabs="i18n"] button').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var loc = btn.getAttribute('data-tab');
        document.querySelectorAll('.locale-tabs[data-tabs="i18n"] button').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        document.querySelectorAll('.locale-pane').forEach(function (p) {
            p.classList.toggle('active', p.getAttribute('data-pane') === loc);
        });
    });
});
</script>
