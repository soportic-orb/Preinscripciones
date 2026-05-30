<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Core\I18n;
use App\Models\LegalDocument;

/**
 * Registro de consentimientos versionados (RGPD/LOPDGDD).
 *
 * Guarda qué versión de qué texto legal aceptó cada usuario, cuándo y desde
 * qué IP. Soporta el doble consentimiento del tutor (is_guardian).
 */
final class ConsentService
{
    /**
     * Registra el consentimiento del usuario para la versión vigente de un tipo.
     */
    public function record(int $userId, string $docType, ?string $ip = null, bool $isGuardian = false): void
    {
        $version = LegalDocument::currentVersion($docType);
        if ($version === 0) {
            return; // No hay texto publicado: nada que consentir todavía.
        }
        Database::instance()->insert('consents', [
            'user_id' => $userId,
            'doc_type' => $docType,
            'version' => $version,
            'locale' => I18n::locale(),
            'is_guardian' => $isGuardian ? 1 : 0,
            'accepted_at' => date('Y-m-d H:i:s'),
            'ip' => $ip,
        ]);
        Audit::log('consent.accepted', $userId, 'legal', null, [
            'doc_type' => $docType,
            'version' => $version,
            'guardian' => $isGuardian,
        ], $ip);
    }

    /** Registra el consentimiento de todos los textos legales vigentes. */
    public function recordAllCurrent(int $userId, ?string $ip = null): void
    {
        foreach (LegalDocument::TYPES as $type) {
            $this->record($userId, $type, $ip);
        }
    }

    /** ¿El usuario ha aceptado la versión vigente de un tipo? */
    public function hasAcceptedCurrent(int $userId, string $docType): bool
    {
        $version = LegalDocument::currentVersion($docType);
        if ($version === 0) {
            return true;
        }
        return (bool) Database::instance()->scalar(
            'SELECT 1 FROM {consents} WHERE user_id = ? AND doc_type = ? AND version = ? LIMIT 1',
            [$userId, $docType, $version],
        );
    }

    /** @return array<int,array<string,mixed>> historial de consentimientos del usuario */
    public function history(int $userId): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM {consents} WHERE user_id = ? ORDER BY accepted_at DESC',
            [$userId],
        );
    }
}
