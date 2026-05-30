<?php

/**
 * Punto de entrada de tareas programadas (cron).
 *
 * Configurar en el servidor, p. ej.:
 *   * /5 * * * * php /ruta/cli/console.php cron >> storage/logs/cron.log 2>&1
 *
 * En esta fase deja registrada su ejecución; los recordatorios (pagos,
 * documentación, inicio de curso, cierre de plazo) se añaden en su fase.
 */

declare(strict_types=1);

use App\Core\Logger;

Logger::info('Cron ejecutado.', ['ts' => date('c')], 'cron');
echo "[cron] OK " . date('c') . "\n";
