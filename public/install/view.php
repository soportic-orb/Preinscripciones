<?php

/**
 * Render del instalador. Variables disponibles: $state, $errors, $notice, $L,
 * $lang, $supported. Funciones: t(), h(), csrf_ok(), requirements().
 */

declare(strict_types=1);

$step = (int) $state['step'];
$token = $_SESSION['install_csrf'];
$stepLabels = $L['steps'];
$csrfInput = '<input type="hidden" name="_token" value="' . h($token) . '">';

?><!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(t('title')) ?></title>
    <link rel="stylesheet" href="assets/install.css">
</head>
<body>
<div class="wrap">
    <div class="lang-bar">
        <?php foreach ($supported as $l): ?>
            <a href="?lang=<?= h($l) ?>" class="<?= $l === $lang ? 'active' : '' ?>"><?= h($l) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="brand">IEM · Preinscripciones</div>

    <?php if ($step <= 7 && $step < 90): ?>
    <div class="steps">
        <?php foreach ($stepLabels as $i => $label): ?>
            <div class="step <?= $i === $step ? 'active' : ($i < $step ? 'done' : '') ?>"><?= h($label) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-err"><?= h($err) ?></div>
        <?php endforeach; ?>
        <?php if ($notice !== null): ?>
            <div class="alert alert-<?= $notice[0] === 'ok' ? 'ok' : 'err' ?>"><?= h($notice[1]) ?></div>
        <?php endif; ?>

        <?php
        switch ($step):
            // ===================================================== PASO 0
            case 0: ?>
                <h1><?= h(t('welcome_title')) ?></h1>
                <p class="muted"><?= h(t('welcome_intro')) ?></p>
                <form method="post">
                    <?= $csrfInput ?>
                    <input type="hidden" name="action" value="welcome">
                    <div class="mode-cards">
                        <label>
                            <input type="radio" name="mode" value="new" checked>
                            <strong><?= h(t('mode_new')) ?></strong>
                            <span class="muted"><?= h(t('mode_new_desc')) ?></span>
                        </label>
                        <label>
                            <input type="radio" name="mode" value="restore">
                            <strong><?= h(t('mode_restore')) ?></strong>
                            <span class="muted"><?= h(t('mode_restore_desc')) ?></span>
                        </label>
                    </div>
                    <div class="actions">
                        <span></span>
                        <button class="btn" type="submit"><?= h(t('start')) ?></button>
                    </div>
                </form>
                <?php break;

            // ===================================================== PASO 1
            case 1:
                $reqs = requirements(); ?>
                <h1><?= h(t('req_title')) ?></h1>
                <p class="muted"><?= h(t('req_intro')) ?></p>
                <ul class="check-list">
                    <?php foreach ($reqs as $r): ?>
                        <li>
                            <span class="ico <?= $r['ok'] ? 'ok' : ($r['required'] ? 'err' : 'warn') ?>">
                                <?= $r['ok'] ? '✓' : ($r['required'] ? '✗' : '!') ?>
                            </span>
                            <span><?= h($r['label']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="actions">
                    <a class="btn btn-ghost" href="?action=noop" onclick="location.reload();return false;"><?= h(t('req_recheck')) ?></a>
                    <?php if (requirements_ok()): ?>
                        <a class="btn" href="?action=req_continue"><?= h(t('next')) ?></a>
                    <?php else: ?>
                        <button class="btn" disabled><?= h(t('next')) ?></button>
                    <?php endif; ?>
                </div>
                <?php break;

            // ===================================================== PASO 2
            case 2:
                $db = $state['db']; ?>
                <h1><?= h(t('db_title')) ?></h1>
                <form method="post">
                    <?= $csrfInput ?>
                    <div class="row">
                        <div class="form-group"><label><?= h(t('db_host')) ?></label><input type="text" name="db_host" value="<?= h($db['host'] ?? '127.0.0.1') ?>"></div>
                        <div class="form-group" style="max-width:120px"><label><?= h(t('db_port')) ?></label><input type="number" name="db_port" value="<?= h((string) ($db['port'] ?? 3306)) ?>"></div>
                    </div>
                    <div class="form-group"><label><?= h(t('db_name')) ?></label><input type="text" name="db_name" value="<?= h($db['name'] ?? '') ?>" required></div>
                    <div class="row">
                        <div class="form-group"><label><?= h(t('db_user')) ?></label><input type="text" name="db_user" value="<?= h($db['user'] ?? '') ?>" required></div>
                        <div class="form-group"><label><?= h(t('db_pass')) ?></label><input type="password" name="db_pass" value=""></div>
                    </div>
                    <div class="form-group"><label><?= h(t('db_prefix')) ?></label><input type="text" name="db_prefix" value="<?= h($db['prefix'] ?? '') ?>"><div class="hint">p. ej. iem_</div></div>
                    <div class="actions">
                        <button class="btn btn-ghost" type="submit" name="action" value="db_test"><?= h(t('test')) ?></button>
                        <button class="btn" type="submit" name="action" value="db_save"><?= h(t('next')) ?></button>
                    </div>
                </form>
                <?php break;

            // ===================================================== PASO 3
            case 3: ?>
                <h1><?= h(t('mig_title')) ?></h1>
                <p class="muted"><?= h(t('mig_intro')) ?></p>
                <?php if (!empty($state['migrated'])): ?>
                    <div class="alert alert-ok"><?= h(t('mig_done')) ?></div>
                    <div class="log"><?php foreach (($state['migrations_ran'] ?? []) as $m) {
                        echo '✓ ' . h($m) . "\n";
                    } ?></div>
                <?php endif; ?>
                <form method="post">
                    <?= $csrfInput ?>
                    <div class="actions">
                        <button class="btn btn-ghost" type="submit" name="action" value="back"><?= h(t('prev')) ?></button>
                        <button class="btn" type="submit" name="action" value="migrate"><?= h(t('mig_run')) ?></button>
                    </div>
                </form>
                <?php break;

            // ===================================================== PASO 4
            case 4:
                $cfg = $state['config'];
                $autoUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
                $tzs = ['Europe/Madrid', 'Atlantic/Canary', 'Europe/Lisbon', 'Europe/London', 'UTC']; ?>
                <h1><?= h(t('cfg_title')) ?></h1>
                <form method="post">
                    <?= $csrfInput ?>
                    <input type="hidden" name="action" value="config">
                    <div class="form-group"><label><?= h(t('cfg_sitename')) ?></label><input type="text" name="site_name" value="<?= h($cfg['site_name'] ?? 'IEM Preinscripciones') ?>" required></div>
                    <div class="form-group"><label><?= h(t('cfg_url')) ?></label><input type="text" name="url" value="<?= h($cfg['url'] ?? $autoUrl) ?>" required></div>
                    <div class="row">
                        <div class="form-group"><label><?= h(t('cfg_locale')) ?></label>
                            <select name="locale">
                                <?php foreach ($supported as $l): ?><option value="<?= h($l) ?>" <?= ($cfg['locale'] ?? 'es') === $l ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label><?= h(t('cfg_tz')) ?></label>
                            <select name="timezone">
                                <?php foreach ($tzs as $tz): ?><option value="<?= h($tz) ?>" <?= ($cfg['timezone'] ?? 'Europe/Madrid') === $tz ? 'selected' : '' ?>><?= h($tz) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label><input type="checkbox" name="force_https" <?= ($cfg['force_https'] ?? 'true') === 'true' ? 'checked' : '' ?>> <?= h(t('cfg_https')) ?></label></div>
                    <div class="actions">
                        <button class="btn btn-ghost" type="submit" name="action" value="back"><?= h(t('prev')) ?></button>
                        <button class="btn" type="submit"><?= h(t('next')) ?></button>
                    </div>
                </form>
                <?php break;

            // ===================================================== PASO 5
            case 5:
                $int = $state['integrations']; ?>
                <h1><?= h(t('int_title')) ?></h1>
                <p class="muted"><?= h(t('int_intro')) ?></p>
                <form method="post">
                    <?= $csrfInput ?>
                    <input type="hidden" name="action" value="integrations">
                    <h2><?= h(t('int_smtp')) ?></h2>
                    <div class="row">
                        <div class="form-group"><label>Host</label><input type="text" name="mail_host" value="<?= h($int['mail_host'] ?? '') ?>"></div>
                        <div class="form-group" style="max-width:110px"><label>Port</label><input type="text" name="mail_port" value="<?= h($int['mail_port'] ?? '587') ?>"></div>
                    </div>
                    <div class="row">
                        <div class="form-group"><label>User</label><input type="text" name="mail_user" value="<?= h($int['mail_user'] ?? '') ?>"></div>
                        <div class="form-group"><label>Password</label><input type="password" name="mail_pass" value=""></div>
                    </div>
                    <h2><?= h(t('int_stripe')) ?></h2>
                    <div class="form-group"><label>Public key</label><input type="text" name="stripe_public" value="<?= h($int['stripe_public'] ?? '') ?>"></div>
                    <div class="form-group"><label>Secret key</label><input type="password" name="stripe_secret" value=""></div>
                    <div class="form-group"><label>Webhook secret</label><input type="text" name="stripe_webhook" value="<?= h($int['stripe_webhook'] ?? '') ?>"></div>
                    <h2><?= h(t('int_git')) ?></h2>
                    <div class="row">
                        <div class="form-group"><label>Branch</label><input type="text" name="git_branch" value="<?= h($int['git_branch'] ?? 'main') ?>"></div>
                        <div class="form-group"><label>Token</label><input type="password" name="git_token" value=""></div>
                    </div>
                    <div class="actions">
                        <button class="btn btn-ghost" type="submit" name="action" value="back"><?= h(t('prev')) ?></button>
                        <button class="btn" type="submit"><?= h(t('next')) ?></button>
                    </div>
                </form>
                <?php break;

            // ===================================================== PASO 6
            case 6: ?>
                <h1><?= h(t('admin_title')) ?></h1>
                <form method="post">
                    <?= $csrfInput ?>
                    <input type="hidden" name="action" value="admin">
                    <div class="form-group"><label><?= h(t('admin_name')) ?></label><input type="text" name="admin_name" required></div>
                    <div class="form-group"><label><?= h(t('admin_email')) ?></label><input type="email" name="admin_email" required></div>
                    <div class="row">
                        <div class="form-group"><label><?= h(t('admin_pass')) ?></label><input type="password" name="admin_pass" required></div>
                        <div class="form-group"><label><?= h(t('admin_pass_confirm')) ?></label><input type="password" name="admin_pass_confirm" required></div>
                    </div>
                    <div class="hint"><?= h(t('admin_pass_hint')) ?></div>
                    <div class="form-group" style="max-width:160px"><label><?= h(t('admin_locale')) ?></label>
                        <select name="admin_locale">
                            <?php foreach ($supported as $l): ?><option value="<?= h($l) ?>"><?= h($l) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="actions">
                        <button class="btn btn-ghost" type="submit" name="action" value="back"><?= h(t('prev')) ?></button>
                        <button class="btn" type="submit"><?= h(t('finish')) ?></button>
                    </div>
                </form>
                <?php break;

            // ===================================================== PASO 7 (fin)
            case 7: ?>
                <h1><?= h(t('done_title')) ?></h1>
                <div class="alert alert-ok"><?= h(t('done_intro')) ?></div>
                <div class="alert alert-warn"><?= h(t('done_remove')) ?></div>
                <div class="actions">
                    <span></span>
                    <a class="btn" href="../"><?= h(t('done_go')) ?></a>
                </div>
                <?php
                // Limpiar estado de instalación de la sesión.
                unset($_SESSION['install']);
                break;

            // ===================================================== Restaurar
            case 90: ?>
                <h1><?= h(t('restore_title')) ?></h1>
                <p class="muted"><?= h(t('restore_intro')) ?></p>
                <form method="post" enctype="multipart/form-data">
                    <?= $csrfInput ?>
                    <input type="hidden" name="action" value="restore">
                    <div class="form-group"><label><?= h(t('restore_file')) ?></label><input type="file" name="package" accept=".zip" required></div>
                    <div class="form-group"><label><?= h(t('restore_pass')) ?></label><input type="password" name="package_pass"></div>
                    <h2><?= h(t('db_title')) ?></h2>
                    <div class="row">
                        <div class="form-group"><label><?= h(t('db_host')) ?></label><input type="text" name="db_host" value="127.0.0.1"></div>
                        <div class="form-group" style="max-width:120px"><label><?= h(t('db_port')) ?></label><input type="number" name="db_port" value="3306"></div>
                    </div>
                    <div class="form-group"><label><?= h(t('db_name')) ?></label><input type="text" name="db_name" required></div>
                    <div class="row">
                        <div class="form-group"><label><?= h(t('db_user')) ?></label><input type="text" name="db_user" required></div>
                        <div class="form-group"><label><?= h(t('db_pass')) ?></label><input type="password" name="db_pass"></div>
                    </div>
                    <div class="form-group"><label><?= h(t('cfg_url')) ?></label><input type="text" name="url" required></div>
                    <div class="actions">
                        <button class="btn btn-ghost" type="submit" name="action" value="back"><?= h(t('prev')) ?></button>
                        <button class="btn" type="submit"><?= h(t('restore_run')) ?></button>
                    </div>
                </form>
                <?php break;

        endswitch; ?>
    </div>
</div>
</body>
</html>
