<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\I18n;

/**
 * Requisito documental configurable por curso o por edición.
 */
final class DocumentRequirement extends Model
{
    protected static string $table = 'document_requirements';

    public static function localized(?string $json): string
    {
        if (!is_string($json) || $json === '') {
            return '';
        }
        $bag = json_decode($json, true);
        if (!is_array($bag)) {
            return $json;
        }
        $loc = I18n::locale();
        return $bag[$loc] ?? ($bag['es'] ?? (reset($bag) ?: ''));
    }

    /** Requisitos aplicables a una edición (los de la edición + los del curso). @return array<int,array<string,mixed>> */
    public static function forEdition(int $editionId, int $courseId): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM {document_requirements}
             WHERE edition_id = ? OR (course_id = ? AND edition_id IS NULL)
             ORDER BY sort_order, id',
            [$editionId, $courseId],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function forCourse(int $courseId): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM {document_requirements} WHERE course_id = ? ORDER BY sort_order, id',
            [$courseId],
        );
    }

    /** @param array<string,mixed> $data */
    public static function store(array $data): int
    {
        return Database::instance()->insert('document_requirements', $data + ['created_at' => date('Y-m-d H:i:s')]);
    }

    public static function delete(int $id): void
    {
        Database::instance()->run('DELETE FROM {document_requirements} WHERE id = ?', [$id]);
    }
}
