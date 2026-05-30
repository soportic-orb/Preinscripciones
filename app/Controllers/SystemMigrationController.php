<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Services\MigrationService;

/**
 * Migración guiada (solo admin): exporta un paquete .zip para mover la
 * plataforma a otro servidor (la importación se hace desde el instalador).
 */
final class SystemMigrationController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('system/migration/index', [
            'title' => __('migration.title'),
            'user' => Auth::user(),
        ]);
    }

    public function export(Request $request): never
    {
        try {
            $zip = (new MigrationService())->exportPackage();
            Audit::log('system.migration_export', Auth::id(), 'system', null, ['file' => basename($zip)], $request->ip());
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
            $this->redirect('/gestion/sistema/migracion');
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zip) . '"');
        header('Content-Length: ' . (string) filesize($zip));
        readfile($zip);
        @unlink($zip);
        exit;
    }
}
