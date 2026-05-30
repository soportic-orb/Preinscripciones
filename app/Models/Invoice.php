<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Factura, recibo o abono (nota de crédito) con numeración correlativa.
 */
final class Invoice extends Model
{
    protected static string $table = 'invoices';

    public const TYPE_INVOICE = 'invoice';
    public const TYPE_CREDIT = 'credit_note';

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::instance()->fetch('SELECT * FROM {invoices} WHERE id = ?', [$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function forUser(int $userId): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM {invoices} WHERE user_id = ? ORDER BY issued_at DESC',
            [$userId],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Database::instance()->fetchAll(
            'SELECT i.*, u.name AS student_name FROM {invoices} i JOIN {users} u ON u.id = i.user_id ORDER BY i.issued_at DESC',
        );
    }

    /**
     * Reserva el siguiente número correlativo para una serie/tipo de forma atómica.
     */
    public static function nextNumber(string $series, string $type): int
    {
        $db = Database::instance();
        $db->beginTransaction();
        try {
            $row = $db->fetch(
                'SELECT * FROM {invoice_counters} WHERE series = ? AND type = ?',
                [$series, $type],
            );
            if ($row === null) {
                $db->insert('invoice_counters', ['series' => $series, 'type' => $type, 'last_number' => 1]);
                $next = 1;
            } else {
                $next = (int) $row['last_number'] + 1;
                $db->update('invoice_counters', ['last_number' => $next], ['id' => (int) $row['id']]);
            }
            $db->commit();
            return $next;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function store(array $data): int
    {
        return Database::instance()->insert('invoices', $data + ['created_at' => date('Y-m-d H:i:s')]);
    }
}
