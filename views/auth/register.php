<?php /** Formulario de registro (alta de estudiante). */ ?>
<h1 class="text-center"><?= e(__('auth.register_title')) ?></h1>

<form method="post" action="<?= e(url('/registro')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="name"><?= e(__('auth.field_name')) ?></label>
        <input type="text" id="name" name="name" value="<?= e(old('name')) ?>" required autofocus>
    </div>
    <div class="form-group">
        <label for="email"><?= e(__('auth.field_email')) ?></label>
        <input type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email">
    </div>
    <div class="form-group">
        <label for="password"><?= e(__('auth.field_password')) ?></label>
        <input type="password" id="password" name="password" required autocomplete="new-password">
        <div class="field-hint"><?= e(__('validation.password', ['field' => __('auth.field_password')])) ?></div>
    </div>
    <div class="form-group">
        <label for="password_confirmation"><?= e(__('auth.field_password_confirm')) ?></label>
        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
    </div>
    <div class="form-group checkbox-row">
        <input type="checkbox" id="terms" name="terms" value="1">
        <label for="terms" style="font-weight:400"><?= e(__('auth.accept_terms')) ?></label>
    </div>
    <button type="submit" class="btn btn-primary btn-block"><?= e(__('auth.register_cta')) ?></button>
</form>

<div class="auth-links">
    <?= e(__('auth.have_account')) ?> <a href="<?= e(url('/login')) ?>"><?= e(__('auth.login_cta')) ?></a>
</div>
