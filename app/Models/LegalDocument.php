<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\I18n;

/**
 * Texto legal versionado (política de privacidad, términos, cancelación).
 *
 * Cada doc_type tiene versiones incrementales; cada versión puede tener una
 * fila por idioma. La "versión vigente" es la mayor versión publicada.
 */
final class LegalDocument extends Model
{
    protected static string $table = 'legal_documents';

    public const TYPES = ['privacy', 'terms', 'cancellation'];

    /** Versión vigente (número) de un tipo, o 0 si no hay ninguna publicada. */
    public static function currentVersion(string $docType): int
    {
        $v = Database::instance()->scalar(
            'SELECT MAX(version) FROM {legal_documents} WHERE doc_type = ? AND is_published = 1',
            [$docType],
        );
        return (int) ($v ?? 0);
    }

    /** Documento vigente en un idioma (con fallback a es). @return array<string,mixed>|null */
    public static function current(string $docType, ?string $locale = null): ?array
    {
        $version = self::currentVersion($docType);
        if ($version === 0) {
            return null;
        }
        $locale = $locale ?? I18n::locale();
        $db = Database::instance();
        $row = $db->fetch(
            'SELECT * FROM {legal_documents} WHERE doc_type = ? AND version = ? AND locale = ? LIMIT 1',
            [$docType, $version, $locale],
        );
        if ($row === null) {
            $row = $db->fetch(
                'SELECT * FROM {legal_documents} WHERE doc_type = ? AND version = ? ORDER BY (locale = "es") DESC LIMIT 1',
                [$docType, $version],
            );
        }
        return $row;
    }

    /** Todas las versiones (agrupadas) para administración. @return array<int,array<string,mixed>> */
    public static function allVersions(): array
    {
        return Database::instance()->fetchAll(
            'SELECT doc_type, version, MAX(is_published) AS is_published, MAX(created_at) AS created_at,
                    COUNT(*) AS locales
             FROM {legal_documents}
             GROUP BY doc_type, version
             ORDER BY doc_type, version DESC',
        );
    }

    /**
     * Crea una nueva versión de un tipo con sus traducciones y la publica.
     *
     * @param array<string,array{title:string,body:string}> $translations
     */
    public static function publishNewVersion(string $docType, array $translations): int
    {
        $db = Database::instance();
        $version = (int) ($db->scalar('SELECT COALESCE(MAX(version),0) FROM {legal_documents} WHERE doc_type = ?', [$docType])) + 1;
        $now = date('Y-m-d H:i:s');
        foreach ($translations as $locale => $t) {
            if (trim($t['title'] ?? '') === '' && trim($t['body'] ?? '') === '') {
                continue;
            }
            $db->insert('legal_documents', [
                'doc_type' => $docType,
                'version' => $version,
                'locale' => $locale,
                'title' => $t['title'] ?? '',
                'body' => $t['body'] ?? '',
                'is_published' => 1,
                'published_at' => $now,
                'created_at' => $now,
            ]);
        }
        return $version;
    }
}
