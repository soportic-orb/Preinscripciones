<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Models\CourseEdition;
use App\Models\Discount;
use App\Models\Payment;
use App\Models\Preinscription;
use App\Models\User;

/**
 * Lógica de pagos: generación del calendario de cobros (matrícula + plazos),
 * aplicación de descuentos, validación de justificantes, cobro y reembolsos.
 */
final class PaymentService
{
    /**
     * Genera los pagos de una preinscripción a partir del precio de la edición.
     * Idempotente: no crea pagos si ya existen.
     *
     * @return array<int,array<string,mixed>>
     */
    public function ensurePayments(int $preinscriptionId): array
    {
        $existing = Payment::forPreinscription($preinscriptionId);
        if ($existing !== []) {
            return $existing;
        }
        $pre = Preinscription::find($preinscriptionId);
        if ($pre === null) {
            return [];
        }
        $edition = CourseEdition::findWithCourse((int) $pre['edition_id']);
        $price = $edition !== null ? (CourseEdition::effectivePrice($edition) ?? 0.0) : 0.0;
        if ($price <= 0) {
            return [];
        }

        $userId = (int) $pre['user_id'];
        $installments = (int) ($edition['installments_count'] ?? 1);
        $allow = (int) ($edition['allow_installments'] ?? 0) === 1 && $installments > 1;

        if (!$allow) {
            Payment::store([
                'preinscription_id' => $preinscriptionId, 'user_id' => $userId,
                'concept' => 'matricula', 'sequence' => 1, 'amount' => $price,
                'status' => Payment::STATUS_PENDING, 'due_date' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $deposit = (float) ($edition['deposit'] ?? 0);
            $deposit = $deposit > 0 ? min($deposit, $price) : round($price / $installments, 2);
            Payment::store([
                'preinscription_id' => $preinscriptionId, 'user_id' => $userId,
                'concept' => 'matricula', 'sequence' => 1, 'amount' => $deposit,
                'status' => Payment::STATUS_PENDING, 'due_date' => date('Y-m-d H:i:s'),
            ]);
            $rest = round($price - $deposit, 2);
            $per = round($rest / max(1, $installments - 1), 2);
            for ($i = 2; $i <= $installments; $i++) {
                $amount = $i === $installments ? round($price - $deposit - $per * ($installments - 2), 2) : $per;
                Payment::store([
                    'preinscription_id' => $preinscriptionId, 'user_id' => $userId,
                    'concept' => 'plazo', 'sequence' => $i, 'amount' => $amount,
                    'status' => Payment::STATUS_PENDING,
                    'due_date' => date('Y-m-d H:i:s', strtotime('+' . ($i - 1) . ' month')),
                ]);
            }
        }
        Audit::log('payment.scheduled', null, 'preinscription', $preinscriptionId, ['price' => $price]);
        return Payment::forPreinscription($preinscriptionId);
    }

    /** Aplica un código de descuento al primer pago de matrícula pendiente. */
    public function applyDiscountCode(int $preinscriptionId, string $code): array
    {
        $discount = Discount::findValidByCode($code);
        if ($discount === null) {
            return ['ok' => false, 'message' => __('payments.discount_invalid')];
        }
        $pre = Preinscription::find($preinscriptionId);
        $edition = CourseEdition::findWithCourse((int) $pre['edition_id']);
        if (!Discount::appliesTo($discount, (int) $edition['course_id'], (int) $pre['edition_id'])) {
            return ['ok' => false, 'message' => __('payments.discount_not_applicable')];
        }
        $payments = Payment::forPreinscription($preinscriptionId);
        $target = null;
        foreach ($payments as $p) {
            if ($p['concept'] === 'matricula' && $p['status'] === Payment::STATUS_PENDING) {
                $target = $p;
                break;
            }
        }
        if ($target === null) {
            return ['ok' => false, 'message' => __('payments.discount_no_target')];
        }
        if ((float) $target['discount_amount'] > 0) {
            return ['ok' => false, 'message' => __('payments.discount_already')];
        }

        $amount = Discount::computeAmount($discount, (float) $target['amount']);
        Payment::update((int) $target['id'], ['discount_amount' => $amount]);
        Database::instance()->insert('discount_redemptions', [
            'discount_id' => (int) $discount['id'], 'preinscription_id' => $preinscriptionId,
            'user_id' => (int) $pre['user_id'], 'amount_applied' => $amount, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        Database::instance()->update('discounts', ['used_count' => (int) $discount['used_count'] + 1], ['id' => (int) $discount['id']]);
        Audit::log('payment.discount_applied', (int) $pre['user_id'], 'payment', (int) $target['id'], ['code' => $code, 'amount' => $amount]);
        return ['ok' => true, 'message' => __('payments.discount_applied', ['amount' => number_format($amount, 2)])];
    }

    /** Registra un justificante de transferencia/Bizum: queda en revisión. */
    public function submitProof(int $paymentId, string $method, string $proofPath, ?string $reference = null): void
    {
        Payment::update($paymentId, [
            'method' => $method,
            'proof_path' => $proofPath,
            'reference' => $reference,
            'status' => Payment::STATUS_REVIEW,
        ]);
        Audit::log('payment.proof_submitted', null, 'payment', $paymentId, ['method' => $method]);
    }

    /** Marca un pago como cobrado, emite factura y completa matrícula si procede. */
    public function markPaid(int $paymentId, ?string $method = null, ?int $actorId = null, ?string $reference = null): void
    {
        $payment = Payment::find($paymentId);
        if ($payment === null || $payment['status'] === Payment::STATUS_PAID) {
            return;
        }
        Payment::update($paymentId, [
            'status' => Payment::STATUS_PAID,
            'method' => $method ?? $payment['method'],
            'reference' => $reference ?? $payment['reference'],
            'paid_at' => date('Y-m-d H:i:s'),
            'validated_by' => $actorId,
            'validated_at' => date('Y-m-d H:i:s'),
        ]);
        Audit::log('payment.paid', $actorId, 'payment', $paymentId, ['method' => $method]);

        // Emitir factura del pago.
        (new InvoiceService())->issueForPayment(Payment::find($paymentId));

        // Notificar al estudiante.
        $user = User::findById((int) $payment['user_id']);
        if ($user !== null) {
            Notifier::toUser($user, 'payment_confirmed', ['name' => $user->name, 'amount' => number_format(Payment::netAmount($payment), 2)]);
        }

        // Si todos los pagos están cobrados, completar matrícula.
        if (Payment::allPaid((int) $payment['preinscription_id'])) {
            (new PreinscriptionService())->transition((int) $payment['preinscription_id'], PreinscriptionStatus::MATRICULADO, $actorId, 'all_paid');
        }
    }

    /** Validación por el gestor de un pago en revisión (transferencia/Bizum). */
    public function validateProof(int $paymentId, bool $approve, ?int $actorId): void
    {
        if ($approve) {
            $this->markPaid($paymentId, null, $actorId);
        } else {
            Payment::update($paymentId, ['status' => Payment::STATUS_REJECTED, 'validated_by' => $actorId, 'validated_at' => date('Y-m-d H:i:s')]);
            Audit::log('payment.proof_rejected', $actorId, 'payment', $paymentId, []);
        }
    }

    /** Reembolso total o parcial con nota de crédito. */
    public function refund(int $paymentId, float $amount, ?string $reason, ?int $actorId): void
    {
        $payment = Payment::find($paymentId);
        if ($payment === null) {
            return;
        }
        $amount = min($amount, Payment::netAmount($payment));
        $invoiceId = (new InvoiceService())->issueCreditNote($payment, $amount);
        Database::instance()->insert('refunds', [
            'payment_id' => $paymentId, 'invoice_id' => $invoiceId, 'amount' => $amount,
            'reason' => $reason, 'created_by' => $actorId, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        Payment::update($paymentId, ['status' => Payment::STATUS_REFUNDED]);
        Audit::log('payment.refunded', $actorId, 'payment', $paymentId, ['amount' => $amount]);
    }
}
