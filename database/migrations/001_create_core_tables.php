<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Migración 001 — Tablas núcleo: usuarios, verificación de email, recuperación
 * de contraseña, auditoría inmutable y configuración (settings).
 */
return [
    'up' => function (Database $db): void {
        $mysql = $db->driver() !== 'sqlite';
        $p = $db->prefix();
        $pk = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $fk = $mysql ? 'INT UNSIGNED' : 'INTEGER';
        $engine = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $dt = $mysql ? 'DATETIME' : 'TEXT';

        // --- users ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}users (
            id {$pk},
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'estudiante',
            locale VARCHAR(5) NOT NULL DEFAULT 'es',
            email_verified_at {$dt} NULL,
            is_active TINYINT NOT NULL DEFAULT 1,
            totp_secret VARCHAR(64) NULL,
            totp_enabled TINYINT NOT NULL DEFAULT 0,
            created_at {$dt} NOT NULL,
            updated_at {$dt} NULL
        ){$engine}");
        if ($mysql) {
            $db->pdo()->exec("CREATE UNIQUE INDEX {$p}users_email_unique ON {$p}users (email)");
        } else {
            $db->pdo()->exec("CREATE UNIQUE INDEX IF NOT EXISTS {$p}users_email_unique ON {$p}users (email)");
        }

        // --- email_verifications ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}email_verifications (
            id {$pk},
            user_id {$fk} NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at {$dt} NOT NULL,
            created_at {$dt} NOT NULL
        ){$engine}");

        // --- password_resets ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}password_resets (
            id {$pk},
            user_id {$fk} NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at {$dt} NOT NULL,
            created_at {$dt} NOT NULL
        ){$engine}");

        // --- audit_log (inmutable, encadenado por hash) ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}audit_log (
            id {$pk},
            actor_id {$fk} NULL,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(60) NULL,
            entity_id {$fk} NULL,
            meta TEXT NULL,
            ip VARCHAR(45) NULL,
            prev_hash VARCHAR(64) NULL,
            row_hash VARCHAR(64) NOT NULL,
            created_at {$dt} NOT NULL
        ){$engine}");

        // --- settings (configuración en caliente) ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}settings (
            id {$pk},
            group_name VARCHAR(60) NOT NULL,
            key_name VARCHAR(100) NOT NULL,
            value TEXT NULL,
            updated_at {$dt} NULL
        ){$engine}");
        if ($mysql) {
            $db->pdo()->exec("CREATE UNIQUE INDEX {$p}settings_unique ON {$p}settings (group_name, key_name)");
        } else {
            $db->pdo()->exec("CREATE UNIQUE INDEX IF NOT EXISTS {$p}settings_unique ON {$p}settings (group_name, key_name)");
        }
    },
    'down' => function (Database $db): void {
        $p = $db->prefix();
        foreach (['settings', 'audit_log', 'password_resets', 'email_verifications', 'users'] as $t) {
            $db->pdo()->exec("DROP TABLE IF EXISTS {$p}{$t}");
        }
    },
];
