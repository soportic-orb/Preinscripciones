<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Models\CourseEdition;
use App\Models\DocumentRequirement;
use App\Models\Preinscription;
use App\Services\DocumentService;
use App\Services\FieldService;
use App\Services\PreinscriptionService;
use App\Services\PreinscriptionStatus;

/**
 * Gestión de preinscripciones e inscripciones por el personal (gestor/admin):
 * listado con filtros, validación documental, aceptar/rechazar, transiciones
 * de estado y lista de espera.
 */
final class ManagePreinscriptionsController extends Controller
{
    public function index(Request $request): never
    {
        $filters = [
            'status' => $request->str('status'),
            'edition_id' => $request->str('edition_id'),
            'q' => $request->str('q'),
        ];
        $this->view('management/preinscriptions/index', [
            'title' => __('management.preinscriptions'),
            'user' => Auth::user(),
            'filters' => $filters,
            'rows' => Preinscription::search($filters),
            'statuses' => PreinscriptionStatus::all(),
            'editions' => Database::instance()->fetchAll('SELECT id, name FROM {course_editions} ORDER BY name'),
        ]);
    }

    public function show(Request $request): never
    {
        $pre = Preinscription::findFull((int) $request->route('id'));
        if ($pre === null) {
            Flash::error(__('preinscription.not_found'));
            $this->redirect('/gestion/preinscripciones');
        }
        $docSvc = new DocumentService();
        $edition = CourseEdition::findWithCourse((int) $pre['edition_id']);
        $this->view('management/preinscriptions/show', [
            'title' => __('management.preinscription_detail'),
            'user' => Auth::user(),
            'pre' => $pre,
            'edition' => $edition,
            'values' => (new FieldService())->values('preinscription', (int) $pre['id']),
            'academic' => (new FieldService())->values('academic', (int) $pre['id']),
            'requirements' => DocumentRequirement::forEdition((int) $pre['edition_id'], (int) ($edition['course_id'] ?? 0)),
            'documents' => $docSvc->forPreinscription((int) $pre['id']),
            'guardian' => Database::instance()->fetch('SELECT * FROM {guardians} WHERE preinscription_id = ? LIMIT 1', [$pre['id']]),
            'history' => Database::instance()->fetchAll('SELECT * FROM {preinscription_status_history} WHERE preinscription_id = ? ORDER BY id DESC', [$pre['id']]),
            'allValidated' => $docSvc->allRequiredValidated((int) $pre['id'], (int) $pre['edition_id'], (int) ($edition['course_id'] ?? 0)),
        ]);
    }

    /** Valida o rechaza un documento. */
    public function validateDocument(Request $request): never
    {
        $docId = (int) $request->route('id');
        $approve = $request->str('decision') === 'approve';
        (new DocumentService())->validateDocument($docId, $approve, (int) Auth::id(), $approve ? null : $request->str('reason'));
        $doc = Database::instance()->fetch('SELECT preinscription_id FROM {preinscription_documents} WHERE id = ?', [$docId]);
        Flash::success($approve ? __('documents.validated') : __('documents.rejected'));
        $this->redirect('/gestion/preinscripciones/' . (int) ($doc['preinscription_id'] ?? 0));
    }

    /** Acepta la preinscripción (con control de aforo → lista de espera). */
    public function accept(Request $request): never
    {
        $id = (int) $request->route('id');
        $result = (new PreinscriptionService())->accept($id, (int) Auth::id());
        Flash::success($result === PreinscriptionStatus::EN_LISTA_ESPERA ? __('management.moved_waitlist') : __('management.accepted'));
        $this->redirect('/gestion/preinscripciones/' . $id);
    }

    public function reject(Request $request): never
    {
        $id = (int) $request->route('id');
        (new PreinscriptionService())->transition($id, PreinscriptionStatus::RECHAZADO, (int) Auth::id(), $request->str('reason'));
        Flash::info(__('management.rejected'));
        $this->redirect('/gestion/preinscripciones/' . $id);
    }

    /** Transición de estado genérica controlada. */
    public function transition(Request $request): never
    {
        $id = (int) $request->route('id');
        $to = $request->str('to');
        $ok = (new PreinscriptionService())->transition($id, $to, (int) Auth::id(), $request->str('note') ?: null);
        Flash::add($ok ? 'success' : 'error', $ok ? __('management.state_changed') : __('management.invalid_transition'));
        $this->redirect('/gestion/preinscripciones/' . $id);
    }

    /** Promueve manualmente al siguiente de la lista de espera. */
    public function promoteWaitlist(Request $request): never
    {
        $editionId = (int) $request->input('edition_id');
        (new PreinscriptionService())->promoteWaitlist($editionId, (int) Auth::id());
        Flash::success(__('management.waitlist_promoted'));
        $this->redirect('/gestion/preinscripciones');
    }
}
