<?php
/** Integraciones: SMTP, Stripe, Git/OTA (editan config/.env en caliente). */
$active = 'integrations';
?>
<?php require VIEW_PATH . '/partials/admin_nav.php'; ?>

<h1><?= e(__('settings.integrations_title')) ?></h1>

<form method="post" action="<?= e(url('/gestion/sistema/integraciones')) ?>" class="card">
    <?= csrf_field() ?>

    <h3><?= e(__('settings.smtp')) ?></h3>
    <div class="row">
        <div class="form-group"><label><?= e(__('settings.mail_host')) ?></label><input type="text" name="mail_host" value="<?= e($mail['host']) ?>"></div>
        <div class="form-group" style="max-width:110px"><label><?= e(__('settings.mail_port')) ?></label><input type="number" name="mail_port" value="<?= e((string) $mail['port']) ?>"></div>
        <div class="form-group" style="max-width:140px">
            <label><?= e(__('settings.mail_encryption')) ?></label>
            <select name="mail_encryption">
                <?php foreach (['tls', 'ssl', 'none'] as $enc): ?>
                    <option value="<?= e($enc) ?>" <?= $mail['encryption'] === $enc ? 'selected' : '' ?>><?= e($enc) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="row">
        <div class="form-group"><label><?= e(__('settings.mail_user')) ?></label><input type="text" name="mail_user" value="<?= e($mail['user']) ?>"></div>
        <div class="form-group">
            <label><?= e(__('settings.mail_pass')) ?></label>
            <input type="password" name="mail_pass" value="" placeholder="••••••••">
            <div class="field-hint"><?= e(__('settings.secret_hint')) ?></div>
        </div>
    </div>
    <div class="form-group"><label><?= e(__('settings.mail_from')) ?></label><input type="email" name="mail_from" value="<?= e($mail['from_address']) ?>"></div>

    <h3><?= e(__('settings.stripe_keys')) ?></h3>
    <div class="form-group"><label><?= e(__('settings.stripe_public')) ?></label><input type="text" name="stripe_public" value="<?= e($stripe['public']) ?>"></div>
    <div class="form-group">
        <label><?= e(__('settings.stripe_secret')) ?> <span class="badge"><?= $stripe['secret_set'] ? e(__('settings.secret_set')) : e(__('settings.secret_unset')) ?></span></label>
        <input type="password" name="stripe_secret" value="" placeholder="••••••••">
        <div class="field-hint"><?= e(__('settings.secret_hint')) ?></div>
    </div>
    <div class="form-group"><label><?= e(__('settings.stripe_webhook')) ?></label><input type="text" name="stripe_webhook" value=""></div>

    <h3><?= e(__('settings.git')) ?></h3>
    <div class="row">
        <div class="form-group"><label><?= e(__('settings.git_branch')) ?></label><input type="text" name="git_branch" value="<?= e($git['branch']) ?>"></div>
        <div class="form-group">
            <label><?= e(__('settings.git_token')) ?> <span class="badge"><?= $git['token_set'] ? e(__('settings.secret_set')) : e(__('settings.secret_unset')) ?></span></label>
            <input type="password" name="git_token" value="" placeholder="••••••••">
        </div>
    </div>

    <button type="submit" class="btn btn-primary mt-4"><?= e(__('common.save')) ?></button>
</form>

<div class="card">
    <h3><?= e(__('settings.test_mail')) ?></h3>
    <div class="row" style="align-items:flex-end">
        <div class="form-group" style="flex:1">
            <label><?= e(__('settings.test_mail_to')) ?></label>
            <input type="email" id="test_mail_to" placeholder="correo@ejemplo.com">
        </div>
        <div class="form-group">
            <button type="button" class="btn btn-outline" id="btn_test_mail"><?= e(__('settings.test_mail')) ?></button>
        </div>
    </div>
</div>

<script>
document.getElementById('btn_test_mail').addEventListener('click', function () {
    var email = document.getElementById('test_mail_to').value;
    var body = new URLSearchParams({ email: email, _token: '<?= e(\App\Core\Csrf::token()) ?>' });
    fetch('<?= e(url('/gestion/sistema/integraciones/test-mail')) ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: body
    }).then(function (r) { return r.json(); }).then(function (d) {
        window.IEM.toast(d.ok ? 'success' : 'error', d.message);
    }).catch(function () { window.IEM.toast('error', 'Error'); });
});
</script>
