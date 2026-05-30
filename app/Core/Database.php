<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Capa de acceso a datos sobre PDO (MySQL en producción; sqlite soportado para tests).
 *
 * Singleton perezoso. Aplica el prefijo de tabla mediante el marcador {tabla}
 * en las consultas: "SELECT * FROM {users}" => "SELECT * FROM prefijo_users".
 */
final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;
    private string $prefix;
    private string $driver;

    private function __construct(PDO $pdo, string $prefix, string $driver)
    {
        $this->pdo = $pdo;
        $this->prefix = $prefix;
        $this->driver = $driver;
    }

    public static function instance(): Database
    {
        if (self::$instance === null) {
            self::$instance = self::connectFromConfig();
        }
        return self::$instance;
    }

    /** Permite inyectar una conexión (tests). */
    public static function setInstance(?Database $db): void
    {
        self::$instance = $db;
    }

    public static function connectFromConfig(): Database
    {
        $c = Config::db();
        return self::connect(
            (string) $c['driver'],
            (string) $c['host'],
            (int) $c['port'],
            (string) $c['name'],
            (string) $c['user'],
            (string) $c['pass'],
            (string) $c['prefix'],
            (string) $c['charset'],
        );
    }

    public static function connect(
        string $driver,
        string $host,
        int $port,
        string $name,
        string $user,
        string $pass,
        string $prefix = '',
        string $charset = 'utf8mb4',
    ): Database {
        if ($driver === 'sqlite') {
            $dsn = 'sqlite:' . ($name === ':memory:' ? ':memory:' : $name);
            $pdo = new PDO($dsn, null, null, self::pdoOptions());
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $dsn = sprintf('%s:host=%s;port=%d;dbname=%s;charset=%s', $driver, $host, $port, $name, $charset);
            $pdo = new PDO($dsn, $user, $pass, self::pdoOptions());
        }
        return new Database($pdo, $prefix, $driver);
    }

    /** @return array<int,mixed> */
    private static function pdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    /** Sustituye los marcadores {tabla} por el nombre real con prefijo. */
    public function expand(string $sql): string
    {
        return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', fn ($m) => $this->prefix . $m[1], $sql) ?? $sql;
    }

    /** @param array<string,mixed>|array<int,mixed> $params */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($this->expand($sql));
        $stmt->execute($params);
        return $stmt;
    }

    /** @return array<string,mixed>|null */
    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function scalar(string $sql, array $params = []): mixed
    {
        return $this->run($sql, $params)->fetchColumn();
    }

    /** Inserta una fila y devuelve el id. @param array<string,mixed> $data */
    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn ($c) => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->prefix . $table,
            implode(', ', $cols),
            implode(', ', $placeholders),
        );
        $this->run($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $where */
    public function update(string $table, array $data, array $where): int
    {
        $set = implode(', ', array_map(fn ($c) => "$c = :set_$c", array_keys($data)));
        $cond = implode(' AND ', array_map(fn ($c) => "$c = :w_$c", array_keys($where)));
        $params = [];
        foreach ($data as $k => $v) {
            $params["set_$k"] = $v;
        }
        foreach ($where as $k => $v) {
            $params["w_$k"] = $v;
        }
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $this->prefix . $table, $set, $cond);
        return $this->run($sql, $params)->rowCount();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function tableExists(string $table): bool
    {
        $full = $this->prefix . $table;
        try {
            if ($this->driver === 'sqlite') {
                $r = $this->scalar("SELECT name FROM sqlite_master WHERE type='table' AND name = ?", [$full]);
                return $r !== false && $r !== null;
            }
            $this->run("SELECT 1 FROM `$full` LIMIT 1");
            return true;
        } catch (PDOException) {
            return false;
        }
    }
}
