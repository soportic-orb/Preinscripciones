<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Shell;

/**
 * Ajustes → Sistema. Widget post-instalación: versión/commit, estado de
 * integraciones, capacidades del servidor. Se ampliará con OTA y migración.
 */
final class SystemController extends Controller
{
    public function index(Request $request): never
    {
        $commit = null;
        if (Shell::isGitRepo() && Shell::hasGit()) {
            [$code, $out] = Shell::exec(['git', 'rev-parse', '--short', 'HEAD']);
            $commit = $code === 0 ? $out : null;
        }

        $this->view('system/index', [
            'title' => __('system.title'),
            'user' => Auth::user(),
            'info' => [
                'php_version' => PHP_VERSION,
                'app_version' => trim((string) @file_get_contents(BASE_PATH . '/VERSION')) ?: 'dev',
                'commit' => $commit,
                'git_available' => Shell::hasGit(),
                'mysqldump_available' => Shell::hasMysqldump(),
                'can_exec' => Shell::canExec(),
                'db_driver' => Database::instance()->driver(),
            ],
        ]);
    }
}
