<?php
/** Layout para páginas de autenticación (login, registro, etc.). */
use App\Core\Config;
use App\Core\View;

$appName = Config::app()['name'];
$pageTitle = isset($title) ? ($title . ' · ' . $appName) : $appName;
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
    <?php require VIEW_PATH . '/partials/toasts.php'; ?>
    <div class="auth-wrap">
        <div class="auth-card">
            <a class="brand" href="<?= e(url('/')) ?>">
                <img src="<?= e(asset('img/logo.svg')) ?>" alt="IEM" onerror="this.style.display='none'">
                <span>IEM</span>
            </a>
            <?= View::yield('content') ?>
            <div class="auth-links">
                <?php require VIEW_PATH . '/partials/lang_switch.php'; ?>
            </div>
        </div>
    </div>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
