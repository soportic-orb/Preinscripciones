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

// ====================== Bloque B ======================
echo "\n== Tests Bloque B ==\n";

// --- Máquina de estados ---
check('Transición válida borrador→preinscrito', App\Services\PreinscriptionStatus::canTransition('borrador', 'preinscrito'));
check('Transición inválida borrador→matriculado', !App\Services\PreinscriptionStatus::canTransition('borrador', 'matriculado'));
check('Transición válida aceptado→pendiente_pago', App\Services\PreinscriptionStatus::canTransition('aceptado', 'pendiente_pago'));
check('Estado rechazado es terminal', App\Services\PreinscriptionStatus::transitions()['rechazado'] === []);

// --- Catálogo + aforo + lista de espera ---
$courseId = App\Models\Course::store([
    'code' => 'C1', 'title' => json_encode(['es' => 'Curso 1']), 'description' => '{}',
    'access_requirements' => '{}', 'course_type' => 'reglado', 'price' => 100, 'is_active' => 1,
]);
$edId = App\Models\CourseEdition::store([
    'course_id' => $courseId, 'name' => 'Ed1', 'modality' => 'online', 'capacity' => 1,
    'waitlist_enabled' => 1, 'payment_methods' => '[]', 'status' => 'open',
    'preinscription_open_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
    'preinscription_close_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
]);
$edition = App\Models\CourseEdition::findWithCourse($edId);
check('Edición abierta detectada', App\Models\CourseEdition::isOpenNow($edition));
check('Precio efectivo hereda del curso', App\Models\CourseEdition::effectivePrice($edition) === 100.0);

// Dos estudiantes preinscritos; capacidad = 1.
$u1 = App\Models\User::create(['name' => 'A', 'email' => 'a@t.com', 'password' => 'Abcdef12345!', 'role' => 'estudiante', 'locale' => 'es', 'is_active' => 1]);
$u2 = App\Models\User::create(['name' => 'B', 'email' => 'b@t.com', 'password' => 'Abcdef12345!', 'role' => 'estudiante', 'locale' => 'es', 'is_active' => 1]);
$p1 = App\Models\Preinscription::store(['user_id' => $u1->id, 'edition_id' => $edId, 'status' => 'preinscrito']);
$p2 = App\Models\Preinscription::store(['user_id' => $u2->id, 'edition_id' => $edId, 'status' => 'preinscrito']);

$svc = new App\Services\PreinscriptionService();
check('Hay plazas libres al inicio', $svc->hasFreeSeats($edId));
$r1 = $svc->accept($p1);
check('Primer aceptado ocupa plaza', $r1 === 'aceptado');
check('Sin plazas tras aceptar a uno', !$svc->hasFreeSeats($edId));
$r2 = $svc->accept($p2);
check('Segundo va a lista de espera', $r2 === 'en_lista_de_espera');
check('occupiedSeats cuenta 1', App\Models\Preinscription::occupiedSeats($edId) === 1);

// Cancelar al aceptado libera plaza y promueve al de lista de espera.
check('Aceptado no puede ir a rechazado', !App\Services\PreinscriptionStatus::canTransition('aceptado', 'rechazado'));
$svc->transition($p1, 'cancelado', null, 'baja');
$p2row = App\Models\Preinscription::find($p2);
check('Promoción automática de lista de espera', $p2row['status'] === 'aceptado');

// Historial de estados registrado.
$hist = $db->fetchAll('SELECT * FROM {preinscription_status_history} WHERE preinscription_id = ?', [$p2]);
check('Historial de estados registrado', count($hist) >= 1);

// existsActive y draftFor.
check('existsActive detecta preinscripción activa', App\Models\Preinscription::existsActive($u2->id, $edId));

// --- Validación documental completa ---
$reqId = App\Models\DocumentRequirement::store(['course_id' => null, 'edition_id' => $edId, 'name' => '{"es":"DNI"}', 'description' => '{}', 'is_required' => 1, 'has_expiry' => 0, 'sort_order' => 0]);
$docSvc = new App\Services\DocumentService();
check('Falta documentación obligatoria', !$docSvc->allRequiredValidated($p2, $edId, $courseId));
$db->insert('preinscription_documents', ['preinscription_id' => $p2, 'requirement_id' => $reqId, 'file_path' => 'x', 'original_name' => 'x.pdf', 'status' => 'validado', 'uploaded_at' => date('Y-m-d H:i:s')]);
check('Documentación obligatoria validada', $docSvc->allRequiredValidated($p2, $edId, $courseId));

// ====================== Bloque C ======================
echo "\n== Tests Bloque C ==\n";

$cidP = App\Models\Course::store(['code' => 'PAY', 'title' => '{"es":"Pago"}', 'description' => '{}', 'access_requirements' => '{}', 'course_type' => 'reglado', 'price' => 200, 'is_active' => 1]);
$edP = App\Models\CourseEdition::store(['course_id' => $cidP, 'name' => 'EdP', 'modality' => 'online', 'capacity' => 0, 'waitlist_enabled' => 0, 'payment_methods' => '[]', 'status' => 'open']);
$uP = App\Models\User::create(['name' => 'Pay', 'email' => 'pay@t.com', 'password' => 'Abcdef12345!', 'role' => 'estudiante', 'locale' => 'es', 'is_active' => 1]);
$pP = App\Models\Preinscription::store(['user_id' => $uP->id, 'edition_id' => $edP, 'status' => 'pendiente_pago']);

$paySvc = new App\Services\PaymentService();
$paymentsC = $paySvc->ensurePayments($pP);
check('ensurePayments crea un pago único', count($paymentsC) === 1);
check('Importe del pago = precio de la edición', (float) $paymentsC[0]['amount'] === 200.0);
check('ensurePayments es idempotente', count($paySvc->ensurePayments($pP)) === 1);

App\Models\Discount::store(['code' => 'BECA10', 'name' => 'Beca 10%', 'type' => 'percent', 'value' => 10, 'scope' => 'all', 'max_uses' => 0, 'used_count' => 0, 'is_active' => 1]);
$res = $paySvc->applyDiscountCode($pP, 'BECA10');
check('Código de descuento válido se aplica', $res['ok'] === true);
$paymentC = App\Models\Payment::forPreinscription($pP)[0];
check('Descuento del 10% sobre 200 = 20', (float) $paymentC['discount_amount'] === 20.0);
check('Neto tras descuento = 180', App\Models\Payment::netAmount($paymentC) === 180.0);
check('Código inválido se rechaza', $paySvc->applyDiscountCode($pP, 'NOEXISTE')['ok'] === false);
check('Descuento no se aplica dos veces', $paySvc->applyDiscountCode($pP, 'BECA10')['ok'] === false);

$paySvc->markPaid((int) $paymentC['id'], 'stripe', null, 'TEST');
$paymentC = App\Models\Payment::find((int) $paymentC['id']);
check('Pago marcado como pagado', $paymentC['status'] === 'pagado');
$inv = $db->fetch('SELECT * FROM {invoices} WHERE payment_id = ?', [$paymentC['id']]);
check('Factura emitida tras el cobro', $inv !== null);
check('Número de factura correlativo (1)', (int) $inv['number'] === 1);
check('Factura exenta de IVA por defecto y total 180', (int) $inv['is_exempt'] === 1 && (float) $inv['total'] === 180.0);
check('Preinscripción pasa a matriculado al pagar todo', App\Models\Preinscription::find($pP)['status'] === 'matriculado');

$cidP2 = App\Models\Course::store(['code' => 'PAY2', 'title' => '{"es":"P2"}', 'description' => '{}', 'access_requirements' => '{}', 'course_type' => 'reglado', 'price' => 50, 'is_active' => 1]);
$edP2 = App\Models\CourseEdition::store(['course_id' => $cidP2, 'name' => 'EdP2', 'modality' => 'online', 'capacity' => 0, 'payment_methods' => '[]', 'status' => 'open']);
$pP2 = App\Models\Preinscription::store(['user_id' => $uP->id, 'edition_id' => $edP2, 'status' => 'pendiente_pago']);
$pay2 = $paySvc->ensurePayments($pP2)[0];
$paySvc->markPaid((int) $pay2['id'], 'transfer', null, 'T2');
$inv2 = $db->fetch('SELECT * FROM {invoices} WHERE payment_id = ?', [$pay2['id']]);
check('Segunda factura correlativa (2)', (int) $inv2['number'] === 2);

$paySvc->refund((int) $paymentC['id'], 180.0, 'baja', null);
check('Pago marcado como reembolsado', App\Models\Payment::find((int) $paymentC['id'])['status'] === 'reembolsado');
$credit = $db->fetch("SELECT * FROM {invoices} WHERE payment_id = ? AND type = 'credit_note'", [$paymentC['id']]);
check('Nota de crédito emitida', $credit !== null && (float) $credit['total'] === -180.0);

App\Models\BillingProfile::save($uP->id, ['name' => 'Empresa SL', 'tax_id' => 'B123', 'is_company' => 1]);
check('Perfil fiscal se guarda', App\Models\BillingProfile::forUser($uP->id)['name'] === 'Empresa SL');
App\Models\BillingProfile::save($uP->id, ['name' => 'Empresa SLU', 'tax_id' => 'B123', 'is_company' => 1]);
check('Perfil fiscal se actualiza (no duplica)', count($db->fetchAll('SELECT id FROM {billing_profiles} WHERE user_id = ?', [$uP->id])) === 1);

$db->update('course_editions', ['allow_installments' => 1, 'installments_count' => 3, 'deposit' => 60], ['id' => $edP2]);
$pP3 = App\Models\Preinscription::store(['user_id' => $uP->id, 'edition_id' => $edP2, 'status' => 'pendiente_pago']);
$sched = $paySvc->ensurePayments($pP3);
check('Pago fraccionado genera 3 cobros', count($sched) === 3);
check('Suma del fraccionado = precio total', round(array_sum(array_map(fn ($p) => (float) $p['amount'], $sched)), 2) === 50.0);

echo "\nResultado: \033[32m{$passed} OK\033[0m" . ($failed > 0 ? ", \033[31m{$failed} FALLOS\033[0m" : '') . "\n";
exit($failed > 0 ? 1 : 0);
