<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Config;
use App\Core\Database;

/**
 * Subida, validación y entrega de documentos de preinscripción.
 *
 * Por privacidad (datos de menores/tutores), los archivos se guardan FUERA del
 * webroot, en storage/uploads/preinscriptions/<id>/, y se sirven solo mediante
 * un endpoint autenticado con control de acceso.
 */
final class DocumentService
{
    private const ALLOWED_MIME = [
        'application/pdf', 'image/jpeg', 'image/png', 'image/heic',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function maxBytes(): int
    {
        return Config::app()['max_upload_mb'] * 1024 * 1024;
    }

    /**
     * Procesa una subida y crea/actualiza la fila del documento.
     *
     * @param array<string,mixed> $file entrada de $_FILES
     * @return array{ok:bool,error?:string,id?:int}
     */
    public function upload(array $file, int $preinscriptionId, ?int $requirementId, bool $hasExpiry = false, ?string $expiresAt = null): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => __('documents.upload_error')];
        }
        if (($file['size'] ?? 0) > $this->maxBytes()) {
            return ['ok' => false, 'error' => __('documents.too_large')];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            return ['ok' => false, 'error' => __('documents.invalid_type')];
        }

        $dir = STORAGE_PATH . '/uploads/preinscriptions/' . $preinscriptionId;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => __('documents.upload_error')];
        }

        $ext = pathinfo((string) $file['name'], PATHINFO_EXTENSION);
        $safeName = bin2hex(random_bytes(8)) . ($ext !== '' ? '.' . preg_replace('/[^a-z0-9]/i', '', $ext) : '');
        $dest = $dir . '/' . $safeName;
        if (!@move_uploaded_file($file['tmp_name'], $dest) && !@rename($file['tmp_name'], $dest)) {
            return ['ok' => false, 'error' => __('documents.upload_error')];
        }

        $db = Database::instance();
        // Reemplazar documento previo del mismo requisito (re-subida tras rechazo).
        if ($requirementId !== null) {
            $prev = $db->fetch(
                'SELECT * FROM {preinscription_documents} WHERE preinscription_id = ? AND requirement_id = ?',
                [$preinscriptionId, $requirementId],
            );
            if ($prev !== null) {
                @unlink(STORAGE_PATH . '/uploads/' . $prev['file_path']);
                $db->run('DELETE FROM {preinscription_documents} WHERE id = ?', [$prev['id']]);
            }
        }

        $id = $db->insert('preinscription_documents', [
            'preinscription_id' => $preinscriptionId,
            'requirement_id' => $requirementId,
            'file_path' => 'preinscriptions/' . $preinscriptionId . '/' . $safeName,
            'original_name' => substr((string) $file['name'], 0, 255),
            'mime' => $mime,
            'size_bytes' => (int) $file['size'],
            'status' => 'pendiente',
            'expires_at' => $hasExpiry ? $expiresAt : null,
            'uploaded_at' => date('Y-m-d H:i:s'),
        ]);

        Audit::log('document.upload', null, 'preinscription', $preinscriptionId, ['requirement' => $requirementId]);
        return ['ok' => true, 'id' => $id];
    }

    /** @return array<int,array<string,mixed>> documentos de una preinscripción */
    public function forPreinscription(int $preinscriptionId): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM {preinscription_documents} WHERE preinscription_id = ? ORDER BY id',
            [$preinscriptionId],
        );
    }

    public function validateDocument(int $documentId, bool $approve, ?int $actorId, ?string $reason = null): void
    {
        Database::instance()->update('preinscription_documents', [
            'status' => $approve ? 'validado' : 'rechazado',
            'reject_reason' => $approve ? null : $reason,
            'validated_by' => $actorId,
            'validated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $documentId]);
        Audit::log($approve ? 'document.validate' : 'document.reject', $actorId, 'document', $documentId, ['reason' => $reason]);
    }

    /** ¿Toda la documentación obligatoria está validada para una preinscripción? */
    public function allRequiredValidated(int $preinscriptionId, int $editionId, int $courseId): bool
    {
        $reqs = \App\Models\DocumentRequirement::forEdition($editionId, $courseId);
        $docs = $this->forPreinscription($preinscriptionId);
        $byReq = [];
        foreach ($docs as $d) {
            $byReq[(int) ($d['requirement_id'] ?? 0)] = $d['status'];
        }
        foreach ($reqs as $r) {
            if ((int) $r['is_required'] === 1 && ($byReq[(int) $r['id']] ?? null) !== 'validado') {
                return false;
            }
        }
        return true;
    }

    public function absolutePath(string $relative): string
    {
        return STORAGE_PATH . '/uploads/' . $relative;
    }
}
