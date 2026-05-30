<?php

/**
 * Consola CLI de la plataforma.
 *
 * Uso:
 *   php cli/console.php migrate              Ejecuta migraciones pendientes.
 *   php cli/console.php migrate:status       Muestra el estado de migraciones.
 *   php cli/console.php seed                 Carga datos de ejemplo.
 *   php cli/console.php cron                 Ejecuta tareas programadas.
 *   php cli/console.php make:admin <email>   (utilidad) marca a un usuario como admin.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Solo CLI.\n");
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Migrator;

$command = $argv[1] ?? 'help';

try {
    switch ($command) {
        case 'migrate':
            $migrator = new Migrator(Database::instance());
            $ran = $migrator->migrate(function (string $name): void {
                echo "  ✓ {$name}\n";
            });
            echo $ran === [] ? "Sin migraciones pendientes.\n" : sprintf("%d migración(es) aplicada(s).\n", count($ran));
            break;

        case 'migrate:status':
            $migrator = new Migrator(Database::instance());
            echo "Aplicadas:\n";
            foreach ($migrator->applied() as $m) {
                echo "  ✓ {$m}\n";
            }
            echo "Pendientes:\n";
            foreach ($migrator->pending() as $m) {
                echo "  • {$m}\n";
            }
            break;

        case 'seed':
            require dirname(__DIR__) . '/database/seeds/seed.php';
            break;

        case 'cron':
            require dirname(__DIR__) . '/cli/cron.php';
            break;

        case 'make:admin':
            $email = $argv[2] ?? null;
            if ($email === null) {
                throw new RuntimeException('Falta el email.');
            }
            $db = Database::instance();
            $n = $db->update('users', ['role' => 'admin'], ['email' => strtolower($email)]);
            echo $n > 0 ? "Usuario {$email} ahora es admin.\n" : "No se encontró el usuario.\n";
            break;

        default:
            echo "Comandos: migrate | migrate:status | seed | cron | make:admin <email>\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
