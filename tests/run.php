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
use App\Core\Settings;
use App\Core\Token;
use App\Core\Totp;
use App\Core\Validator;
use App\Models\FieldDefinition;
use App\Models\LegalDocument;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\FieldService;

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

// ====================== Fase 2 ======================
echo "\n== Tests Fase 2 ==\n";

// --- Settings (configuración en caliente) ---
Settings::set('payments', 'stripe', '1');
Settings::flush();
check('Settings persiste y recupera', Settings::get('payments', 'stripe') === '1');
check('Settings::bool interpreta valor', Settings::bool('payments', 'stripe') === true);
Settings::set('payments', 'stripe', '0');
check('Settings actualiza (upsert)', Settings::get('payments', 'stripe') === '0');

// --- Motor de campos dinámicos ---
$f1 = FieldDefinition::createFromInput([
    'form_key' => 'preinscription', 'section' => 'datos', 'field_key' => 'telefono', 'type' => 'tel',
    'label' => ['es' => 'Teléfono', 'ca' => 'Telèfon', 'en' => 'Phone', 'pt' => 'Telefone'],
    'is_required' => 1, 'is_active' => 1, 'sort_order' => 10,
]);
$f2 = FieldDefinition::createFromInput([
    'form_key' => 'preinscription', 'section' => 'datos', 'field_key' => 'pais', 'type' => 'select',
    'label' => ['es' => 'País'], 'options' => [
        ['value' => 'es', 'label' => ['es' => 'España']],
        ['value' => 'pt', 'label' => ['es' => 'Portugal']],
    ],
    'is_required' => 0, 'is_active' => 1, 'sort_order' => 20,
]);
$fields = FieldDefinition::forForm('preinscription');
check('forForm devuelve los campos activos', count($fields) === 2);
check('Label localizada (en)', (function () use ($f1) {
    \App\Core\I18n::setLocale('en');
    $r = $f1->label() === 'Phone';
    \App\Core\I18n::setLocale('es');
    return $r;
})());

$svc = new FieldService();
// Validación: requerido vacío -> error; select fuera de opciones -> error.
$errs = $svc->validate($fields, ['telefono' => '', 'pais' => 'fr']);
check('Validación detecta requerido vacío', isset($errs['telefono']));
check('Validación detecta opción inválida en select', isset($errs['pais']));
$ok = $svc->validate($fields, ['telefono' => '600123123', 'pais' => 'es']);
check('Validación correcta sin errores', $ok === []);

// Guardar y recuperar valores por entidad.
$svc->save($fields, ['telefono' => '600123123', 'pais' => 'es'], 'preinscription', 555);
$vals = $svc->values('preinscription', 555);
check('Valores dinámicos se persisten', ($vals['telefono'] ?? '') === '600123123');
$svc->save($fields, ['telefono' => '699999999', 'pais' => 'pt'], 'preinscription', 555);
$vals = $svc->values('preinscription', 555);
check('Valores dinámicos se actualizan (upsert)', ($vals['telefono'] ?? '') === '699999999');

// Render produce HTML con el nombre agrupado.
$html = $svc->renderField($f1, '600');
check('Render incluye name field[telefono]', str_contains($html, 'name="field[telefono]"'));

// Borrado de campo (no system) elimina también sus valores.
FieldDefinition::delete($f2->id);
check('Delete elimina la definición', FieldDefinition::find($f2->id) === null);

// --- Textos legales versionados ---
$v1 = LegalDocument::publishNewVersion('terms', [
    'es' => ['title' => 'Términos', 'body' => 'v1'],
    'en' => ['title' => 'Terms', 'body' => 'v1'],
]);
check('Primera versión legal es 1', $v1 === 1);
check('currentVersion refleja publicación', LegalDocument::currentVersion('terms') === 1);
$v2 = LegalDocument::publishNewVersion('terms', ['es' => ['title' => 'Términos', 'body' => 'v2']]);
check('Segunda versión incrementa', $v2 === 2 && LegalDocument::currentVersion('terms') === 2);

// --- Consentimientos versionados ---
$consent = new ConsentService();
$consent->record($user->id, 'terms', '127.0.0.1');
check('Consentimiento aceptado de versión vigente', $consent->hasAcceptedCurrent($user->id, 'terms'));
// Nueva versión -> el consentimiento anterior ya no cubre la vigente.
LegalDocument::publishNewVersion('terms', ['es' => ['title' => 'Términos', 'body' => 'v3']]);
check('Nueva versión invalida consentimiento previo', !$consent->hasAcceptedCurrent($user->id, 'terms'));

echo "\nResultado: \033[32m{$passed} OK\033[0m" . ($failed > 0 ? ", \033[31m{$failed} FALLOS\033[0m" : '') . "\n";
exit($failed > 0 ? 1 : 0);
