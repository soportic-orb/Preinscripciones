<?php
/** Editor de una nueva versión de un texto legal (multiidioma). */
$active = 'legal';
?>
<?php require VIEW_PATH . '/partials/admin_nav.php'; ?>

<h1><?= e(__('legal.types.' . $type)) ?> — <?= e(__('legal.new_version')) ?></h1>

<form method="post" action="<?= e(url('/gestion/sistema/legales/' . $type)) ?>" class="card">
    <?= csrf_field() ?>

    <div class="locale-tabs" data-tabs="legal">
        <?php foreach ($locales as $i => $loc): ?>
            <button type="button" class="<?= $i === 0 ? 'active' : '' ?>" data-tab="<?= e($loc) ?>"><?= e($loc) ?></button>
        <?php endforeach; ?>
    </div>

    <?php foreach ($locales as $i => $loc): ?>
        <div class="locale-pane <?= $i === 0 ? 'active' : '' ?>" data-pane="<?= e($loc) ?>">
            <div class="form-group">
                <label><?= e(__('legal.title_field')) ?> (<?= e($loc) ?>)</label>
                <input type="text" name="title[<?= e($loc) ?>]" value="<?= e($existing[$loc]['title'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><?= e(__('legal.body_field')) ?> (<?= e($loc) ?>)</label>
                <textarea name="body[<?= e($loc) ?>]" rows="12"><?= e($existing[$loc]['body'] ?? '') ?></textarea>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="actions" style="display:flex;justify-content:space-between">
        <a class="btn btn-ghost" href="<?= e(url('/gestion/sistema/legales')) ?>"><?= e(__('common.cancel')) ?></a>
        <button type="submit" class="btn btn-primary"><?= e(__('legal.save_version')) ?></button>
    </div>
</form>

<script>
document.querySelectorAll('.locale-tabs[data-tabs="legal"] button').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var loc = btn.getAttribute('data-tab');
        document.querySelectorAll('.locale-tabs[data-tabs="legal"] button').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        document.querySelectorAll('.locale-pane').forEach(function (p) {
            p.classList.toggle('active', p.getAttribute('data-pane') === loc);
        });
    });
});
</script>
