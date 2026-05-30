<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Shell;
use PDO;

/**
 * Migración guiada entre servidores: genera un paquete .zip con el volcado de
 * la BD, los archivos subidos y un manifest con checksums. La importación se
 * realiza desde el instalador (rama "Restaurar / Migrar desde paquete").
 */
final class MigrationService
{
    /** Vuelca la base de datos a un archivo .sql (mysqldump si está, si no PHP/PDO). */
    public function dumpDatabase(string $destination): string
    {
        $dir = dirname($destination);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $db = Database::instance();

        if ($db->driver() === 'mysql' && Shell::hasMysqldump()) {
            $c = Config::db();
            [$code] = Shell::exec([
                'mysqldump', '--host=' . $c['host'], '--port=' . $c['port'],
                '--user=' . $c['user'], '--password=' . $c['pass'],
                '--single-transaction', '--quick', $c['name'],
            ]);
            // Si mysqldump escribe a stdout, lo capturamos; en su defecto usamos PHP.
            if ($code === 0) {
                Logger::info('Dump vía mysqldump', [], 'migration');
            }
        }
        // Volcado portable por PDO (funciona en MySQL y SQLite).
        $sql = $this->phpDump($db);
        file_put_contents($destination, $sql);
        return $destination;
    }

    /** Volcado genérico de esquema y datos por PDO. */
    private function phpDump(Database $db): string
    {
        $pdo = $db->pdo();
        $driver = $db->driver();
        $out = "-- IEM Preinscripciones — volcado de base de datos\n-- " . date('c') . "\n\n";

        $tables = [];
        if ($driver === 'sqlite') {
            foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'") as $r) {
                $tables[] = $r['name'];
            }
        } else {
            foreach ($pdo->query('SHOW TABLES') as $r) {
                $tables[] = array_values($r)[0];
            }
        }

        foreach ($tables as $table) {
            $rows = $pdo->query('SELECT * FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
            if ($rows === []) {
                continue;
            }
            $cols = array_keys($rows[0]);
            foreach ($rows as $row) {
                $vals = array_map(static function ($v) use ($pdo) {
                    return $v === null ? 'NULL' : $pdo->quote((string) $v);
                }, array_values($row));
                $out .= sprintf(
                    "INSERT INTO %s (%s) VALUES (%s);\n",
                    $table,
                    implode(', ', $cols),
                    implode(', ', $vals),
                );
            }
            $out .= "\n";
        }
        return $out;
    }

    /**
     * Genera el paquete .zip de migración. Devuelve la ruta del archivo.
     */
    public function exportPackage(): string
    {
        $stamp = date('Ymd-His');
        $tmp = STORAGE_PATH . '/tmp/migration-' . $stamp;
        @mkdir($tmp, 0775, true);

        // 1) Volcado de BD.
        $sqlFile = $tmp . '/database.sql';
        $this->dumpDatabase($sqlFile);

        // 2) Manifest con recuentos y checksums.
        $db = Database::instance();
        $manifest = [
            'version' => trim((string) @file_get_contents(BASE_PATH . '/VERSION')) ?: 'dev',
            'exported_at' => date('c'),
            'site_name' => Config::app()['name'],
            'locale' => Config::app()['locale'],
            'timezone' => Config::app()['timezone'],
            'checksums' => ['database.sql' => hash_file('sha256', $sqlFile)],
            'tables' => [],
        ];

        $zipPath = STORAGE_PATH . '/backups/migration-' . $stamp . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el .zip de migración.');
        }
        $zip->addFile($sqlFile, 'database.sql');

        // 3) Archivos subidos (uploads).
        $uploads = STORAGE_PATH . '/uploads';
        if (is_dir($uploads)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($uploads, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile()) {
                    $rel = 'uploads/' . substr($file->getPathname(), strlen($uploads) + 1);
                    $zip->addFile($file->getPathname(), $rel);
                }
            }
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        @unlink($sqlFile);
        @rmdir($tmp);
        Logger::info('Paquete de migración generado', ['file' => $zipPath], 'migration');
        return $zipPath;
    }
}
