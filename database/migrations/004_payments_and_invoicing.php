<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Migración 004 — Bloque C: pagos, descuentos/becas, perfiles fiscales,
 * facturas (numeración correlativa), reembolsos y datos FUNDAE.
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

        // Pago fraccionado a nivel de edición.
        foreach ([
            'allow_installments TINYINT NOT NULL DEFAULT 0',
            'installments_count INT NOT NULL DEFAULT 1',
            "deposit {$dec} NULL",
        ] as $col) {
            try {
                $db->pdo()->exec("ALTER TABLE {$p}course_editions ADD COLUMN {$col}");
            } catch (\Throwable) {
                // columna ya existente: ignorar
            }
        }

        // --- Pagos ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}payments (
            id {$pk},
            preinscription_id {$fk} NOT NULL,
            user_id {$fk} NOT NULL,
            concept VARCHAR(60) NOT NULL DEFAULT 'matricula',
            sequence INT NOT NULL DEFAULT 1,
            amount {$dec} NOT NULL,
            discount_amount {$dec} NOT NULL DEFAULT 0,
            currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
            method VARCHAR(20) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            reference VARCHAR(120) NULL,
            proof_path VARCHAR(255) NULL,
            stripe_intent VARCHAR(120) NULL,
            due_date {$dt} NULL,
            paid_at {$dt} NULL,
            validated_by {$fk} NULL,
            validated_at {$dt} NULL,
            created_at {$dt} NOT NULL,
            updated_at {$dt} NULL
        ){$engine}");
        $db->pdo()->exec("CREATE INDEX {$idx}{$p}payments_pre ON {$p}payments (preinscription_id)");
        $db->pdo()->exec("CREATE INDEX {$idx}{$p}payments_user ON {$p}payments (user_id)");

        // --- Descuentos / becas / códigos promocionales ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}discounts (
            id {$pk},
            code VARCHAR(40) NULL,
            name VARCHAR(120) NOT NULL,
            type VARCHAR(10) NOT NULL DEFAULT 'percent',
            value {$dec} NOT NULL,
            scope VARCHAR(20) NOT NULL DEFAULT 'all',
            scope_id {$fk} NULL,
            valid_from {$dt} NULL,
            valid_to {$dt} NULL,
            max_uses INT NOT NULL DEFAULT 0,
            used_count INT NOT NULL DEFAULT 0,
            is_active TINYINT NOT NULL DEFAULT 1,
            created_at {$dt} NOT NULL
        ){$engine}");
        $db->pdo()->exec("CREATE INDEX {$idx}{$p}discounts_code ON {$p}discounts (code)");

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}discount_redemptions (
            id {$pk},
            discount_id {$fk} NOT NULL,
            preinscription_id {$fk} NOT NULL,
            user_id {$fk} NOT NULL,
            amount_applied {$dec} NOT NULL,
            created_at {$dt} NOT NULL
        ){$engine}");

        // --- Perfiles fiscales (estudiante o empresa pagadora) ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}billing_profiles (
            id {$pk},
            user_id {$fk} NOT NULL,
            is_company TINYINT NOT NULL DEFAULT 0,
            name VARCHAR(150) NOT NULL,
            tax_id VARCHAR(40) NULL,
            address VARCHAR(200) NULL,
            city VARCHAR(100) NULL,
            postal_code VARCHAR(20) NULL,
            country VARCHAR(60) NULL,
            email VARCHAR(190) NULL,
            created_at {$dt} NOT NULL,
            updated_at {$dt} NULL
        ){$engine}");
        $db->pdo()->exec("CREATE INDEX {$idx}{$p}billing_user ON {$p}billing_profiles (user_id)");

        // --- Facturas / recibos / abonos (numeración correlativa) ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}invoices (
            id {$pk},
            series VARCHAR(10) NOT NULL DEFAULT 'A',
            number INT NOT NULL,
            full_number VARCHAR(40) NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'invoice',
            payment_id {$fk} NULL,
            preinscription_id {$fk} NULL,
            user_id {$fk} NOT NULL,
            billing_snapshot TEXT NULL,
            subtotal {$dec} NOT NULL,
            tax_rate {$dec} NOT NULL DEFAULT 0,
            tax_amount {$dec} NOT NULL DEFAULT 0,
            total {$dec} NOT NULL,
            is_exempt TINYINT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'issued',
            pdf_path VARCHAR(255) NULL,
            issued_at {$dt} NOT NULL,
            created_at {$dt} NOT NULL
        ){$engine}");
        $db->pdo()->exec("CREATE UNIQUE INDEX {$idx}{$p}invoices_number ON {$p}invoices (series, number, type)");
        $db->pdo()->exec("CREATE INDEX {$idx}{$p}invoices_user ON {$p}invoices (user_id)");

        // Contadores de numeración por serie/año/tipo.
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}invoice_counters (
            id {$pk},
            series VARCHAR(10) NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'invoice',
            last_number INT NOT NULL DEFAULT 0
        ){$engine}");
        $db->pdo()->exec("CREATE UNIQUE INDEX {$idx}{$p}counters_series ON {$p}invoice_counters (series, type)");

        // --- Reembolsos ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}refunds (
            id {$pk},
            payment_id {$fk} NOT NULL,
            invoice_id {$fk} NULL,
            amount {$dec} NOT NULL,
            reason TEXT NULL,
            created_by {$fk} NULL,
            created_at {$dt} NOT NULL
        ){$engine}");

        // --- FUNDAE (formación bonificada) ---
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS {$p}fundae_records (
            id {$pk},
            preinscription_id {$fk} NOT NULL,
            company_name VARCHAR(150) NOT NULL,
            company_cif VARCHAR(40) NOT NULL,
            contribution_account VARCHAR(60) NULL,
            worker_name VARCHAR(150) NULL,
            worker_nif VARCHAR(40) NULL,
            created_at {$dt} NOT NULL
        ){$engine}");
        $db->pdo()->exec("CREATE INDEX {$idx}{$p}fundae_pre ON {$p}fundae_records (preinscription_id)");
    },
    'down' => function (Database $db): void {
        $p = $db->prefix();
        foreach ([
            'fundae_records', 'refunds', 'invoice_counters', 'invoices',
            'billing_profiles', 'discount_redemptions', 'discounts', 'payments',
        ] as $t) {
            $db->pdo()->exec("DROP TABLE IF EXISTS {$p}{$t}");
        }
    },
];
