<?php

/**
 * Test runner ligero y autocontenido (sin dependencias externas).
 *
 * Valida la lógica crítica de la Fase 1 usando SQLite en memoria, de modo que
 * pueda ejecutarse en cualquier entorno sin servidor MySQL ni Composer.
 *
 *   php tests/run.php
 *
 * Cuando haya PHPUnit en vendor/, estos casos se migrarán a tests/Unit y Feature.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('LANG_PATH', BASE_PATH . '/lang');
define('DATABASE_PATH', BASE_PATH . '/database');

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
require APP_PATH . '/Core/helpers.php';

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Hash;
use App\Core\I18n;
use App\Core\Migrator;
use App\Core\Token;
use App\Core\Totp;
use App\Core\Validator;
use App\Models\User;

$passed = 0;
$failed = 0;
function check(string $name, bool $cond): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  \033[32m✓\033[0m {$name}\n";
    } else {
        $failed++;
        echo "  \033[31m✗ {$name}\033[0m\n";
    }
}

echo "== Tests Fase 1 ==\n";

// --- Conexión SQLite en memoria + migraciones ---
$db = Database::connect('sqlite', '', 0, ':memory:', '', '', '', 'utf8mb4');
Database::setInstance($db);
$migrator = new Migrator($db);
$ran = $migrator->migrate();
check('Migraciones se aplican', count($ran) >= 1);
check('Tabla users existe', $db->tableExists('users'));
check('Tabla audit_log existe', $db->tableExists('audit_log'));
check('No quedan migraciones pendientes', $migrator->pending() === []);

// --- Hash argon2id ---
$hash = Hash::make('Sup3rS3cret-2026!');
check('Hash verifica correctamente', Hash::verify('Sup3rS3cret-2026!', $hash));
check('Hash rechaza incorrecta', !Hash::verify('mala', $hash));

// --- Validador de contraseñas ---
check('Password fuerte aceptada', Validator::strongPassword('Abcdef12345!'));
check('Password débil rechazada (corta)', !Validator::strongPassword('Ab1!'));
check('Password débil rechazada (sin símbolo)', !Validator::strongPassword('Abcdefgh1234'));

// --- Validador genérico ---
$v = Validator::make(
    ['email' => 'no-es-email', 'name' => ''],
    ['email' => 'required|email', 'name' => 'required'],
);
check('Validator detecta errores', $v->fails());
check('Validator cuenta 2 errores', count($v->errors()) === 2);

// --- i18n en los 4 idiomas ---
I18n::setFallback('es');
$ok4 = true;
foreach (['es', 'ca', 'en', 'pt'] as $loc) {
    I18n::setLocale($loc);
    $val = I18n::t('auth.login_title');
    if ($val === 'auth.login_title' || $val === '') {
        $ok4 = false;
    }
}
check('auth.login_title traducido en es/ca/en/pt', $ok4);
I18n::setLocale('es');
check('Reemplazo de variables i18n', str_contains(I18n::t('auth.welcome_back', ['name' => 'Ada']), 'Ada'));
check('Fallback a es si falta clave', I18n::t('auth.login_title', [], 'en') !== '');

// --- Usuario + Auth ---
$user = User::create([
    'name' => 'Test', 'email' => 'Test@Example.com', 'password' => 'Abcdef12345!',
    'role' => 'estudiante', 'locale' => 'es', 'is_active' => 1,
]);
check('Email normalizado a minúsculas', $user->email === 'test@example.com');
check('emailExists detecta el alta', User::emailExists('test@example.com'));
check('Auth::attempt con credenciales correctas', Auth::attempt('test@example.com', 'Abcdef12345!') !== null);
check('Auth::attempt rechaza password incorrecta', Auth::attempt('test@example.com', 'otra') === null);

// --- Tokens de un solo uso ---
$token = Token::issue('password_resets', $user->id, 3600);
check('Token válido se consume', Token::consume('password_resets', $token) !== null);
check('Token no se puede reutilizar', Token::consume('password_resets', $token) === null);

// --- TOTP ---
$secret = Totp::generateSecret();
$code = Totp::codeAt($secret, (int) floor(time() / 30));
check('TOTP verifica código actual', Totp::verify($secret, $code));
check('TOTP rechaza código inválido', !Totp::verify($secret, '000000'));

// --- Auditoría encadenada ---
Audit::log('test.action', $user->id, 'user', $user->id, ['k' => 'v'], '127.0.0.1');
Audit::log('test.action2', $user->id, 'user', $user->id, [], '127.0.0.1');
$rows = $db->fetchAll('SELECT * FROM {audit_log} ORDER BY id');
check('Auditoría registra 2 filas', count($rows) === 2);
check('Cadena de hash enlazada', $rows[1]['prev_hash'] === $rows[0]['row_hash']);

echo "\nResultado: \033[32m{$passed} OK\033[0m" . ($failed > 0 ? ", \033[31m{$failed} FALLOS\033[0m" : '') . "\n";
exit($failed > 0 ? 1 : 0);
