<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Capa de portabilidad: detecta capacidades del servidor (exec, git, mysqldump)
 * para decidir entre rutas nativas de sistema o fallbacks 100% PHP.
 *
 * Esto permite que la plataforma funcione tanto en VPS (con shell) como en
 * hosting compartido (sin shell), tal como se acordó en el diseño.
 */
final class Shell
{
    public static function canExec(): bool
    {
        if (!function_exists('proc_open')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return !in_array('proc_open', $disabled, true);
    }

    /**
     * Ejecuta un comando de forma segura y devuelve [exitCode, stdout, stderr].
     *
     * @param array<int,string> $command
     * @return array{0:int,1:string,2:string}
     */
    public static function exec(array $command, ?string $cwd = null, ?int $timeout = 120): array
    {
        if (!self::canExec()) {
            return [127, '', 'exec no disponible'];
        }
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, $cwd ?? BASE_PATH);
        if (!is_resource($process)) {
            return [127, '', 'No se pudo iniciar el proceso'];
        }
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        return [$code, trim($stdout), trim($stderr)];
    }

    public static function hasGit(): bool
    {
        if (!self::canExec()) {
            return false;
        }
        [$code] = self::exec(['git', '--version']);
        return $code === 0;
    }

    public static function hasMysqldump(): bool
    {
        if (!self::canExec()) {
            return false;
        }
        [$code] = self::exec(['mysqldump', '--version']);
        return $code === 0;
    }

    public static function isGitRepo(): bool
    {
        return is_dir(BASE_PATH . '/.git');
    }
}
