<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Migración 002 — Fase 2: campos dinámicos, valores, textos legales y
 * consentimientos versionados. (settings ya se creó en la migración 001.)
 */
return [
    'up' => function (Database $db): void {
        $mysql = $db->driver() !== 'sqlite';
        $p = $db->prefix();
        $pk = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $fk = $mysql ? 'INT UNSIGNED' : 'INTEGER';
        $engine = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $dt = $mysql ? 'DATETIME' : 'TEXT';

        // --- Definición de campos dinámicos ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}field_definitions (
            id {$pk},
            form_key VARCHAR(60) NOT NULL,
            section VARCHAR(60) NOT NULL DEFAULT 'general',
            field_key VARCHAR(80) NOT NULL,
            type VARCHAR(30) NOT NULL DEFAULT 'text',
            label TEXT NOT NULL,
            help TEXT NULL,
            placeholder TEXT NULL,
            options TEXT NULL,
            validations TEXT NULL,
            is_required TINYINT NOT NULL DEFAULT 0,
            is_active TINYINT NOT NULL DEFAULT 1,
            is_system TINYINT NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_at {$dt} NOT NULL,
            updated_at {$dt} NULL
        ){$engine}");
        $idx = $mysql ? '' : 'IF NOT EXISTS ';
        $db->pdo()->exec("CREATE UNIQUE INDEX {$idx}{$p}fielddef_unique ON {$p}field_definitions (form_key, field_key)");

        // --- Valores de campos dinámicos ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}field_values (
            id {$pk},
            field_id {$fk} NOT NULL,
            entity_type VARCHAR(40) NOT NULL,
            entity_id {$fk} NOT NULL,
            value TEXT NULL,
            created_at {$dt} NOT NULL,
            updated_at {$dt} NULL
        ){$engine}");
        $db->pdo()->exec("CREATE INDEX {$idx}{$p}fieldval_entity ON {$p}field_values (entity_type, entity_id)");
        $db->pdo()->exec("CREATE UNIQUE INDEX {$idx}{$p}fieldval_unique ON {$p}field_values (field_id, entity_type, entity_id)");

        // --- Textos legales versionados ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}legal_documents (
            id {$pk},
            doc_type VARCHAR(40) NOT NULL,
            version INT NOT NULL DEFAULT 1,
            locale VARCHAR(5) NOT NULL,
            title VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            is_published TINYINT NOT NULL DEFAULT 0,
            published_at {$dt} NULL,
            created_at {$dt} NOT NULL
        ){$engine}");
        $db->pdo()->exec("CREATE UNIQUE INDEX {$idx}{$p}legal_unique ON {$p}legal_documents (doc_type, version, locale)");

        // --- Consentimientos del usuario (versionados, con timestamp e IP) ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}consents (
            id {$pk},
            user_id {$fk} NOT NULL,
            doc_type VARCHAR(40) NOT NULL,
            version INT NOT NULL,
            locale VARCHAR(5) NOT NULL,
            is_guardian TINYINT NOT NULL DEFAULT 0,
            accepted_at {$dt} NOT NULL,
            ip VARCHAR(45) NULL
        ){$engine}");
        $db->pdo()->exec("CREATE INDEX {$idx}{$p}consents_user ON {$p}consents (user_id)");
    },
    'down' => function (Database $db): void {
        $p = $db->prefix();
        foreach (['consents', 'legal_documents', 'field_values', 'field_definitions'] as $t) {
            $db->pdo()->exec("DROP TABLE IF EXISTS {$p}{$t}");
        }
    },
];
