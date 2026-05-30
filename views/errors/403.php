<?php /** Error 403. */ ?>
<div class="error-page">
    <div class="code">403</div>
    <h1><?= e(__('common.forbidden_title')) ?></h1>
    <p class="text-muted"><?= e(__('common.forbidden_body')) ?></p>
    <a class="btn btn-primary" href="<?= e(url('/')) ?>"><?= e(__('nav.home')) ?></a>
</div>
