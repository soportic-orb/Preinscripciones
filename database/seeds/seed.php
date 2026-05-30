<?php

/**
 * Datos de ejemplo (seed) para desarrollo y demostración.
 * Idempotente: no duplica registros si ya existen.
 */

declare(strict_types=1);

use App\Core\Database;
use App\Models\User;

$db = Database::instance();

$demoUsers = [
    ['name' => 'Administrador IEM', 'email' => 'admin@iem.local', 'role' => 'owner', 'pass' => 'Admin-IEM-2026!'],
    ['name' => 'Gestor de ejemplo', 'email' => 'gestor@iem.local', 'role' => 'gestor', 'pass' => 'Gestor-IEM-2026!'],
    ['name' => 'Estudiante de ejemplo', 'email' => 'alumno@iem.local', 'role' => 'estudiante', 'pass' => 'Alumno-IEM-2026!'],
];

foreach ($demoUsers as $u) {
    if (User::emailExists($u['email'])) {
        echo "  = {$u['email']} ya existe\n";
        continue;
    }
    $user = User::create([
        'name' => $u['name'],
        'email' => $u['email'],
        'password' => $u['pass'],
        'role' => $u['role'],
        'locale' => 'es',
        'is_active' => 1,
    ]);
    $db->update('users', ['email_verified_at' => date('Y-m-d H:i:s')], ['id' => $user->id]);
    echo "  ✓ {$u['email']} ({$u['role']}) — contraseña: {$u['pass']}\n";
}

// --- Campos dinámicos de ejemplo (formulario de preinscripción) ---
$defaultFields = [
    ['preinscription', 'datos_estudiante', 'telefono', 'tel', ['es' => 'Teléfono', 'ca' => 'Telèfon', 'en' => 'Phone', 'pt' => 'Telefone'], 1, 1, 10],
    ['preinscription', 'datos_estudiante', 'fecha_nacimiento', 'date', ['es' => 'Fecha de nacimiento', 'ca' => 'Data de naixement', 'en' => 'Date of birth', 'pt' => 'Data de nascimento'], 1, 1, 20],
    ['preinscription', 'datos_estudiante', 'direccion', 'text', ['es' => 'Dirección postal', 'ca' => 'Adreça postal', 'en' => 'Postal address', 'pt' => 'Morada'], 1, 1, 30],
    ['academic', 'datos_academicos', 'titulacion', 'text', ['es' => 'Titulación de acceso', 'ca' => 'Titulació d\'accés', 'en' => 'Entry qualification', 'pt' => 'Habilitação de acesso'], 0, 1, 10],
];
foreach ($defaultFields as [$form, $section, $key, $type, $label, $required, $system, $order]) {
    $exists = $db->scalar('SELECT 1 FROM {field_definitions} WHERE form_key = ? AND field_key = ?', [$form, $key]);
    if ($exists) {
        continue;
    }
    $db->insert('field_definitions', [
        'form_key' => $form, 'section' => $section, 'field_key' => $key, 'type' => $type,
        'label' => json_encode($label, JSON_UNESCAPED_UNICODE),
        'help' => '{}', 'placeholder' => '{}', 'options' => '[]', 'validations' => '{}',
        'is_required' => $required, 'is_active' => 1, 'is_system' => $system, 'sort_order' => $order,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    echo "  ✓ campo {$form}.{$key}\n";
}

// --- Texto legal inicial (privacidad) si no hay ninguno publicado ---
if (\App\Models\LegalDocument::currentVersion('privacy') === 0) {
    \App\Models\LegalDocument::publishNewVersion('privacy', [
        'es' => ['title' => 'Política de privacidad', 'body' => 'Texto de ejemplo de la política de privacidad. Edítalo en Ajustes → Textos legales.'],
        'ca' => ['title' => 'Política de privadesa', 'body' => 'Text d\'exemple de la política de privadesa.'],
        'en' => ['title' => 'Privacy policy', 'body' => 'Sample privacy policy text.'],
        'pt' => ['title' => 'Política de privacidade', 'body' => 'Texto de exemplo da política de privacidade.'],
    ]);
    echo "  ✓ texto legal privacy v1\n";
}

echo "Seed completado.\n";
