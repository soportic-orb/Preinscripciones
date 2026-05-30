<?php /** Error 404. */ ?>
<div class="error-page">
    <div class="code">404</div>
    <h1><?= e(__('common.not_found_title')) ?></h1>
    <p class="text-muted"><?= e(__('common.not_found_body')) ?></p>
    <a class="btn btn-primary" href="<?= e(url('/')) ?>"><?= e(__('nav.home')) ?></a>
</div>
