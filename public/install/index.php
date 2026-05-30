<?php

/**
 * ============================================================================
 *  Instalador web paso a paso — IEM Preinscripciones
 *
 *  Aislado del core: solo PHP nativo + PDO. No requiere .env ni vendor/.
 *  Reutiliza el motor de migraciones (App\Core) para no duplicar el esquema,
 *  cargándolo mediante un autoloader mínimo sin depender del estado instalado.
 * ============================================================================
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('INSTALL_BASE', dirname(__DIR__, 2));            // raíz del proyecto
define('INSTALL_CONFIG', INSTALL_BASE . '/config');
define('INSTALL_LOCK', INSTALL_CONFIG . '/installed.lock');
define('INSTALL_LOG', INSTALL_BASE . '/storage/logs/install.log');

// --- 403 si ya está instalado ----------------------------------------------
if (is_file(INSTALL_LOCK)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>403</h1><p>La plataforma ya está instalada. Para reinstalar, elimina config/installed.lock por SSH.</p>';
    exit;
}

session_name('iem_install');
session_start();

// --- Idioma del instalador --------------------------------------------------
$supported = ['es', 'ca', 'en', 'pt'];
if (isset($_GET['lang']) && in_array($_GET['lang'], $supported, true)) {
    $_SESSION['install_lang'] = $_GET['lang'];
}
$lang = $_SESSION['install_lang'] ?? 'es';
$L = require __DIR__ . '/lang/' . $lang . '.php';

/** Traducción del instalador. */
function t(string $key, array $r = []): string
{
    global $L;
    $v = $L[$key] ?? $key;
    if (!is_string($v)) {
        return $key;
    }
    foreach ($r as $k => $val) {
        $v = str_replace(':' . $k, (string) $val, $v);
    }
    return $v;
}
function h(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

function install_log(string $msg): void
{
    @file_put_contents(INSTALL_LOG, '[' . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

// --- CSRF -------------------------------------------------------------------
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
function csrf_ok(): bool
{
    return isset($_POST['_token']) && hash_equals($_SESSION['install_csrf'], (string) $_POST['_token']);
}

// --- Estado -----------------------------------------------------------------
$_SESSION['install'] ??= ['step' => 0, 'mode' => 'new', 'db' => [], 'config' => [], 'integrations' => []];
$state = &$_SESSION['install'];

$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$errors = [];
$notice = null;

// ---------------------------------------------------------------------------
//  Cargar el motor de migraciones (sin estado instalado)
// ---------------------------------------------------------------------------
function load_core(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    if (!defined('BASE_PATH')) {
        define('BASE_PATH', INSTALL_BASE);
        define('APP_PATH', INSTALL_BASE . '/app');
        define('CONFIG_PATH', INSTALL_CONFIG);
        define('STORAGE_PATH', INSTALL_BASE . '/storage');
        define('DATABASE_PATH', INSTALL_BASE . '/database');
    }
    spl_autoload_register(static function (string $class): void {
        if (!str_starts_with($class, 'App\\')) {
            return;
        }
        $file = INSTALL_BASE . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
    $loaded = true;
}

/** Crea una conexión PDO a partir de los datos del paso 2. */
function install_pdo(array $db): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int) $db['port'], $db['name']);
    return new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

// ===========================================================================
//  PROCESADO DE ACCIONES (POST)
// ===========================================================================
if ($method === 'POST') {
    if (!csrf_ok()) {
        $errors[] = t('err_csrf');
    } else {
        switch ($action) {
            // ---- Paso 0: elegir modo y empezar ----
            case 'welcome':
                $state['mode'] = ($_POST['mode'] ?? 'new') === 'restore' ? 'restore' : 'new';
                $state['step'] = $state['mode'] === 'restore' ? 90 : 1;
                break;

            // ---- Paso 2: probar/guardar conexión ----
            case 'db_test':
            case 'db_save':
                $db = [
                    'host' => trim($_POST['db_host'] ?? '127.0.0.1'),
                    'port' => (int) ($_POST['db_port'] ?? 3306),
                    'name' => trim($_POST['db_name'] ?? ''),
                    'user' => trim($_POST['db_user'] ?? ''),
                    'pass' => (string) ($_POST['db_pass'] ?? ''),
                    'prefix' => trim($_POST['db_prefix'] ?? ''),
                ];
                try {
                    install_pdo($db);
                    $state['db'] = $db;
                    if ($action === 'db_save') {
                        $state['step'] = 3;
                    } else {
                        $notice = ['ok', t('db_ok')];
                    }
                } catch (Throwable $e) {
                    $errors[] = t('db_fail', ['error' => $e->getMessage()]);
                }
                break;

            // ---- Paso 3: ejecutar migraciones ----
            case 'migrate':
                try {
                    load_core();
                    $db = $state['db'];
                    $database = App\Core\Database::connect('mysql', $db['host'], (int) $db['port'], $db['name'], $db['user'], $db['pass'], $db['prefix']);
                    App\Core\Database::setInstance($database);
                    $migrator = new App\Core\Migrator($database);
                    $ran = $migrator->migrate(function (string $n): void { install_log('Migración: ' . $n); });
                    $state['migrated'] = true;
                    $state['migrations_ran'] = $ran;
                    $state['step'] = 4;
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                    install_log('ERROR migración: ' . $e->getMessage());
                }
                break;

            // ---- Paso 4: configuración general ----
            case 'config':
                $state['config'] = [
                    'site_name' => trim($_POST['site_name'] ?? 'IEM Preinscripciones'),
                    'url' => rtrim(trim($_POST['url'] ?? ''), '/'),
                    'locale' => in_array($_POST['locale'] ?? 'es', $supported, true) ? $_POST['locale'] : 'es',
                    'timezone' => trim($_POST['timezone'] ?? 'Europe/Madrid'),
                    'force_https' => isset($_POST['force_https']) ? 'true' : 'false',
                ];
                $state['step'] = 5;
                break;

            // ---- Paso 5: integraciones (opcionales) ----
            case 'integrations':
                $state['integrations'] = [
                    'mail_host' => trim($_POST['mail_host'] ?? ''),
                    'mail_port' => trim($_POST['mail_port'] ?? '587'),
                    'mail_user' => trim($_POST['mail_user'] ?? ''),
                    'mail_pass' => (string) ($_POST['mail_pass'] ?? ''),
                    'mail_enc' => trim($_POST['mail_enc'] ?? 'tls'),
                    'stripe_public' => trim($_POST['stripe_public'] ?? ''),
                    'stripe_secret' => trim($_POST['stripe_secret'] ?? ''),
                    'stripe_webhook' => trim($_POST['stripe_webhook'] ?? ''),
                    'git_branch' => trim($_POST['git_branch'] ?? 'main'),
                    'git_token' => trim($_POST['git_token'] ?? ''),
                ];
                $state['step'] = 6;
                break;

            // ---- Paso 6: crear admin y finalizar ----
            case 'admin':
                $name = trim($_POST['admin_name'] ?? '');
                $email = strtolower(trim($_POST['admin_email'] ?? ''));
                $pass = (string) ($_POST['admin_pass'] ?? '');
                $confirm = (string) ($_POST['admin_pass_confirm'] ?? '');
                $alocale = in_array($_POST['admin_locale'] ?? 'es', $supported, true) ? $_POST['admin_locale'] : 'es';

                if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = t('err_required');
                } elseif (!strong_password($pass)) {
                    $errors[] = t('err_pass_weak');
                } elseif ($pass !== $confirm) {
                    $errors[] = t('err_pass_match');
                } else {
                    try {
                        finalize_install($state, $name, $email, $pass, $alocale);
                        $state['admin_email'] = $email;
                        $state['step'] = 7;
                    } catch (Throwable $e) {
                        $errors[] = $e->getMessage();
                        install_log('ERROR finalización: ' . $e->getMessage());
                    }
                }
                break;

            // ---- Rama restaurar/migrar ----
            case 'restore':
                try {
                    restore_from_package($state, $_FILES['package'] ?? null, (string) ($_POST['package_pass'] ?? ''), [
                        'host' => trim($_POST['db_host'] ?? '127.0.0.1'),
                        'port' => (int) ($_POST['db_port'] ?? 3306),
                        'name' => trim($_POST['db_name'] ?? ''),
                        'user' => trim($_POST['db_user'] ?? ''),
                        'pass' => (string) ($_POST['db_pass'] ?? ''),
                        'prefix' => trim($_POST['db_prefix'] ?? ''),
                    ], trim($_POST['url'] ?? ''));
                    $state['step'] = 7;
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                }
                break;

            // ---- Navegación atrás ----
            case 'back':
                if ((int) $state['step'] === 90) {
                    $state['step'] = 0;
                } else {
                    $state['step'] = max(0, (int) ($_POST['to'] ?? ($state['step'] - 1)));
                }
                break;
        }
    }
}

// Avanzar del paso 1 (requisitos) al 2 vía GET cuando todo está OK.
if ($action === 'req_continue' && $state['step'] === 1) {
    if (requirements_ok()) {
        $state['step'] = 2;
    } else {
        $errors[] = t('req_blocked');
    }
}

// ===========================================================================
//  LÓGICA DE INSTALACIÓN
// ===========================================================================
function strong_password(string $p): bool
{
    return strlen($p) >= 12
        && preg_match('/[A-Z]/', $p) && preg_match('/[a-z]/', $p)
        && preg_match('/[0-9]/', $p) && preg_match('/[^A-Za-z0-9]/', $p);
}

/** @return array<int,array{label:string,ok:bool,required:bool}> */
function requirements(): array
{
    $bytes = static function (string $val): int {
        $val = trim($val);
        $n = (int) $val;
        return match (strtolower(substr($val, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    };
    $req = [];
    $req[] = ['label' => t('req_php'), 'ok' => PHP_VERSION_ID >= 80200, 'required' => true];
    foreach (['pdo_mysql', 'mbstring', 'openssl', 'json', 'curl', 'fileinfo', 'zip', 'xml', 'intl'] as $ext) {
        $req[] = ['label' => t('req_ext', ['ext' => $ext]), 'ok' => extension_loaded($ext), 'required' => true];
    }
    $req[] = ['label' => t('req_ext', ['ext' => 'gd | imagick']), 'ok' => extension_loaded('gd') || extension_loaded('imagick'), 'required' => true];

    foreach (['storage', 'storage/logs', 'storage/cache', 'storage/backups', 'public/uploads', 'config'] as $path) {
        $full = INSTALL_BASE . '/' . $path;
        $req[] = ['label' => t('req_writable', ['path' => $path]), 'ok' => is_dir($full) && is_writable($full), 'required' => true];
    }

    $req[] = ['label' => t('req_mem'), 'ok' => $bytes((string) ini_get('memory_limit')) === -1 || $bytes((string) ini_get('memory_limit')) >= 128 * 1024 ** 2, 'required' => true];
    $req[] = ['label' => t('req_upload'), 'ok' => $bytes((string) ini_get('upload_max_filesize')) >= 50 * 1024 ** 2, 'required' => false];
    $req[] = ['label' => t('req_post'), 'ok' => $bytes((string) ini_get('post_max_size')) >= 50 * 1024 ** 2, 'required' => false];
    $maxExec = (int) ini_get('max_execution_time');
    $req[] = ['label' => t('req_exec'), 'ok' => $maxExec === 0 || $maxExec >= 60, 'required' => false];
    $req[] = ['label' => t('req_openssl'), 'ok' => extension_loaded('openssl'), 'required' => true];

    $gitOk = false;
    if (function_exists('proc_open')) {
        $p = @proc_open(['git', '--version'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, INSTALL_BASE);
        if (is_resource($p)) {
            fclose($pipes[1]);
            fclose($pipes[2]);
            $gitOk = proc_close($p) === 0;
        }
    }
    $req[] = ['label' => t('req_git'), 'ok' => $gitOk, 'required' => false];

    return $req;
}

function requirements_ok(): bool
{
    foreach (requirements() as $r) {
        if ($r['required'] && !$r['ok']) {
            return false;
        }
    }
    return true;
}

/** Escribe config/.env, crea el admin y el lock. */
function finalize_install(array $state, string $name, string $email, string $pass, string $alocale): void
{
    load_core();
    $db = $state['db'];

    // Asegurar conexión y migraciones aplicadas.
    $database = App\Core\Database::connect('mysql', $db['host'], (int) $db['port'], $db['name'], $db['user'], $db['pass'], $db['prefix']);
    App\Core\Database::setInstance($database);
    (new App\Core\Migrator($database))->migrate();

    // Crear usuario administrador (rol owner) con argon2id.
    $hash = defined('PASSWORD_ARGON2ID')
        ? password_hash($pass, PASSWORD_ARGON2ID)
        : password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    $database->insert('users', [
        'name' => $name,
        'email' => $email,
        'password_hash' => $hash,
        'role' => 'owner',
        'locale' => $alocale,
        'email_verified_at' => date('Y-m-d H:i:s'),
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    // Escribir .env.
    write_env($state, $email);

    // Crear el lock.
    $lock = json_encode([
        'installed_at' => date('c'),
        'version' => trim((string) @file_get_contents(INSTALL_BASE . '/VERSION')) ?: 'dev',
        'hash' => bin2hex(random_bytes(8)),
    ], JSON_PRETTY_PRINT);
    file_put_contents(INSTALL_LOCK, $lock);
    @chmod(INSTALL_LOCK, 0600);
    install_log('Instalación finalizada para ' . $email);
}

function write_env(array $state, string $adminEmail): void
{
    $cfg = $state['config'] ?? [];
    $db = $state['db'] ?? [];
    $int = $state['integrations'] ?? [];
    $appKey = bin2hex(random_bytes(32));
    $esc = static fn (string $v): string => '"' . str_replace('"', '\"', $v) . '"';

    $lines = [
        'APP_NAME=' . $esc($cfg['site_name'] ?? 'IEM Preinscripciones'),
        'APP_ENV=production',
        'APP_DEBUG=false',
        'APP_URL=' . $esc($cfg['url'] ?? ''),
        'APP_KEY=' . $appKey,
        'APP_LOCALE=' . ($cfg['locale'] ?? 'es'),
        'APP_TIMEZONE=' . $esc($cfg['timezone'] ?? 'Europe/Madrid'),
        'FORCE_HTTPS=' . ($cfg['force_https'] ?? 'true'),
        'MAX_UPLOAD_MB=50',
        '',
        'DB_DRIVER=mysql',
        'DB_HOST=' . $esc($db['host'] ?? '127.0.0.1'),
        'DB_PORT=' . (int) ($db['port'] ?? 3306),
        'DB_NAME=' . $esc($db['name'] ?? ''),
        'DB_USER=' . $esc($db['user'] ?? ''),
        'DB_PASS=' . $esc($db['pass'] ?? ''),
        'DB_PREFIX=' . $esc($db['prefix'] ?? ''),
        'DB_CHARSET=utf8mb4',
        '',
        'MAIL_HOST=' . $esc($int['mail_host'] ?? ''),
        'MAIL_PORT=' . (int) ($int['mail_port'] ?? 587),
        'MAIL_USER=' . $esc($int['mail_user'] ?? ''),
        'MAIL_PASS=' . $esc($int['mail_pass'] ?? ''),
        'MAIL_ENCRYPTION=' . ($int['mail_enc'] ?? 'tls'),
        'MAIL_FROM_ADDRESS=' . $esc($int['mail_user'] ?: 'no-reply@example.com'),
        'MAIL_FROM_NAME=' . $esc($cfg['site_name'] ?? 'IEM Preinscripciones'),
        '',
        'STRIPE_PUBLIC_KEY=' . $esc($int['stripe_public'] ?? ''),
        'STRIPE_SECRET_KEY=' . $esc($int['stripe_secret'] ?? ''),
        'STRIPE_WEBHOOK_SECRET=' . $esc($int['stripe_webhook'] ?? ''),
        '',
        'GIT_REMOTE=origin',
        'GIT_BRANCH=' . ($int['git_branch'] ?? 'main'),
        'GIT_TOKEN=' . $esc($int['git_token'] ?? ''),
        '',
        'DATA_RETENTION_DAYS=0',
    ];
    $path = INSTALL_CONFIG . '/.env';
    file_put_contents($path, implode("\n", $lines) . "\n");
    @chmod($path, 0600);
}

/** Restauración básica desde paquete de migración (.zip con manifest + dump + uploads). */
function restore_from_package(array &$state, ?array $file, string $pass, array $db, string $url): void
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se ha subido un paquete válido.');
    }
    if (!extension_loaded('zip')) {
        throw new RuntimeException('La extensión zip no está disponible.');
    }
    // Validar conexión BD destino.
    install_pdo($db);

    $tmp = INSTALL_BASE . '/storage/tmp/restore_' . bin2hex(random_bytes(4));
    @mkdir($tmp, 0775, true);
    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) !== true) {
        throw new RuntimeException('No se pudo abrir el paquete .zip.');
    }
    if ($pass !== '') {
        $zip->setPassword($pass);
    }
    $zip->extractTo($tmp);
    $zip->close();

    $manifestRaw = @file_get_contents($tmp . '/manifest.json');
    $manifest = $manifestRaw ? json_decode($manifestRaw, true) : null;
    if (!is_array($manifest)) {
        throw new RuntimeException('El paquete no contiene un manifest.json válido.');
    }

    // Restaurar dump SQL.
    $sqlFile = $tmp . '/database.sql';
    if (is_file($tmp . '/database.sql.gz')) {
        $sqlFile = $tmp . '/database.sql';
        file_put_contents($sqlFile, gzdecode((string) file_get_contents($tmp . '/database.sql.gz')));
    }
    if (is_file($sqlFile)) {
        $pdo = install_pdo($db);
        $pdo->exec((string) file_get_contents($sqlFile));
    }

    // Restaurar uploads.
    if (is_dir($tmp . '/uploads')) {
        copy_dir($tmp . '/uploads', INSTALL_BASE . '/public/uploads');
    }

    // Escribir config con la URL nueva.
    $state['db'] = $db;
    $state['config'] = [
        'site_name' => $manifest['site_name'] ?? 'IEM Preinscripciones',
        'url' => rtrim($url, '/'),
        'locale' => $manifest['locale'] ?? 'es',
        'timezone' => $manifest['timezone'] ?? 'Europe/Madrid',
        'force_https' => 'true',
    ];
    write_env($state, '');

    $lock = json_encode(['installed_at' => date('c'), 'version' => $manifest['version'] ?? 'dev', 'restored' => true], JSON_PRETTY_PRINT);
    file_put_contents(INSTALL_LOCK, $lock);
    @chmod(INSTALL_LOCK, 0600);
    install_log('Restauración desde paquete completada.');
}

function copy_dir(string $src, string $dst): void
{
    @mkdir($dst, 0775, true);
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($items as $item) {
        $target = $dst . '/' . $items->getSubPathName();
        if ($item->isDir()) {
            @mkdir($target, 0775, true);
        } else {
            @copy($item->getPathname(), $target);
        }
    }
}

// ===========================================================================
//  RENDER
// ===========================================================================
require __DIR__ . '/view.php';
