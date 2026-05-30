<?php /** Solicitud de recuperación de contraseña. */ ?>
<h1 class="text-center"><?= e(__('auth.forgot_title')) ?></h1>

<form method="post" action="<?= e(url('/recuperar')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="email"><?= e(__('auth.field_email')) ?></label>
        <input type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autofocus autocomplete="email">
    </div>
    <button type="submit" class="btn btn-primary btn-block"><?= e(__('auth.send_reset_link')) ?></button>
</form>

<div class="auth-links">
    <a href="<?= e(url('/login')) ?>"><?= e(__('auth.login_cta')) ?></a>
</div>
