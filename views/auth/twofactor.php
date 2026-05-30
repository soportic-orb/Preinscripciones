<?php /** Verificación del segundo factor (TOTP). */ ?>
<h1 class="text-center"><?= e(__('auth.twofa_title')) ?></h1>
<p class="text-center text-muted"><?= e(__('auth.twofa_prompt')) ?></p>

<form method="post" action="<?= e(url('/2fa')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="form-group">
        <label for="code"><?= e(__('auth.twofa_code')) ?></label>
        <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
               required autofocus autocomplete="one-time-code" style="text-align:center;letter-spacing:.4em;font-size:1.3rem">
    </div>
    <button type="submit" class="btn btn-primary btn-block"><?= e(__('auth.login_cta')) ?></button>
</form>
