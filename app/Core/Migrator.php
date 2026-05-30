<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sistema de migraciones versionado propio.
 *
 * Cada migración es un archivo database/migrations/NNN_descripcion.php que
 * devuelve un array con claves 'up' (callable) y, opcionalmente, 'down'.
 * El estado se registra en la tabla schema_migrations.
 */
final class Migrator
{
    public function __construct(private Database $db)
    {
    }

    private function ensureTable(): void
    {
        $prefix = $this->db->prefix();
        $driver = $this->db->driver();
        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS {$prefix}schema_migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                batch INTEGER NOT NULL,
                applied_at TEXT NOT NULL
            )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS {$prefix}schema_migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT UNSIGNED NOT NULL,
                applied_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }
        $this->db->pdo()->exec($sql);
    }

    /** @return array<int,string> nombres de migraciones ya aplicadas */
    public function applied(): array
    {
        $this->ensureTable();
        $rows = $this->db->fetchAll('SELECT migration FROM {schema_migrations} ORDER BY migration');
        return array_column($rows, 'migration');
    }

    /** @return array<int,string> rutas de archivos de migración disponibles */
    public function available(): array
    {
        $files = glob(DATABASE_PATH . '/migrations/*.php') ?: [];
        sort($files);
        return $files;
    }

    /** @return array<int,string> migraciones pendientes (nombres) */
    public function pending(): array
    {
        $applied = $this->applied();
        $pending = [];
        foreach ($this->available() as $file) {
            $name = basename($file, '.php');
            if (!in_array($name, $applied, true)) {
                $pending[] = $name;
            }
        }
        return $pending;
    }

    /**
     * Ejecuta las migraciones pendientes.
     *
     * @param callable(string):void|null $onEach callback de progreso por migración
     * @return array<int,string> migraciones aplicadas en esta ejecución
     */
    public function migrate(?callable $onEach = null): array
    {
        $this->ensureTable();
        $applied = $this->applied();
        $batch = (int) ($this->db->scalar('SELECT COALESCE(MAX(batch),0) FROM {schema_migrations}') ?? 0) + 1;
        $ran = [];

        foreach ($this->available() as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $applied, true)) {
                continue;
            }
            $migration = require $file;
            if (!is_array($migration) || !isset($migration['up']) || !is_callable($migration['up'])) {
                throw new \RuntimeException("Migración inválida: {$name}");
            }
            $migration['up']($this->db);
            $this->db->insert('schema_migrations', [
                'migration' => $name,
                'batch' => $batch,
                'applied_at' => date('Y-m-d H:i:s'),
            ]);
            $ran[] = $name;
            if ($onEach !== null) {
                $onEach($name);
            }
        }
        return $ran;
    }
}
