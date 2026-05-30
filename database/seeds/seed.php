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

echo "Seed completado.\n";
