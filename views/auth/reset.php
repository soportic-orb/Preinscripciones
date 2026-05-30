<?php /** Restablecer contraseña con token. */ ?>
<h1 class="text-center"><?= e(__('auth.reset_title')) ?></h1>

<form method="post" action="<?= e(url('/restablecer')) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
    <div class="form-group">
        <label for="password"><?= e(__('auth.field_password')) ?></label>
        <input type="password" id="password" name="password" required autofocus autocomplete="new-password">
        <div class="field-hint"><?= e(__('validation.password', ['field' => __('auth.field_password')])) ?></div>
    </div>
    <div class="form-group">
        <label for="password_confirmation"><?= e(__('auth.field_password_confirm')) ?></label>
        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
    </div>
    <button type="submit" class="btn btn-primary btn-block"><?= e(__('common.save')) ?></button>
</form>
