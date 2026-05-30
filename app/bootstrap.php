<?php
/**
 * Bootstrap de la aplicación.
 *
 * Carga el autoloader propio (PSR-4 sin Composer en runtime), las dependencias
 * de terceros incluidas en vendor/ (si existen), la configuración de entorno y
 * registra el manejo de errores. Devuelve la instancia del contenedor/aplicación.
 */

declare(strict_types=1);

define('APP_START', microtime(true));
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('LANG_PATH', BASE_PATH . '/lang');
define('VIEW_PATH', BASE_PATH . '/views');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('DATABASE_PATH', BASE_PATH . '/database');

// --- Autoloader propio (PSR-4: App\ => app/) -------------------------------
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = APP_PATH . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// --- Dependencias de terceros (vendor/), solo si están presentes -----------
$vendorAutoload = BASE_PATH . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
}

// --- Carga del entorno (.env) ----------------------------------------------
App\Core\Env::load(CONFIG_PATH . '/.env');

// --- Zona horaria ----------------------------------------------------------
date_default_timezone_set(App\Core\Env::get('APP_TIMEZONE', 'Europe/Madrid'));

// --- Manejo de errores ------------------------------------------------------
$debug = App\Core\Env::bool('APP_DEBUG', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

set_exception_handler([App\Core\ErrorHandler::class, 'handleException']);
set_error_handler([App\Core\ErrorHandler::class, 'handleError']);
register_shutdown_function([App\Core\ErrorHandler::class, 'handleShutdown']);

return App\Core\App::boot();
