<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Migración 006 — Bloque E: certificados/diplomas con código de verificación.
 */
return [
    'up' => function (Database $db): void {
        $mysql = $db->driver() !== 'sqlite';
        $p = $db->prefix();
        $pk = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $fk = $mysql ? 'INT UNSIGNED' : 'INTEGER';
        $engine = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $dt = $mysql ? 'DATETIME' : 'TEXT';
        $idx = $mysql ? '' : 'IF NOT EXISTS ';

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}certificates (
            id {$pk},
            preinscription_id {$fk} NOT NULL,
            user_id {$fk} NOT NULL,
            code VARCHAR(40) NOT NULL,
            student_name VARCHAR(150) NOT NULL,
            course_name VARCHAR(200) NOT NULL,
            edition_name VARCHAR(150) NULL,
            pdf_path VARCHAR(255) NULL,
            issued_at {$dt} NOT NULL,
            created_at {$dt} NOT NULL
        ){$engine}");
        $db->pdo()->exec("CREATE UNIQUE INDEX {$idx}{$p}cert_code ON {$p}certificates (code)");
        $db->pdo()->exec("CREATE INDEX {$idx}{$p}cert_user ON {$p}certificates (user_id)");
    },
    'down' => function (Database $db): void {
        $db->pdo()->exec('DROP TABLE IF EXISTS ' . $db->prefix() . 'certificates');
    },
];
