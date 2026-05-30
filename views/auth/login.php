<?php /** Formulario de inicio de sesión. */ ?>
<h1 class="text-center"><?= e(__('auth.login_title')) ?></h1>

<form method="post" action="<?= e(url('/login')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="email"><?= e(__('auth.field_email')) ?></label>
        <input type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autofocus autocomplete="email">
    </div>
    <div class="form-group">
        <label for="password"><?= e(__('auth.field_password')) ?></label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn btn-primary btn-block"><?= e(__('auth.login_cta')) ?></button>
</form>

<div class="auth-links">
    <a href="<?= e(url('/recuperar')) ?>"><?= e(__('auth.forgot_link')) ?></a>
    <div class="mt-2"><?= e(__('auth.no_account')) ?> <a href="<?= e(url('/registro')) ?>"><?= e(__('auth.register_cta')) ?></a></div>
    <form method="post" action="<?= e(url('/reenviar-verificacion')) ?>" class="mt-2">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-ghost" style="font-size:.85rem"><?= e(__('auth.resend_verification')) ?></button>
    </form>
</div>
