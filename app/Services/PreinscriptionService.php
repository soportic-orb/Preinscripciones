<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Preinscription;
use App\Models\User;

/**
 * Lógica de negocio de la preinscripción: máquina de estados, control de aforo
 * y lista de espera con promoción automática.
 */
final class PreinscriptionService
{
    /**
     * Aplica una transición de estado validada, registra historial + auditoría
     * y notifica al estudiante. Devuelve true si se aplicó.
     */
    public function transition(int $preinscriptionId, string $to, ?int $actorId = null, ?string $note = null): bool
    {
        $pre = Preinscription::find($preinscriptionId);
        if ($pre === null) {
            return false;
        }
        $from = (string) $pre['status'];
        if ($from === $to) {
            return true;
        }
        if (!PreinscriptionStatus::canTransition($from, $to)) {
            return false;
        }

        $db = Database::instance();
        $changes = ['status' => $to];
        if (in_array($to, [PreinscriptionStatus::ACEPTADO, PreinscriptionStatus::RECHAZADO], true)) {
            $changes['decided_by'] = $actorId;
            $changes['decided_at'] = date('Y-m-d H:i:s');
        }
        if ($to === PreinscriptionStatus::RECHAZADO && $note !== null) {
            $changes['reject_reason'] = $note;
        }
        if ($to === PreinscriptionStatus::EN_LISTA_ESPERA) {
            $changes['waitlist_position'] = $this->nextWaitlistPosition((int) $pre['edition_id']);
        }
        Preinscription::update($preinscriptionId, $changes);

        $db->insert('preinscription_status_history', [
            'preinscription_id' => $preinscriptionId,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actorId,
            'note' => $note,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        Audit::log('preinscription.transition', $actorId, 'preinscription', $preinscriptionId, [
            'from' => $from, 'to' => $to,
        ]);

        $this->notify($preinscriptionId, $to, $note);

        // Si se libera una plaza (rechazo/cancelación de alguien aceptado), promover lista de espera.
        if (in_array($from, [PreinscriptionStatus::ACEPTADO, PreinscriptionStatus::PENDIENTE_PAGO], true)
            && in_array($to, [PreinscriptionStatus::RECHAZADO, PreinscriptionStatus::CANCELADO], true)) {
            $this->promoteWaitlist((int) $pre['edition_id'], $actorId);
        }

        return true;
    }

    /** ¿Hay plazas libres en la edición? */
    public function hasFreeSeats(int $editionId): bool
    {
        $edition = CourseEdition::find($editionId);
        if ($edition === null) {
            return false;
        }
        $capacity = (int) $edition['capacity'];
        if ($capacity <= 0) {
            return true; // 0 = sin límite
        }
        return Preinscription::occupiedSeats($editionId) < $capacity;
    }

    /** Acepta una preinscripción si hay aforo; si no, la envía a lista de espera. */
    public function accept(int $preinscriptionId, ?int $actorId = null): string
    {
        $pre = Preinscription::find($preinscriptionId);
        if ($pre === null) {
            return 'error';
        }
        if (!$this->hasFreeSeats((int) $pre['edition_id'])) {
            $this->transition($preinscriptionId, PreinscriptionStatus::EN_LISTA_ESPERA, $actorId);
            return PreinscriptionStatus::EN_LISTA_ESPERA;
        }
        $this->transition($preinscriptionId, PreinscriptionStatus::ACEPTADO, $actorId);
        return PreinscriptionStatus::ACEPTADO;
    }

    private function nextWaitlistPosition(int $editionId): int
    {
        return (int) Database::instance()->scalar(
            "SELECT COALESCE(MAX(waitlist_position),0)+1 FROM {preinscriptions}
             WHERE edition_id = ? AND status = 'en_lista_de_espera'",
            [$editionId],
        );
    }

    /** Promueve al siguiente de la lista de espera si hay plazas libres. */
    public function promoteWaitlist(int $editionId, ?int $actorId = null): void
    {
        if (!$this->hasFreeSeats($editionId)) {
            return;
        }
        $next = Preinscription::nextInWaitlist($editionId);
        if ($next === null) {
            return;
        }
        $this->transition((int) $next['id'], PreinscriptionStatus::ACEPTADO, $actorId, 'waitlist_promotion');
    }

    private function notify(int $preinscriptionId, string $status, ?string $note): void
    {
        $full = Preinscription::findFull($preinscriptionId);
        if ($full === null) {
            return;
        }
        $user = User::findById((int) $full['user_id']);
        if ($user === null) {
            return;
        }
        $event = match ($status) {
            PreinscriptionStatus::ACEPTADO => 'preinscription_accepted',
            PreinscriptionStatus::RECHAZADO => 'preinscription_rejected',
            PreinscriptionStatus::EN_LISTA_ESPERA => 'preinscription_waitlisted',
            PreinscriptionStatus::PENDIENTE_PAGO => 'enrollment_available',
            default => null,
        };
        if ($event === null) {
            return;
        }
        Notifier::toUser($user, $event, [
            'name' => $user->name,
            'course' => Course::localized($full['course_title'] ?? ''),
            'edition' => (string) ($full['edition_name'] ?? ''),
            'reason' => (string) ($note ?? ''),
        ]);
    }
}
