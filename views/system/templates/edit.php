<?php
/** Editor de una plantilla de email (asunto + HTML con variables). */
$active = 'templates';
?>
<?php require VIEW_PATH . '/partials/admin_nav.php'; ?>

<p><a href="<?= e(url('/gestion/sistema/plantillas')) ?>">&larr; <?= e(__('templates.title')) ?></a></p>
<h1><?= e(__('templates.edit')) ?> — <code><?= e($event) ?></code> (<?= e(strtoupper($locale)) ?>)</h1>

<div class="card">
    <p class="field-hint"><?= e(__('templates.vars_hint')) ?>: <code>{{name}}</code> <code>{{course}}</code> <code>{{edition}}</code> <code>{{amount}}</code> <code>{{reason}}</code></p>
    <form method="post" action="<?= e(url('/gestion/sistema/plantillas/' . $event . '/' . $locale)) ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label><?= e(__('templates.subject')) ?></label>
            <input type="text" name="subject" value="<?= e((string) $tpl['subject']) ?>" required>
        </div>
        <div class="form-group">
            <label><?= e(__('templates.body')) ?> (HTML)</label>
            <textarea name="body_html" rows="12" id="tpl_body" oninput="document.getElementById('tpl_prev').srcdoc=this.value"><?= e((string) $tpl['body_html']) ?></textarea>
        </div>
        <div class="form-group">
            <label><?= e(__('templates.preview')) ?></label>
            <iframe id="tpl_prev" style="width:100%;height:200px;border:1px solid var(--color-border);border-radius:5px;background:#fff" srcdoc="<?= e((string) $tpl['body_html']) ?>"></iframe>
        </div>
        <div class="form-group checkbox-row">
            <input type="checkbox" id="is_active" name="is_active" value="1" <?= !$isNew && (int) ($tpl['is_active'] ?? 0) === 1 ? 'checked' : (!$isNew ? '' : 'checked') ?>>
            <label for="is_active" style="font-weight:400"><?= e(__('templates.active_hint')) ?></label>
        </div>
        <div class="actions" style="display:flex;justify-content:space-between">
            <a class="btn btn-ghost" href="<?= e(url('/gestion/sistema/plantillas')) ?>"><?= e(__('common.cancel')) ?></a>
            <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
        </div>
    </form>
</div>
