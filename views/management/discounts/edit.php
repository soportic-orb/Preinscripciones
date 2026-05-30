<?php
/** Alta de descuento/beca/código. */
use App\Models\Course;
use App\Models\Discount;

$active = 'discounts';
?>
<?php require VIEW_PATH . '/partials/management_nav.php'; ?>

<h1><?= e(__('discounts.new')) ?></h1>

<form method="post" action="<?= e(url('/gestion/descuentos')) ?>" class="card">
    <?= csrf_field() ?>
    <div class="row">
        <div class="form-group" style="flex:2"><label><?= e(__('discounts.name')) ?></label><input type="text" name="name" required></div>
        <div class="form-group"><label><?= e(__('discounts.code')) ?></label><input type="text" name="code" placeholder="<?= e(__('discounts.code_hint')) ?>"></div>
    </div>
    <div class="row">
        <div class="form-group">
            <label><?= e(__('discounts.type')) ?></label>
            <select name="type">
                <?php foreach (Discount::TYPES as $t): ?><option value="<?= e($t) ?>"><?= e(__('discounts.types.' . $t)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label><?= e(__('discounts.value')) ?></label><input type="number" step="0.01" name="value" required></div>
        <div class="form-group">
            <label><?= e(__('discounts.scope')) ?></label>
            <select name="scope">
                <?php foreach (Discount::SCOPES as $s): ?><option value="<?= e($s) ?>"><?= e(__('discounts.scopes.' . $s)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label><?= e(__('discounts.scope_course')) ?></label>
            <select name="scope_id">
                <option value="0">—</option>
                <?php foreach ($courses as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e(Course::localized($c['title'])) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="row">
        <div class="form-group"><label><?= e(__('discounts.valid_from')) ?></label><input type="date" name="valid_from"></div>
        <div class="form-group"><label><?= e(__('discounts.valid_to')) ?></label><input type="date" name="valid_to"></div>
        <div class="form-group"><label><?= e(__('discounts.max_uses')) ?></label><input type="number" name="max_uses" value="0"></div>
        <div class="form-group checkbox-row" style="align-items:center"><input type="checkbox" id="is_active" name="is_active" value="1" checked><label for="is_active" style="font-weight:400"><?= e(__('fields.active')) ?></label></div>
    </div>
    <div class="actions" style="display:flex;justify-content:space-between">
        <a class="btn btn-ghost" href="<?= e(url('/gestion/descuentos')) ?>"><?= e(__('common.cancel')) ?></a>
        <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
    </div>
</form>
