<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Auditoría inmutable de acciones sensibles.
 *
 * Solo inserta (nunca actualiza ni borra desde la app). Encadena un hash con
 * el registro anterior para detectar manipulaciones (tamper-evidence).
 */
final class Audit
{
    /**
     * @param array<string,mixed> $meta
     */
    public static function log(
        string $action,
        ?int $actorId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        array $meta = [],
        ?string $ip = null,
    ): void {
        try {
            $db = Database::instance();
            if (!$db->tableExists('audit_log')) {
                return;
            }
            $prev = $db->scalar('SELECT row_hash FROM {audit_log} ORDER BY id DESC LIMIT 1');
            $prevHash = is_string($prev) ? $prev : str_repeat('0', 64);

            $payload = [
                'actor_id' => $actorId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'ip' => $ip,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $payload['row_hash'] = hash('sha256', $prevHash . json_encode($payload, JSON_UNESCAPED_UNICODE));
            $payload['prev_hash'] = $prevHash;

            $db->insert('audit_log', $payload);
        } catch (\Throwable $e) {
            Logger::error('No se pudo registrar auditoría: ' . $e->getMessage(), ['action' => $action]);
        }
    }
}
