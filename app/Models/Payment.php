<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Pago asociado a una preinscripción (matrícula o plazo).
 */
final class Payment extends Model
{
    protected static string $table = 'payments';

    public const STATUS_PENDING = 'pendiente';
    public const STATUS_REVIEW = 'pago_en_revision';
    public const STATUS_PAID = 'pagado';
    public const STATUS_REJECTED = 'rechazado';
    public const STATUS_REFUNDED = 'reembolsado';

    public const METHODS = ['stripe', 'bizum', 'transfer'];

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::instance()->fetch('SELECT * FROM {payments} WHERE id = ?', [$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function forPreinscription(int $preinscriptionId): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM {payments} WHERE preinscription_id = ? ORDER BY sequence, id',
            [$preinscriptionId],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function forUser(int $userId): array
    {
        return Database::instance()->fetchAll(
            'SELECT p.*, c.title AS course_title, e.name AS edition_name
             FROM {payments} p
             JOIN {preinscriptions} pr ON pr.id = p.preinscription_id
             JOIN {course_editions} e ON e.id = pr.edition_id
             JOIN {courses} c ON c.id = e.course_id
             WHERE p.user_id = ? ORDER BY p.created_at DESC',
            [$userId],
        );
    }

    public static function netAmount(array $payment): float
    {
        return round((float) $payment['amount'] - (float) $payment['discount_amount'], 2);
    }

    /** ¿Todos los pagos de la preinscripción están pagados? */
    public static function allPaid(int $preinscriptionId): bool
    {
        $total = (int) Database::instance()->scalar('SELECT COUNT(*) FROM {payments} WHERE preinscription_id = ?', [$preinscriptionId]);
        if ($total === 0) {
            return false;
        }
        $paid = (int) Database::instance()->scalar(
            "SELECT COUNT(*) FROM {payments} WHERE preinscription_id = ? AND status = 'pagado'",
            [$preinscriptionId],
        );
        return $paid === $total;
    }

    /** @param array<string,mixed> $data */
    public static function store(array $data): int
    {
        return Database::instance()->insert('payments', $data + ['created_at' => date('Y-m-d H:i:s')]);
    }

    public static function update(int $id, array $data): void
    {
        Database::instance()->update('payments', $data + ['updated_at' => date('Y-m-d H:i:s')], ['id' => $id]);
    }

    /** @return array<int,array<string,mixed>> pagos en revisión (para gestión) */
    public static function inReview(): array
    {
        return Database::instance()->fetchAll(
            "SELECT p.*, u.name AS student_name, u.email AS student_email
             FROM {payments} p JOIN {users} u ON u.id = p.user_id
             WHERE p.status = 'pago_en_revision' ORDER BY p.created_at",
        );
    }
}
