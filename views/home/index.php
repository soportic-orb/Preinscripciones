<?php
/** Página de inicio pública. */
use App\Core\Auth;
?>
<section class="hero">
    <h1><?= e(__('home.hero_title')) ?></h1>
    <p><?= e(__('home.hero_subtitle')) ?></p>
    <div class="hero-actions">
        <?php if (Auth::check()): ?>
            <a class="btn btn-primary" href="<?= e(url('/panel')) ?>"><?= e(__('nav.dashboard')) ?></a>
        <?php else: ?>
            <a class="btn btn-primary" href="<?= e(url('/registro')) ?>"><?= e(__('home.hero_cta')) ?></a>
            <a class="btn btn-outline" href="<?= e(url('/login')) ?>"><?= e(__('home.hero_login')) ?></a>
        <?php endif; ?>
    </div>
</section>

<section class="features">
    <div class="card">
        <h3><?= e(__('home.feature_courses_title')) ?></h3>
        <p class="text-muted"><?= e(__('home.feature_courses_body')) ?></p>
    </div>
    <div class="card">
        <h3><?= e(__('home.feature_process_title')) ?></h3>
        <p class="text-muted"><?= e(__('home.feature_process_body')) ?></p>
    </div>
    <div class="card">
        <h3><?= e(__('home.feature_secure_title')) ?></h3>
        <p class="text-muted"><?= e(__('home.feature_secure_body')) ?></p>
    </div>
</section>
