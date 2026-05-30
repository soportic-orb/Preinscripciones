<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Maintenance;
use App\Core\Migrator;
use App\Core\Shell;

/**
 * Actualizaciones OTA basadas en Git. Requiere que el directorio sea un clon
 * con remoto y que el servidor permita ejecutar git (VPS). En hosting sin shell
 * la OTA automática no está disponible y se informa de ello.
 */
final class UpdateService
{
    public function available(): bool
    {
        return Shell::isGitRepo() && Shell::hasGit();
    }

    public function currentCommit(): ?string
    {
        [$code, $out] = Shell::exec(['git', 'rev-parse', 'HEAD']);
        return $code === 0 ? $out : null;
    }

    public function branch(): string
    {
        return Env::get('GIT_BRANCH', 'main') ?: 'main';
    }

    /**
     * Comprueba si hay commits nuevos en el remoto. Devuelve estado y changelog.
     *
     * @return array{available:bool,behind:int,changelog:array<int,string>,error?:string}
     */
    public function check(): array
    {
        if (!$this->available()) {
            return ['available' => false, 'behind' => 0, 'changelog' => [], 'error' => 'git_unavailable'];
        }
        $branch = $this->branch();
        [$code, , $err] = Shell::exec(['git', 'fetch', '--quiet', 'origin', $branch]);
        if ($code !== 0) {
            return ['available' => false, 'behind' => 0, 'changelog' => [], 'error' => $err ?: 'fetch_failed'];
        }
        [$c2, $count] = Shell::exec(['git', 'rev-list', '--count', 'HEAD..origin/' . $branch]);
        $behind = $c2 === 0 ? (int) $count : 0;
        $changelog = [];
        if ($behind > 0) {
            [$c3, $log] = Shell::exec(['git', 'log', '--pretty=%h %s', 'HEAD..origin/' . $branch]);
            if ($c3 === 0) {
                $changelog = array_filter(explode("\n", $log));
            }
        }
        return ['available' => $behind > 0, 'behind' => $behind, 'changelog' => $changelog];
    }

    /**
     * Aplica la actualización con salvaguardas: mantenimiento, backup de BD,
     * git reset al remoto, migraciones, limpieza; rollback ante fallo.
     *
     * @return array{ok:bool,message:string}
     */
    public function update(?int $actorId = null): array
    {
        if (!$this->available()) {
            return ['ok' => false, 'message' => 'git_unavailable'];
        }
        $branch = $this->branch();
        $previous = $this->currentCommit();
        Maintenance::enable('OTA update');
        Logger::info('OTA: inicio', ['from' => $previous], 'update');

        try {
            // 1) Backup de BD (best-effort).
            $backup = (new MigrationService())->dumpDatabase(STORAGE_PATH . '/backups/pre-update-' . date('Ymd-His') . '.sql');
            Logger::info('OTA: backup', ['file' => $backup], 'update');

            // 2) Traer y aplicar el remoto.
            [$fc] = Shell::exec(['git', 'fetch', 'origin', $branch]);
            if ($fc !== 0) {
                throw new \RuntimeException('git fetch falló');
            }
            [$rc, , $rerr] = Shell::exec(['git', 'reset', '--hard', 'origin/' . $branch]);
            if ($rc !== 0) {
                throw new \RuntimeException('git reset falló: ' . $rerr);
            }

            // 3) Migraciones pendientes.
            (new Migrator(Database::instance()))->migrate();

            // 4) Limpiar caché.
            $this->clearCache();

            Maintenance::disable();
            Audit::log('system.updated', $actorId, 'system', null, ['from' => $previous, 'branch' => $branch]);
            Logger::info('OTA: completado', [], 'update');
            return ['ok' => true, 'message' => 'updated'];
        } catch (\Throwable $e) {
            // Rollback al commit anterior.
            if ($previous !== null) {
                Shell::exec(['git', 'reset', '--hard', $previous]);
            }
            Maintenance::disable();
            Logger::error('OTA: fallo, rollback: ' . $e->getMessage(), [], 'update');
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function clearCache(): void
    {
        $dir = STORAGE_PATH . '/cache';
        foreach (glob($dir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }
    }
}
