<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Migración 005 — Bloque D: mensajería interna, plantillas de email editables
 * por evento/idioma y registro de recordatorios enviados (para no duplicar).
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

        // --- Hilos de mensajería ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}message_threads (
            id {$pk},
            student_id {$fk} NOT NULL,
            subject VARCHAR(200) NOT NULL,
            preinscription_id {$fk} NULL,
            last_message_at {$dt} NULL,
            created_at {$dt} NOT NULL
        ){$engine}");
        $db->pdo()->exec("CREATE INDEX {$idx}{$p}threads_student ON {$p}message_threads (student_id)");

        // --- Mensajes ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}messages (
            id {$pk},
            thread_id {$fk} NOT NULL,
            sender_id {$fk} NOT NULL,
            is_staff TINYINT NOT NULL DEFAULT 0,
            body TEXT NOT NULL,
            created_at {$dt} NOT NULL
        ){$engine}");
        $db->pdo()->exec("CREATE INDEX {$idx}{$p}messages_thread ON {$p}messages (thread_id)");

        // --- Lecturas (último visto por usuario y por hilo) ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}thread_reads (
            id {$pk},
            thread_id {$fk} NOT NULL,
            user_id {$fk} NOT NULL,
            last_read_message_id {$fk} NOT NULL DEFAULT 0,
            last_read_at {$dt} NULL
        ){$engine}");
        $db->pdo()->exec("CREATE UNIQUE INDEX {$idx}{$p}threadreads_unique ON {$p}thread_reads (thread_id, user_id)");

        // --- Plantillas de email por evento e idioma ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}email_templates (
            id {$pk},
            event VARCHAR(60) NOT NULL,
            locale VARCHAR(5) NOT NULL,
            subject VARCHAR(200) NOT NULL,
            body_html TEXT NOT NULL,
            is_active TINYINT NOT NULL DEFAULT 1,
            updated_at {$dt} NULL,
            created_at {$dt} NOT NULL
        ){$engine}");
        $db->pdo()->exec("CREATE UNIQUE INDEX {$idx}{$p}emailtpl_unique ON {$p}email_templates (event, locale)");

        // --- Recordatorios ya enviados (idempotencia del cron) ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}reminders_sent (
            id {$pk},
            reminder_key VARCHAR(120) NOT NULL,
            sent_at {$dt} NOT NULL
        ){$engine}");
        $db->pdo()->exec("CREATE UNIQUE INDEX {$idx}{$p}reminders_key ON {$p}reminders_sent (reminder_key)");
    },
    'down' => function (Database $db): void {
        $p = $db->prefix();
        foreach (['reminders_sent', 'email_templates', 'thread_reads', 'messages', 'message_threads'] as $t) {
            $db->pdo()->exec("DROP TABLE IF EXISTS {$p}{$t}");
        }
    },
];
