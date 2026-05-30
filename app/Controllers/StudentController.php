<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\DocumentRequirement;
use App\Models\Preinscription;
use App\Services\DocumentService;
use App\Services\FieldService;

/**
 * Panel de Estudiante: detalle de preinscripciones, documentos, descarga
 * segura de archivos y derechos RGPD (exportar datos / solicitar supresión).
 */
final class StudentController extends Controller
{
    /** Detalle de una preinscripción del propio estudiante. */
    public function show(Request $request): never
    {
        $pre = $this->loadOwned($request);
        $edition = CourseEdition::findWithCourse((int) $pre['edition_id']);
        $docSvc = new DocumentService();
        $this->view('student/preinscription', [
            'title' => __('dashboard.student_title'),
            'pre' => $pre,
            'edition' => $edition,
            'values' => (new FieldService())->values('preinscription', (int) $pre['id']),
            'requirements' => DocumentRequirement::forEdition((int) $pre['edition_id'], (int) ($edition['course_id'] ?? 0)),
            'documents' => $docSvc->forPreinscription((int) $pre['id']),
        ]);
    }

    /** Re-subida de un documento (p. ej. tras rechazo). */
    public function uploadDocument(Request $request): never
    {
        $pre = $this->loadOwned($request);
        $requirementId = (int) $request->input('requirement_id') ?: null;
        $reqRow = $requirementId ? Database::instance()->fetch('SELECT * FROM {document_requirements} WHERE id = ?', [$requirementId]) : null;
        $hasExpiry = $reqRow && (int) $reqRow['has_expiry'] === 1;
        $result = (new DocumentService())->upload(
            $request->files['document'] ?? [],
            (int) $pre['id'],
            $requirementId,
            $hasExpiry,
            $hasExpiry ? $request->str('expires_at') : null,
        );
        Flash::add($result['ok'] ? 'success' : 'error', $result['ok'] ? __('documents.uploaded') : ($result['error'] ?? __('documents.upload_error')));
        $this->redirect('/panel/preinscripcion/' . $pre['id']);
    }

    /** Descarga segura de un documento (estudiante propietario o staff). */
    public function download(Request $request): never
    {
        $doc = Database::instance()->fetch('SELECT * FROM {preinscription_documents} WHERE id = ?', [(int) $request->route('id')]);
        if ($doc === null) {
            Response::html('<h1>404</h1>', 404);
        }
        $pre = Preinscription::find((int) $doc['preinscription_id']);
        $user = Auth::user();
        $isOwner = $pre !== null && (int) $pre['user_id'] === (int) Auth::id();
        $isStaff = $user !== null && \App\Core\Rbac::isStaff($user);
        if (!$isOwner && !$isStaff) {
            Response::html(\App\Core\View::render('errors/403', [], 'layouts/app'), 403);
        }
        $path = (new DocumentService())->absolutePath((string) $doc['file_path']);
        if (!is_file($path)) {
            Response::html('<h1>404</h1>', 404);
        }
        Audit::log('document.download', Auth::id(), 'document', (int) $doc['id'], []);
        header('Content-Type: ' . ($doc['mime'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . basename((string) $doc['original_name']) . '"');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
    }

    // ------------------------------------------------------------- RGPD
    public function exportData(Request $request): never
    {
        $user = Auth::user();
        if ($user === null) {
            $this->redirect('/login');
        }
        $db = Database::instance();
        $data = [
            'user' => $db->fetch('SELECT id, name, email, role, locale, created_at FROM {users} WHERE id = ?', [$user->id]),
            'preinscriptions' => Preinscription::forUser($user->id),
            'field_values' => $db->fetchAll(
                'SELECT d.form_key, d.field_key, v.value FROM {field_values} v JOIN {field_definitions} d ON d.id = v.field_id WHERE v.entity_id IN (SELECT id FROM {preinscriptions} WHERE user_id = ?)',
                [$user->id],
            ),
            'consents' => $db->fetchAll('SELECT doc_type, version, locale, accepted_at, ip FROM {consents} WHERE user_id = ?', [$user->id]),
            'exported_at' => date('c'),
        ];
        Audit::log('rgpd.export', $user->id, 'user', $user->id, [], $request->ip());
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="mis-datos-iem.json"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function requestDeletion(Request $request): never
    {
        $user = Auth::user();
        if ($user !== null) {
            \App\Core\Settings::set('rgpd_deletion_requests', 'user_' . $user->id, date('c'));
            Audit::log('rgpd.deletion_requested', $user->id, 'user', $user->id, [], $request->ip());
            Flash::success(__('rgpd.deletion_requested'));
        }
        $this->redirect('/panel');
    }

    private function loadOwned(Request $request): array
    {
        $pre = Preinscription::find((int) $request->route('id'));
        if ($pre === null || (int) $pre['user_id'] !== (int) Auth::id()) {
            Flash::error(__('preinscription.not_found'));
            $this->redirect('/panel');
        }
        return $pre;
    }
}
