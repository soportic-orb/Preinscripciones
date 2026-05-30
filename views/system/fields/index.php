<?php
/** Listado de campos dinámicos por formulario. */
/** @var \App\Services\FieldService $service */
$active = 'fields';
?>
<?php require VIEW_PATH . '/partials/admin_nav.php'; ?>

<div class="toolbar">
    <div>
        <h1><?= e(__('fields.title')) ?></h1>
        <p class="text-muted" style="margin:0"><?= e(__('fields.intro')) ?></p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('/gestion/sistema/campos/nuevo')) ?>"><?= e(__('fields.new')) ?></a>
</div>

<div class="admin-nav" style="border:0;margin-bottom:16px">
    <?php foreach ($forms as $f): ?>
        <a href="<?= e(url('/gestion/sistema/campos?form=' . $f)) ?>" class="<?= $f === $formKey ? 'active' : '' ?>">
            <?= e(__('fields.forms.' . $f)) ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <?php if ($fields === []): ?>
        <p class="text-muted" style="margin:0"><?= e(__('fields.no_fields')) ?></p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th><?= e(__('fields.sort_order')) ?></th>
                    <th><?= e(__('fields.label')) ?></th>
                    <th><?= e(__('fields.field_key')) ?></th>
                    <th><?= e(__('fields.type')) ?></th>
                    <th><?= e(__('fields.section')) ?></th>
                    <th><?= e(__('fields.required')) ?></th>
                    <th><?= e(__('fields.active')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fields as $field): ?>
                    <tr>
                        <td><?= (int) $field->sort_order ?></td>
                        <td><?= e($field->label()) ?></td>
                        <td><code><?= e($field->field_key) ?></code></td>
                        <td><?= e(__('fields.types.' . $field->type)) ?></td>
                        <td><?= e($field->section) ?></td>
                        <td><?= $field->is_required ? '✓' : '—' ?></td>
                        <td><?= $field->is_active ? '✓' : '—' ?></td>
                        <td class="actions-cell">
                            <a class="btn btn-ghost btn-sm" href="<?= e(url('/gestion/sistema/campos/' . $field->id)) ?>"><?= e(__('fields.edit')) ?></a>
                            <?php if (!$field->is_system): ?>
                                <form method="post" action="<?= e(url('/gestion/sistema/campos/' . $field->id . '/eliminar')) ?>"
                                      onsubmit="return confirm('<?= e(__('fields.confirm_delete')) ?>')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--color-accent)">✕</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
