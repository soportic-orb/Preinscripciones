<?php
/** Layout principal de la aplicación. */
use App\Core\Auth;
use App\Core\Config;
use App\Core\View;

$appName = Config::app()['name'];
$pageTitle = isset($title) ? ($title . ' · ' . $appName) : $appName;
$user = Auth::user();
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($appName) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
    <a href="#main" class="skip-link"><?= e(__('common.skip_to_content')) ?></a>
    <?php require VIEW_PATH . '/partials/toasts.php'; ?>

    <header class="site-header">
        <div class="container">
            <a class="brand" href="<?= e(url('/')) ?>">
                <img src="<?= e(asset('img/logo.svg')) ?>" alt="IEM" onerror="this.style.display='none'">
                <span>IEM</span>
            </a>
            <nav class="nav" aria-label="<?= e(__('nav.home')) ?>">
                <a href="<?= e(url('/')) ?>"><?= e(__('nav.home')) ?></a>
                <?php if ($user === null): ?>
                    <a href="<?= e(url('/login')) ?>"><?= e(__('nav.login')) ?></a>
                    <a class="btn btn-primary" href="<?= e(url('/registro')) ?>"><?= e(__('nav.register')) ?></a>
                <?php else: ?>
                    <a href="<?= e(url('/panel')) ?>"><?= e(__('nav.dashboard')) ?></a>
                    <a href="<?= e(url('/panel/mensajes')) ?>"><?= e(__('messages.title')) ?></a>
                    <form method="post" action="<?= e(url('/logout')) ?>" style="display:inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-ghost"><?= e(__('common.logout')) ?></button>
                    </form>
                <?php endif; ?>
                <?php require VIEW_PATH . '/partials/lang_switch.php'; ?>
            </nav>
        </div>
    </header>

    <main id="main" class="container" style="padding-top:24px;padding-bottom:24px">
        <?= View::yield('content') ?>
    </main>

    <footer class="site-footer">
        <div class="container">
            <span>© <?= date('Y') ?> Institut d'Estudis Mèdics · <?= e($appName) ?></span>
            <a href="<?= e(url('/verificar-certificado')) ?>" style="margin-left:12px"><?= e(__('certificates.verify_title')) ?></a>
        </div>
    </footer>

    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
