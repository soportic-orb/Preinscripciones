<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Migración 003 — Bloque B: catálogo formativo (cursos, ediciones, requisitos
 * documentales) y preinscripciones (con documentos, tutor legal e historial de
 * estados para la máquina de estados).
 */
return [
    'up' => function (Database $db): void {
        $mysql = $db->driver() !== 'sqlite';
        $p = $db->prefix();
        $pk = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $fk = $mysql ? 'INT UNSIGNED' : 'INTEGER';
        $engine = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $dt = $mysql ? 'DATETIME' : 'TEXT';
        $dec = $mysql ? 'DECIMAL(10,2)' : 'REAL';
        $idx = $mysql ? '' : 'IF NOT EXISTS ';

        // --- Cursos ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}courses (
            id {$pk},
            code VARCHAR(40) NOT NULL,
            title TEXT NOT NULL,
            description TEXT NULL,
            access_requirements TEXT NULL,
            course_type VARCHAR(20) NOT NULL DEFAULT 'reglado',
            area VARCHAR(80) NULL,
            prerequisite_course_id {$fk} NULL,
            price {$dec} NULL,
            is_active TINYINT NOT NULL DEFAULT 1,
            created_at {$dt} NOT NULL,
            updated_at {$dt} NULL
        ){$engine}");
        $db->pdo()->exec("CREATE UNIQUE INDEX {$idx}{$p}courses_code ON {$p}courses (code)");

        // --- Convocatorias / ediciones ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}course_editions (
            id {$pk},
            course_id {$fk} NOT NULL,
            name VARCHAR(120) NOT NULL,
            start_date {$dt} NULL,
            end_date {$dt} NULL,
            schedule VARCHAR(150) NULL,
            modality VARCHAR(20) NOT NULL DEFAULT 'presencial',
            location VARCHAR(150) NULL,
            price {$dec} NULL,
            capacity INT NOT NULL DEFAULT 0,
            waitlist_enabled TINYINT NOT NULL DEFAULT 1,
            payment_methods TEXT NULL,
            preinscription_open_at {$dt} NULL,
            preinscription_close_at {$dt} NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            created_at {$dt} NOT NULL,
            updated_at {$dt} NULL
        ){$engine}");
        $db->pdo()->exec("CREATE INDEX {$idx}{$p}editions_course ON {$p}course_editions (course_id)");

        // --- Requisitos documentales (por curso o por edición) ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}document_requirements (
            id {$pk},
            course_id {$fk} NULL,
            edition_id {$fk} NULL,
            name TEXT NOT NULL,
            description TEXT NULL,
            is_required TINYINT NOT NULL DEFAULT 1,
            has_expiry TINYINT NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_at {$dt} NOT NULL
        ){$engine}");

        // --- Preinscripciones (máquina de estados) ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}preinscriptions (
            id {$pk},
            user_id {$fk} NOT NULL,
            edition_id {$fk} NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'borrador',
            is_minor TINYINT NOT NULL DEFAULT 0,
            wizard_step INT NOT NULL DEFAULT 1,
            waitlist_position INT NULL,
            reject_reason TEXT NULL,
            decided_by {$fk} NULL,
            decided_at {$dt} NULL,
            submitted_at {$dt} NULL,
            created_at {$dt} NOT NULL,
            updated_at {$dt} NULL
        ){$engine}");
        $db->pdo()->exec("CREATE INDEX {$idx}{$p}preinscr_user ON {$p}preinscriptions (user_id)");
        $db->pdo()->exec("CREATE INDEX {$idx}{$p}preinscr_edition ON {$p}preinscriptions (edition_id)");

        // --- Documentos subidos por preinscripción ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}preinscription_documents (
            id {$pk},
            preinscription_id {$fk} NOT NULL,
            requirement_id {$fk} NULL,
            file_path VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            mime VARCHAR(100) NULL,
            size_bytes {$fk} NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            reject_reason TEXT NULL,
            expires_at {$dt} NULL,
            validated_by {$fk} NULL,
            validated_at {$dt} NULL,
            uploaded_at {$dt} NOT NULL
        ){$engine}");
        $db->pdo()->exec("CREATE INDEX {$idx}{$p}preinscrdoc_pre ON {$p}preinscription_documents (preinscription_id)");

        // --- Tutor legal (para menores) ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}guardians (
            id {$pk},
            preinscription_id {$fk} NOT NULL,
            name VARCHAR(150) NOT NULL,
            dni VARCHAR(40) NULL,
            email VARCHAR(190) NULL,
            phone VARCHAR(40) NULL,
            relationship VARCHAR(60) NULL,
            consent_1 TINYINT NOT NULL DEFAULT 0,
            consent_2 TINYINT NOT NULL DEFAULT 0,
            created_at {$dt} NOT NULL
        ){$engine}");
        $db->pdo()->exec("CREATE INDEX {$idx}{$p}guardian_pre ON {$p}guardians (preinscription_id)");

        // --- Historial de transiciones de estado ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}preinscription_status_history (
            id {$pk},
            preinscription_id {$fk} NOT NULL,
            from_status VARCHAR(30) NULL,
            to_status VARCHAR(30) NOT NULL,
            actor_id {$fk} NULL,
            note TEXT NULL,
            created_at {$dt} NOT NULL
        ){$engine}");
        $db->pdo()->exec("CREATE INDEX {$idx}{$p}statushist_pre ON {$p}preinscription_status_history (preinscription_id)");
    },
    'down' => function (Database $db): void {
        $p = $db->prefix();
        foreach ([
            'preinscription_status_history', 'guardians', 'preinscription_documents',
            'preinscriptions', 'document_requirements', 'course_editions', 'courses',
        ] as $t) {
            $db->pdo()->exec("DROP TABLE IF EXISTS {$p}{$t}");
        }
    },
];
